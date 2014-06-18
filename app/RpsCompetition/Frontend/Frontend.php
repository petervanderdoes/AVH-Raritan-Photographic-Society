<?php
namespace RpsCompetition\Frontend;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use RpsCompetition\Api\Client;
use RpsCompetition\Common\Core;
use RpsCompetition\Constants;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Settings;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class Frontend
{
    /**
     * @var Core
     */
    private $core;
    /**
     * @var Request
     */
    private $request;
    /**
     * @var RpsDb
     */
    private $rpsdb;
    /**
     * @var Settings
     */
    private $settings;

    /**
     * PHP5 Constructor
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->settings = $container->make('RpsCompetition\Settings');
        $this->core = $container->make('RpsCompetition\Common\Core');
        $this->rpsdb = $container->make('RpsCompetition\Db\RpsDb');
        $this->request = $container->make('Illuminate\Http\Request');
        $this->container = $container;
        $this->options = $container->make('RpsCompetition\Options\General', array($this->settings));

        $this->settings->errmsg = '';

        // The actions are in order as how WordPress executes them
        add_action('after_setup_theme', array($this, 'actionAfterThemeSetup'), 14);
        add_action('init', array($this, 'actionInit'));
        if ($this->request->isMethod('POST')) {
            add_action('suffusion_before_post', array($this, 'actionHandleHttpPostRpsMyEntries'));
            add_action('suffusion_before_post', array($this, 'actionHandleHttpPostRpsEditTitle'));
            add_action('suffusion_before_post', array($this, 'actionHandleHttpPostRpsUploadEntry'));
            add_action('suffusion_before_post', array($this, 'actionHandleHttpPostRpsBanquetEntries'));
        }
        add_action('template_redirect', array($this, 'actionTemplateRedirectRpsWindowsClient'));
    }

    /**
     * Implement actions.
     * This method is called by the action after_setup_theme and is used to setup:
     *  - New actions
     *
     * @version  GIT: $Id$
     * @internal Hook: after_setup_theme
     */
    public function actionAfterThemeSetup()
    {
        add_action('rps_showcase', array($this, 'actionShowcaseCompetitionThumbnails'));
    }

    /**
     * Handle POST request for the Banquet Entries.
     * This method handles the POST request generated on the page for Banquet Entries
     * The action is called from the theme!
     *
     * @uses     \RpsCompetition\Db\QueryEntries
     * @uses     \RpsCompetition\Db\QueryCompetitions
     * @see      Shortcodes::displayBanquetCurrentUser
     * @internal Hook: suffusion_before_post
     */
    public function actionHandleHttpPostRpsBanquetEntries()
    {
        global $post;
        $query_entries = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->rpsdb);

        if (is_object($post) && $post->post_title == 'Banquet Entries') {
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
                $entries = (array) $this->request->input('entry_id', array());
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
                            $path = '/Digital_Competitions/' . $comp_date . '_' . $classification . '_' . $medium;
                            if (!is_dir($this->request->server('DOCUMENT_ROOT') . $path)) {
                                mkdir($this->request->server('DOCUMENT_ROOT') . $path, 0755);
                            }

                            $file_info = pathinfo($entry->Server_File_Name);
                            $new_file_name = $path . '/' . $file_info['basename'];
                            $original_filename = html_entity_decode($this->request->server('DOCUMENT_ROOT') . $entry->Server_File_Name, ENT_QUOTES, get_bloginfo('charset'));
                            // Need to create the destination folder?
                            copy($original_filename, $this->request->server('DOCUMENT_ROOT') . $new_file_name);
                            $data = array('Competition_ID' => $banquet_record->ID, 'Title' => $entry->Title, 'Client_File_Name' => $entry->Client_File_Name, 'Server_File_Name' => $new_file_name);
                            $this->rpsdb->addEntry($data);
                        }
                    }
                }
            }
        }
        unset($query_entries, $query_competitions);
    }

    /**
     * Handle POST request for the editing the title of a photo.
     * This method handles the POST request generated on the page Edit Title
     * The action is called from the theme!
     *
     * @uses     \RpsCompetition\Db\QueryEntries
     * @uses     \RpsCompetition\Db\QueryCompetitions
     * @see      Shortcodes::displayEditTitle
     * @internal Hook: suffusion_before_post
     */
    public function actionHandleHttpPostRpsEditTitle()
    {
        global $post;

        $query_entries = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->rpsdb);
        if (is_object($post) && $post->ID == 75) {
            $redirect_to = $this->request->input('wp_get_referer');
            $this->_medium_subset = $this->request->input('m');
            $this->_entry_id = $this->request->input('id');

            // Just return to the My Images page is the user clicked Cancel
            if ($this->request->has('cancel')) {

                wp_redirect($redirect_to);
                exit();
            }

            // makes sure they filled in the title field
            if (!$this->request->has('new_title')) {
                $this->settings->errmsg = 'You must provide an image title.<br><br>';
            } else {
                $server_file_name = $this->request->input('server_file_name');
                $new_title = trim($this->request->input('new_title'));
                if (get_magic_quotes_gpc()) {
                    $server_file_name = stripslashes($this->request->input('server_file_name'));
                    $new_title = stripslashes(trim($this->request->input('new_title')));
                }

                $recs = $query_competitions->getCompetitionByEntryId($this->_entry_id);
                if ($recs == null) {
                    wp_die("Failed to SELECT competition for entry ID: " . $this->_entry_id);
                }

                $date_parts = explode(" ", $recs->Competition_Date);
                $comp_date = $date_parts[0];
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
        unset($query_entries);
    }

    /**
     * Handle POST request for editing the entries of a user.
     * This method handles the POST request generated on the page for editing entries
     * The action is called from the theme!
     *
     * @uses     \RpsCompetition\Db\QueryCompetitions
     * @see      Shortcodes::displayMyEntries
     * @internal Hook: suffusion_before_post
     */
    public function actionHandleHttpPostRpsMyEntries()
    {
        global $post;
        $query_competitions = new QueryCompetitions($this->rpsdb);

        if (is_object($post) && ($post->ID == 56 || $post->ID == 58)) {
            $this->settings->errmsg = '';

            $page = explode('-', $post->post_name);
            $medium_subset = $page[1];
            if ($this->request->has('submit_control')) {
                // @TODO Nonce check

                $comp_date = $this->request->input('comp_date');
                $classification = $this->request->input('classification');
                $medium = $this->request->input('medium');
                $t = time() + (2 * 24 * 3600);
                $url = parse_url(get_bloginfo('url'));
                setcookie("RPS_MyEntries", $comp_date . "|" . $classification . "|" . $medium, $t, '/', $url['host']);

                $entry_array = $this->request->input('EntryID', null);

                switch ($this->request->input('submit_control')) {
                    case 'add':
                        if (!$query_competitions->checkCompetitionClosed($comp_date, $classification, $medium)) {
                            $_query = array('m' => $medium_subset);
                            $_query = build_query($_query);
                            $loc = '/member/upload-image/?' . $_query;
                            wp_redirect($loc);
                        }
                        break;

                    case 'edit':
                        if (!$query_competitions->checkCompetitionClosed($comp_date, $classification, $medium)) {
                            if (is_array($entry_array)) {
                                foreach ($entry_array as $id) {
                                    // @TODO Add Nonce
                                    $_query = array('id' => $id, 'm' => $medium_subset);
                                    $_query = build_query($_query);
                                    $loc = '/member/edit-title/?' . $_query;
                                    wp_redirect($loc);
                                }
                            }
                        }
                        break;

                    case 'delete':
                        if (!$query_competitions->checkCompetitionClosed($comp_date, $classification, $medium)) {
                            if ($entry_array !== null) {
                                $this->deleteCompetitionEntries($entry_array);
                            }
                        }
                        break;
                }
            }
        }
        unset($query_competitions);
    }

    /**
     * Handle POST request for uploading a photo.
     * This method handles the POST request generated when uploading a photo
     * The action is called from the theme!
     *
     * @uses     \RpsCompetition\Db\QueryEntries
     * @uses     \RpsCompetition\Db\QueryCompetitions
     * @see      Shortcodes::displayUploadEntry
     * @internal Hook: suffusion_before_post
     */
    public function actionHandleHttpPostRpsUploadEntry()
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

                $recs = $query_competitions->getCompetitionByDateClassMedium($this->settings->comp_date, $this->settings->classification, $this->settings->medium, ARRAY_A);
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
                if (get_magic_quotes_gpc()) {
                    $title = stripslashes(trim($this->request->input('title')));
                } else {
                    $title = trim($this->request->input('title'));
                }
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
                $result = $query_entries->addEntry($data, get_current_user_id());
                if ($result === false) {
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

    /**
     * Setup all that is needed to run the plugin.
     * This method runs during the init hook and you can basically add everything that needs
     * to be setup for plugin.
     * - Shortcodes
     * - User meta information concerning their classification
     *
     * @uses     \RpsCompetition\Db\QueryCompetitions
     * @internal Hook: init
     */
    public function actionInit()
    {

        $this->setupShortcodes();

        $query_competitions = new QueryCompetitions($this->rpsdb);
        $query_competitions->setAllPastCompetitionsClose();

        $this->setupUserMeta();

        unset($query_competitions);
    }

    /**
     * Display the showcase on the front page.
     * This will display the showcase as used on the front page.
     *
     * @uses     \RpsCompetition\Db\QueryMiscellaneous
     * @see      actionAfterThemeSetup
     * @internal Hook: rps_showcase
     *
     * @param null $foo
     */
    public function actionShowcaseCompetitionThumbnails($foo)
    {
        if (is_front_page()) {
            $query_miscellaneous = new QueryMiscellaneous($this->rpsdb);

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
                $title = $recs->Title;
                $last_name = $recs->LastName;
                $first_name = $recs->FirstName;

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

    /**
     * Handles the requests by the RPS Windows Client
     *
     * @uses     \RpsCompetition\Api\Client
     * @internal Hook: template_redirect
     */
    public function actionTemplateRedirectRpsWindowsClient()
    {
        if ($this->request->has('rpswinclient')) {

            $api_client = new Client($this->core);

            define('DONOTCACHEPAGE', true);
            global $hyper_cache_stop;
            $hyper_cache_stop = true;
            add_filter('w3tc_can_print_comment', '__return_false');

            // Properties of the logged in user
            status_header(200);
            switch ($this->request->input('rpswinclient')) {
                case 'getcompdate':
                    $api_client->sendXmlCompetitionDates($this->request);
                    break;
                case 'download':
                    $api_client->sendCompetitions($this->request);
                    break;
                case 'uploadscore':
                    $api_client->doUploadScore($this->request);
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Delete competition entries
     *
     * @uses  \RpsCompetition\Db\QueryEntries
     *
     * @param array $entries Array of entries ID to delete.
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
     * Setup shortcodes.
     * Setup all the need shortcodes.
     *
     * @uses  \RpsCompetition\Frontend\Shortcodes
     */
    private function setupShortcodes()
    {
        /** @var \RpsCompetition\Frontend\Shortcodes $shortcode */
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
    }

    /**
     * Setup the needed user meta information.

     */
    private function setupUserMeta()
    {
        $user_id = get_current_user_id();
        $x = get_user_meta($user_id, 'rps_class_bw', true);
        if (empty($x)) {
            update_user_meta($user_id, "rps_class_bw", 'beginner');
            update_user_meta($user_id, "rps_class_color", 'beginner');
            update_user_meta($user_id, "rps_class_print_bw", 'beginner');
            update_user_meta($user_id, "rps_class_print_color", 'beginner');
        }
    }
}
