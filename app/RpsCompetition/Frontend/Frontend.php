<?php
namespace RpsCompetition\Frontend;

use Illuminate\Http\Request;
use RpsCompetition\Api\Client;
use RpsCompetition\Common\Core;
use RpsCompetition\Constants;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Settings;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Db\QueryBanquet;

class Frontend
{

    /**
     *
     * @var Core
     */
    private $core;

    /**
     *
     * @var Settings
     */
    private $settings;

    /**
     *
     * @var RpsDb
     */
    private $rpsdb;

    /**
     *
     * @var Request
     */
    private $request;

    /**
     * PHP5 Constructor
     */
    public function __construct(\Illuminate\Container\Container $container)
    {
        $this->settings = $container->make('RpsCompetition\Settings');
        $this->core = $container->make('RpsCompetition\Common\Core');
        $this->rpsdb = $container->make('RpsCompetition\Db\RpsDb');
        $this->request = $container->make('Illuminate\Http\Request');
        $this->container = $container;

        $this->settings->errmsg = '';

        // The actions are in order as how WordPress executes them
        add_action('after_setup_theme', array($this, 'actionAfterThemeSetup'), 14);
        add_action('init', array($this, 'actionInit'));
        add_action('wp', array($this, 'actionPreHeaderRpsMyEntries'));
        add_action('wp', array($this, 'actionPreHeaderRpsEditTitle'));
        add_action('wp', array($this, 'actionPreHeaderRpsUploadEntry'));
        add_action('wp', array($this, 'actionPreHeaderRpsBanquetEntries'));
        add_action('template_redirect', array($this, 'actionTemplateRedirectRpsWindowsClient'));
    }

    public function actionAfterThemeSetup()
    {
        add_action('rps_showcase', array($this, 'actionShowcaseCompetitionThumbnails'));
    }

    public function actionInit()
    {
        $query_competitions = new QueryCompetitions($this->rpsdb);
        /* @var $shortcode \RpsCompetition\Frontend\Shortcodes */
        $shortcode = $this->container->make('RpsCompetition\Frontend\Shortcodes');
        $shortcode->register('rps_category_winners', 'displayCategoryWinners');
        $shortcode->register('rps_monthly_winners', 'displayMonthlyWinners');
        $shortcode->register('rps_scores_current_user', 'displayScoresCurrentUser');
        $shortcode->register('rps_banquet_current_user', 'displayBanquetCurrentUser');
        $shortcode->register('rps_all_scores', 'displayAllScores');
        $shortcode->register('rps_my_entries', 'displayMyEntries');
        $shortcode->register('rps_edit_title', 'displayEditTitle');
        $shortcode->register('rps_upload_image', 'displayUploadEntry');
        $shortcode->register('rps_email', 'displayEmail');
        $shortcode->register('rps_person_winners', 'displayPersonWinners');
        $shortcode->register('rps_monthly_entries', 'displayMonthlyEntries');
        $user_id = get_current_user_id();
        $query_competitions->setAllPastCompetitionsClose();

        $x = get_user_meta($user_id, 'rps_class_bw', true);
        if (empty($x)) {
            update_user_meta($user_id, "rps_class_bw", 'beginner');
            update_user_meta($user_id, "rps_class_color", 'beginner');
            update_user_meta($user_id, "rps_class_print_bw", 'beginner');
            update_user_meta($user_id, "rps_class_print_color", 'beginner');
        }
        unset($query_competitions);
    }

    public function actionTemplateRedirectRpsWindowsClient()
    {
        if ($this->request->has('rpswinclient')) {

            define('DONOTCACHEPAGE', true);
            global $hyper_cache_stop;
            $hyper_cache_stop = true;
            add_filter('w3tc_can_print_comment', '__return_false');

            // Properties of the logged in user
            status_header(200);
            switch ($this->request->input('rpswinclient')) {
                case 'getcompdate':
                    Client::sendXmlCompetitionDates($this->request);
                    break;
                case 'download':
                    Client::sendCompetitions($this->request);
                    break;
                case 'uploadscore':
                    Client::doUploadScore($this->request);
                    break;
                default:
                    break;
            }
        }
    }

    public function actionShowcaseCompetitionThumbnails($ctr)
    {
        if (is_front_page()) {
            $query_miscellaneous = new QueryMiscellaneous($this->rpsdb);
            $image = array();
            $seasons = $query_miscellaneous->getSeasonList('ASC', $this->settings->club_season_start_month_num, $this->settings->club_season_end_month_num);
            $from_season = $seasons[count($seasons) - 3];

            $season_start_year = substr($from_season, 0, 4);
            $season = sprintf("%d-%02s-%02s", $season_start_year, $this->settings->club_season_start_month_num, 1);

            echo '<div class="rps-sc-tile suf-tile-1c entry-content bottom">';

            echo '<div class="suf-gradient suf-tile-topmost">';
            echo '<h3>Showcase</h3>';
            echo '</div>';

            echo '<div class="gallery gallery-columns-5 gallery-size-150">';
            echo '<div class="gallery-row gallery-row-equal">';
            $records = $query_miscellaneous->getEightsAndHigher(5);

            foreach ($records as $recs) {
                $user_info = get_userdata($recs->Member_ID);
                $recs->FirstName = $user_info->user_firstname;
                $recs->LastName = $user_info->user_lastname;
                $recs->Username = $user_info->user_login;

                // Grab a new record from the database
                $dateParts = explode(" ", $recs->Competition_Date);
                $comp_date = $dateParts[0];
                $medium = $recs->Medium;
                $classification = $recs->Classification;
                $comp = "$classification<br>$medium";
                $title = $recs->Title;
                $last_name = $recs->LastName;
                $first_name = $recs->FirstName;
                $award = $recs->Award;
                // Display this thumbnail in the the next available column

                echo '<figure class="gallery-item">';
                echo '<div class="gallery-item-content">';
                echo '<div class="gallery-item-content-image">';
                echo '<a href="' . $this->core->rpsGetThumbnailUrl($recs, 800) . '" rel="rps-showcase" title="' . $title . ' by ' . $first_name . ' ' . $last_name . '">';
                echo '<img src="' . $this->core->rpsGetThumbnailUrl($recs, 150) . '" /></a>';
                echo '</div>';
                $caption = "${title}<br /><span class='wp-caption-credit'>Credit: ${first_name} ${last_name}";
                echo "<figcaption class='wp-caption-text showcase-caption'>" . wptexturize($caption) . "</figcaption>\n";
                echo '</div>';

                echo '</figure>' . "\n";
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';

            unset($query_miscellaneous);
        }
    }

    public function actionPreHeaderRpsMyEntries()
    {
        global $post;
        $query_competitions = new QueryCompetitions($this->rpsdb);

        if (is_object($post) && ($post->ID == 56 || $post->ID == 58)) {
            $this->settings->comp_date = "";
            $this->settings->classification = "";
            $this->settings->medium = "";
            $this->settings->errmsg = '';

            $page = explode('-', $post->post_name);
            $this->settings->medium_subset = $page[1];
            if ($this->request->has('submit_control')) {
                // @TODO Nonce check

                $this->settings->comp_date = $this->request->input('comp_date');
                $this->settings->classification = $this->request->input('classification');
                $this->settings->medium = $this->request->input('medium');
                $t = time() + (2 * 24 * 3600);
                $url = parse_url(get_bloginfo('url'));
                setcookie("RPS_MyEntries", $this->settings->comp_date . "|" . $this->settings->classification . "|" . $this->settings->medium, $t, '/', $url['host']);

                if ($this->request->has('EntryID')) {
                    $entry_array = $this->request->input('EntryID');
                }
                $medium_subset = $this->request->input('medium_subset');
                $medium_param = "?medium=" . strtolower($medium_subset);

                switch ($this->request->input('submit_control')) {

                    case 'select_comp':
                        $this->settings->comp_date = $this->request->input('select_comp');
                        break;

                    case 'select_medium':
                        $this->settings->medium = $this->request->input('select_medium');
                        break;

                    case 'add':
                        if (!$query_competitions->checkCompetitionClosed($this->settings->comp_date, $this->settings->classification, $this->settings->medium)) {
                            $_query = array('m' => $this->settings->medium_subset);
                            $_query = build_query($_query);
                            $loc = '/member/upload-image/?' . $_query;
                            wp_redirect($loc);
                        }
                        break;

                    case 'edit':
                        if (!$query_competitions->checkCompetitionClosed($this->settings->comp_date, $this->settings->classification, $this->settings->medium)) {
                            if (isset($entry_array) && is_array($entry_array)) {
                                foreach ($entry_array as $id) {
                                    // @TODO Add Nonce
                                    $_query = array('id' => $id, 'm' => $this->settings->medium_subset);
                                    $_query = build_query($_query);
                                    $loc = '/member/edit-title/?' . $_query;
                                    wp_redirect($loc);
                                }
                            }
                        }
                        break;

                    case 'delete':
                        if (!$query_competitions->checkCompetitionClosed($this->settings->comp_date, $this->settings->classification, $this->settings->medium)) {
                            $this->deleteCompetitionEntries($entry_array);
                        }
                        break;
                }
            }

            if (!$this->request->isMethod('POST')) {
                if ($this->request->hasCookie('RPS_MyEntries')) {
                    list ($comp_date, $classification, $medium) = explode("|", $this->request->cookie('RPS_MyEntries'));
                    $this->settings->comp_date = $comp_date;
                    $this->settings->classification = $classification;
                    $this->settings->medium = $medium;
                }
            }
            $this->settings->validComp = $this->validateSelectedComp($this->settings->comp_date, $this->settings->medium);
            if ($this->settings->validComp === false) {
                $this->settings->comp_date = '';
                $this->settings->classification = '';
                $this->settings->medium = '';
                $this->settings->errmsg = 'There are no competitions available to enter';
                $url = parse_url(get_bloginfo('url'));
                setcookie("RPS_MyEntries", $this->settings->comp_date . "|" . $this->settings->classification . "|" . $this->settings->medium, time() - (24 * 3600), '/', $url['host']);
            }
        }
        unset($query_competitions);
    }

    /**
     * Handle $_POST Edit Title
     */
    public function actionPreHeaderRpsEditTitle()
    {
        global $post;

        $query_entries = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->rpsdb);
        if (is_object($post) && $post->ID == 75) {
            if ($this->request->isMethod('POST')) {
                $redirect_to = $this->request->input('wp_get_referer');
                $this->_medium_subset = $this->request->input('m');
                $this->_entry_id = $this->request->input('id');

                // Just return to the My Images page is the user clicked Cancel
                if ($this->request->has('cancel')) {

                    wp_redirect($redirect_to);
                    exit();
                }

                if ($this->request->has('m')) {

                    if (get_magic_quotes_gpc()) {
                        $server_file_name = stripslashes($this->request->input('server_file_name'));
                        $new_title = stripslashes(trim($this->request->input('new_title')));
                    } else {
                        $server_file_name = $this->request->input('server_file_name');
                        $new_title = trim($this->request->input('new_title'));
                    }
                }
                // makes sure they filled in the title field
                if (!$this->request->has('new_title')) {
                    $this->settings->errmsg = 'You must provide an image title.<br><br>';
                } else {
                    $recs = $query_competitions->getCompetitionByEntryId($this->_entry_id);
                    if ($recs == null) {
                        wp_die("Failed to SELECT competition for entry ID: " . $this->_entry_id);
                    }

                    $dateParts = explode(" ", $recs->Competition_Date);
                    $comp_date = $dateParts[0];
                    $classification = $recs->Classification;
                    $medium = $recs->Medium;

                    // Rename the image file on the server file system
                    $ext = ".jpg";
                    $path = '/Digital_Competitions/' . $comp_date . '_' . $classification . '_' . $medium;
                    $old_file_parts = pathinfo($server_file_name);
                    $old_file_name = $old_file_parts['filename'];
                    $current_user = wp_get_current_user();
                    $new_file_name_noext = sanitize_file_name($new_title) . '+' . $current_user->user_login . '+' . filemtime($this->request->server('DOCUMENT_ROOT') . $server_file_name);
                    $new_file_name = $new_file_name_noext . $ext;
                    if (!$this->core->renameImageFile($path, $old_file_name, $new_file_name_noext, $ext)) {
                        die("<b>Failed to rename image file</b><br>" . "Path: $path<br>Old Name: $old_file_name<br>" . "New Name: $new_file_name_noext");
                    }

                    // Update the Title and File Name in the database
                    $updated_data = array('ID' => $this->_entry_id, 'Title' => $new_title, 'Server_File_Name' => $path . '/' . $new_file_name, 'Date_Modified' => current_time('mysql'));
                    $_result = $query_entries->updateEntry($updated_data);
                    if ($_result === false) {
                        wp_die("Failed to UPDATE entry record from database");
                    }

                    $redirect_to = $this->request->input('wp_get_referer');
                    wp_redirect($redirect_to);
                    exit();
                }
            }
        }
        unset($query_entries);
    }

    public function actionPreHeaderRpsUploadEntry()
    {
        global $post;
        $query_entries = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->rpsdb);

        if (is_object($post) && $post->ID == 89) {
            if ($this->request->has('post')) {
                $redirect_to = $this->request->input('wp_get_referer');

                // Just return if user clicked Cancel
                if ($this->request->has('cancel')) {
                    wp_redirect($redirect_to);
                    exit();
                }

                $file = $this->request->file('file_name');
                if ($file === null) {
                    $this->settings->errmsg = 'You did not select a file to upload';
                    unset($query_entries, $query_competitions);
                    return;
                }

                if (!$file->isValid()) {
                    $this->settings->errmsg = $file->getErrorMessage();
                    unset($query_entries, $query_competitions);
                    return;
                }

                // Verify that the uploaded image is a JPEG
                $uploaded_file_name = $file->getRealPath();
                $uploaded_file_info = getimagesize($uploaded_file_name);
                if ($uploaded_file_info === false || $uploaded_file_info[2] != IMAGETYPE_JPEG) {
                    $this->settings->errmsg = "Submitted file is not a JPEG image.  Please try again.<br>Click the Browse button to select a .jpg image file before clicking Submit";
                    unset($query_entries, $query_competitions);
                    return;
                }
                if (!$this->request->has('title')) {
                    $this->settings->errmsg = 'Please enter your image title in the Title field.';
                    unset($query_entries, $query_competitions);
                    return;
                }

                // Retrieve and parse the selected competition cookie
                if ($this->request->hasCookie('RPS_MyEntries')) {
                    list ($this->settings->comp_date, $this->settings->classification, $this->settings->medium) = explode("|", $this->request->cookie('RPS_MyEntries'));
                } else {
                    $this->settings->errmsg = "Upload Form Error<br>The Selected_Competition cookie is not set.";
                    unset($query_entries, $query_competitions);
                    return;
                }

                $recs = $query_competitions->getCompetitionByDateClassMedium($this->settings->comp_date, $this->settings->classification, $this->settings->medium);
                if ($recs) {
                    $comp_id = $recs['ID'];
                    $max_entries = $recs['Max_Entries'];
                } else {
                    $d = $this->comp_date;
                    $c = $this->classification;
                    $m = $this->medium;
                    $this->settings->errmsg = "Upload Form Error<br>Competition $d/$c/$m not found in database<br>";
                    unset($query_entries, $query_competitions);
                    return;
                }

                // Prepare the title and client file name for storing in the database
                $title = trim($this->request->input('title'));
                $client_file_name = $file->getClientOriginalName();

                // Before we go any further, make sure the title is not a duplicate of
                // an entry already submitted to this competition. Dupliacte title result in duplicate
                // file names on the server
                if ($query_entries->checkDuplicateTitle($comp_id, $title, get_current_user_id())) {
                    $this->settings->errmsg = "You have already submitted an entry with a title of \"" . $title . "\" in this competition<br>Please submit your entry again with a different title.";
                    unset($query_entries, $query_competitions);
                    return;
                }

                // Do a final check that the user hasn't exceeded the maximum images per competition.
                // If we don't check this at the last minute it may be possible to exceed the
                // maximum images per competition by having two upload windows open simultaneously.
                $max_per_id = $query_entries->countEntriesByCompetitionId($comp_id, get_current_user_id());
                if ($max_per_id >= $max_entries) {
                    $this->settings->errmsg = "You have already submitted the maximum of $max_entries entries into this competition<br>You must Remove an image before you can submit another";
                    unset($query_entries, $query_competitions);
                    return;
                }

                $max_per_date = $query_entries->countEntriesByCompetitionDate($this->settings->comp_date, get_current_user_id());
                if ($max_per_date >= $this->settings->club_max_entries_per_member_per_date) {
                    $x = $this->settings->club_max_entries_per_member_per_date;
                    $this->settings->errmsg = "You have already submitted the maximum of $x entries for this competition date<br>You must Remove an image before you can submit another";
                    unset($query_entries, $query_competitions);
                    return;
                }

                // Move the file to its final location
                $comp_date = $this->settings->comp_date;
                $classification = $this->settings->classification;
                $medium = $this->settings->medium;
                $path = $this->request->server('DOCUMENT_ROOT') . '/Digital_Competitions/' . $comp_date . '_' . $classification . '_' . $medium;

                $user = wp_get_current_user();
                $dest_name = sanitize_file_name($title) . '+' . $user->user_login . '+' . filemtime($uploaded_file_name);
                $full_path = $path . '/' . $dest_name;
                // Need to create the destination folder?
                if (!is_dir($path)) {
                    mkdir($path, 0755);
                }

                // If the .jpg file is too big resize it
                if ($uploaded_file_info[0] > Constants::IMAGE_MAX_WIDTH_ENTRY || $uploaded_file_info[1] > Constants::IMAGE_MAX_HEIGHT_ENTRY) {

                    // Resize the image and deposit it in the destination directory
                    $this->core->rpsResizeImage($uploaded_file_name, $full_path . '.jpg', 'FULL');
                    $resized = 1;
                } else {
                    // The uploaded image does not need to be resized so just move it to the destination directory
                    $resized = 0;
                    try {
                        $file->move($path, $dest_name . '.jpg');
                    } catch (FileException $e) {
                        $this->settings->errmsg = $e->getMessage();
                        unset($query_entries, $query_competitions);
                        return;
                    }
                }
                $server_file_name = str_replace($this->request->server('DOCUMENT_ROOT'), '', $full_path . '.jpg');
                $data = array('Competition_ID' => $comp_id, 'Title' => $title, 'Client_File_Name' => $client_file_name, 'Server_File_Name' => $server_file_name);
                $_result = $query_entries->addEntry($data, get_current_user_id());
                if ($_result === false) {
                    $this->settings->errmsg = "Failed to INSERT entry record into database";
                    unset($query_entries, $query_competitions);
                    return;
                }
                $query = build_query(array('resized' => $resized));
                wp_redirect($redirect_to . '/?' . $query);
                exit();
            }
        }
        unset($query_entries, $query_competitions);
    }

    public function actionPreHeaderRpsBanquetEntries()
    {
        global $post;
        $query_entries = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->rpsdb);

        if (is_object($post) && $post->post_title == 'Banquet Entries') {
            if ($this->request->isMethod('POST')) {
                $redirect_to = $this->request->input('wp_get_referer');

                // Just return if user clicked Cancel
                if ($this->request->has('cancel')) {
                    wp_redirect($redirect_to);
                    exit();
                }

                if ($this->request->has('submit')) {
                    if ($this->request->has('allentries')) {
                        $all_entries = explode(',', $this->request->input('allentries'));
                        foreach ($all_entries as $entry) {
                            $query_entries->deleteEntry($entry);
                        }
                    }
                    $entries = $this->request->input('entry_id', array());
                    foreach ($entries as $entry_id) {
                        $entry = $query_entries->getEntryById($entry_id, OBJECT);
                        $competition = $query_competitions->getCompetitionByID($entry->Competition_ID);
                        $banquet_ids = explode(',', $this->request->input('banquetids'));
                        foreach ($banquet_ids as $banquet_id) {
                            $banquet_record = $query_competitions->getCompetitionByID($banquet_id);
                            if ($competition->Medium == $banquet_record->Medium && $competition->Classification == $banquet_record->Classification) {
                                // Move the file to its final location
                                $comp_date = strtok($banquet_record->Competition_Date, ' ');
                                $classification = $banquet_record->Classification;
                                $medium = $banquet_record->Medium;
                                $path = $this->request->server('DOCUMENT_ROOT') . '/Digital_Competitions/' . $comp_date . '_' . $classification . '_' . $medium;
                                if (!is_dir($path)) {
                                    mkdir($path, 0755);
                                }

                                $file_name = pathinfo($entry->Server_File_Name);
                                $server_file_name = $path . '/' . $file_name['basename'];
                                $original_filename = html_entity_decode($this->request->server('DOCUMENT_ROOT') . $entry->Server_File_Name, ENT_QUOTES, get_bloginfo('charset'));
                                // Need to create the destination folder?
                                copy($original_filename, $server_file_name);
                                $data = array('Competition_ID' => $banquet_record->ID, 'Title' => $entry->Title, 'Client_File_Name' => $entry->Client_File_Name, 'Server_File_Name' => $server_file_name);
                                $this->rpsdb->addEntry($data);
                            }
                        }
                    }
                }
            }
        }
        unset($query_entries, $query_competitions);
    }

    // ----- Private Functions --------

    /**
     * Delete competition entries
     *
     * @param array $entries
     *            Array of entries ID to delete.
     */
    private function deleteCompetitionEntries($entries)
    {
        $query_entries = new QueryEntries($this->rpsdb);

        if (is_array($entries)) {
            foreach ($entries as $id) {

                $recs = $query_entries->getEntryById($id, OBJECT);
                if ($recs == false) {
                    $this->settings->errmsg = sprintf("<b>Failed to SELECT competition entry with ID %s from database</b><br>", $id);
                } else {

                    $server_file_name = $this->request->server('DOCUMENT_ROOT') . $recs->Server_File_Name;
                    // Delete the record from the database
                    $result = $query_entries->deleteEntry($id);
                    if ($result === false) {
                        $this->settings->errmsg = sprintf("<b>Failed to DELETE competition entry %s from database</b><br>");
                    } else {

                        // Delete the file from the server file system
                        if (file_exists($server_file_name)) {
                            unlink($server_file_name);
                        }
                        // Delete any thumbnails of this image
                        $ext = ".jpg";
                        $comp_date = $this->settings->comp_date;
                        $classification = $this->settings->classification;
                        $medium = $this->settings->medium;
                        $path = $this->request->server('DOCUMENT_ROOT') . '/Digital_Competitions/' . $comp_date . '_' . $classification . '_' . $medium;

                        $old_file_parts = pathinfo($server_file_name);
                        $old_file_name = $old_file_parts['filename'];

                        if (is_dir($path . "/thumbnails")) {
                            $thumb_base_name = $path . "/thumbnails/" . $old_file_name;
                            // Get all the matching thumbnail files
                            $thumbnails = glob("$thumb_base_name*");
                            // Iterate through the list of matching thumbnails and delete each one
                            if (is_array($thumbnails) && count($thumbnails) > 0) {
                                foreach ($thumbnails as $thumb) {
                                    unlink($thumb);
                                }
                            }
                        }
                    }
                }
            }
        }
        unset($query_entries);
    }

    /**
     * Select the list of open competitions for this member's classification and validate the currently selected competition against that list.
     *
     * @param string $date
     * @param unknown $med
     * @return boolean
     */
    private function validateSelectedComp($date, $med)
    {
        $query_competitions = new QueryCompetitions($this->rpsdb);
        $open_competitions = $query_competitions->getOpenCompetitions(get_current_user_id(), $this->settings->medium_subset);

        if (empty($open_competitions)) {
            unset($query_competitions);
            return false;
        }

        // Read the competition attributes into a series of arrays
        $index = 0;
        $date_index = -1;
        $medium_index = -1;
        foreach ($open_competitions as $recs) {
            if ($recs['Theme'] == 'Annual Banquet') {
                continue;
            }
            // Append this competition to the arrays
            $dateParts = explode(" ", $recs['Competition_Date']);
            $this->_open_comp_date[$index] = $dateParts[0];
            $this->_open_comp_medium[$index] = $recs['Medium'];
            $this->_open_comp_class[$index] = $recs['Classification'];
            $this->_open_comp_theme[$index] = $recs['Theme'];
            // If this is the first competition whose date matches the currently selected
            // competition date, save its array index
            if ($this->_open_comp_date[$index] == $date) {
                if ($date_index < 0) {
                    $date_index = $index;
                }
                // If this competition matches the date AND the medium of the currently selected
                // competition, save its array index
                if ($this->_open_comp_medium[$index] == $med) {
                    if ($medium_index < 0) {
                        $medium_index = $index;
                    }
                }
            }
            $index += 1;
        }

        // If date and medium both matched, then the currently selected competition is in the
        // list of open competitions for this member
        if ($medium_index >= 0) {
            $index = $medium_index;

            // If the date matched but the medium did not, then there are valid open competitions on
            // the selected date for this member, but not in the currently selected medium. In this
            // case set the medium to the first one in the list for the selected date.
        } elseif ($medium_index < 0 && $date_index >= 0) {
            $index = $date_index;

            // If neither the date or medium matched, simply select the first open competition in the
            // list.
        } else {
            $index = 0;
        }
        // Establish the (possibly adjusted) selected competition
        $this->settings->open_comp_date = $this->_open_comp_date;
        $this->settings->open_comp_medium = $this->_open_comp_medium;
        $this->settings->open_comp_theme = $this->_open_comp_theme;
        $this->settings->open_comp_class = $this->_open_comp_class;
        $this->settings->comp_date = $this->_open_comp_date[$index];
        $this->settings->classification = $this->_open_comp_class[$index];
        $this->settings->medium = $this->_open_comp_medium[$index];
        // Save the currently selected competition in a cookie
        $hour = time() + (2 * 3600);
        $url = parse_url(get_bloginfo('url'));
        setcookie("RPS_MyEntries", $this->settings->comp_date . "|" . $this->settings->classification . "|" . $this->settings->medium, $hour, '/', $url['host']);

        unset($query_competitions);
        return true;
    }
}
