<?php
namespace RpsCompetition\Frontend;

use RpsCompetition\Settings;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Db\RPSPDO;
use RpsCompetition\Common\Core;
use Illuminate\Http\Request;
use PDO;
use DOMDocument;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

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
        add_action('template_redirect', array($this, 'actionTemplateRedirectRpsWindowsClient'));
    }

    public function actionAfterThemeSetup()
    {
        add_action('rps_showcase', array($this, 'actionShowcaseCompetitionThumbnails'));
    }

    public function actionInit()
    {
        /* @var $shortcode \RpsCompetition\Frontend\Shortcodes */
        $shortcode = $this->container->make('RpsCompetition\Frontend\Shortcodes');
        $shortcode->register('rps_category_winners', 'displayCategoryWinners');
        $shortcode->register('rps_monthly_winners', 'displayMonthlyWinners');
        $shortcode->register('rps_scores_current_user', 'displayScoresCurrentUser');
        $shortcode->register('rps_all_scores', 'displayAllScores');
        $shortcode->register('rps_my_entries', 'displayMyEntries');
        $shortcode->register('rps_edit_title', 'displayEditTitle');
        $shortcode->register('rps_upload_image', 'displayUploadEntry');
        $shortcode->register('rps_email', 'displayEmail');
        $shortcode->register('rps_person_winners', 'displayPersonWinners');
        $userID = get_current_user_id();
        $this->rpsdb->setUserId($userID);
        $this->rpsdb->setCompetitionClose();

        $x = get_user_meta($userID, 'rps_class_bw', true);
        if (empty($x)) {
            update_user_meta($userID, "rps_class_bw", 'beginner');
            update_user_meta($userID, "rps_class_color", 'beginner');
            update_user_meta($userID, "rps_class_print_bw", 'beginner');
            update_user_meta($userID, "rps_class_print_color", 'beginner');
        }
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
                    $this->sendXmlCompetitionDates();
                    break;
                case 'download':
                    $this->sendCompetitions();
                    break;
                case 'uploadscore':
                    $this->doUploadScore();
                    break;
                default:
                    break;
            }
        }
    }

    public function actionShowcaseCompetitionThumbnails($ctr)
    {
        if (is_front_page()) {
            $image = array();
            $seasons = $this->rpsdb->getSeasonList();
            $from_season = $seasons[count($seasons) - 3];

            $season_start_year = substr($from_season, 0, 4);
            $season = sprintf("%d-%02s-%02s", $season_start_year, $this->settings->club_season_start_month_num, 1);

            echo '<div class="rps-sc-tile suf-tile-1c entry-content bottom">';

            echo '<div class="suf-gradient suf-tile-topmost">';
            echo '<h3>Showcase</h3>';
            echo '</div>';

            echo '<div class="gallery gallery-size-150">';
            echo '<ul class="gallery-row">';
            $entries = $this->rpsdb->getEightsAndHigher('', $season);
            $images = array_rand($entries, 5);

            foreach ($images as $key) {
                $recs = $entries[$key];
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
                echo '<li class="gallery-item">';
                echo '<div class="gallery-item-content">';
                echo '<div class="gallery-item-content-image">';
                echo '	<a href="' . $this->core->rpsGetThumbnailUrl($recs, 800) . '" rel="rps-showcase" title="' . $title . ' by ' . $first_name . ' ' . $last_name . '">';
                echo '	<img src="' . $this->core->rpsGetThumbnailUrl($recs, 150) . '" /></a>';
                echo "</div>";
                echo "</div>";

                echo '</li>' . "\n";
            }
            echo '</ul>';
            echo '</div>';
            echo '</div>';
        }
    }

    public function actionPreHeaderRpsMyEntries()
    {
        global $post;

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
                        if (!$this->rpsdb->getCompetionClosed()) {
                            $_query = array('m' => $this->settings->medium_subset);
                            $_query = build_query($_query);
                            $loc = '/member/upload-image/?' . $_query;
                            wp_redirect($loc);
                        }
                        break;

                    case 'edit':
                        if (!$this->rpsdb->getCompetionClosed()) {
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
                        if (!$this->rpsdb->getCompetionClosed()) {
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
    }

    /**
     * Handle $_POST Edit Title
     */
    public function actionPreHeaderRpsEditTitle()
    {
        global $post;

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
                    $recs = $this->rpsdb->getCompetitionByID($this->_entry_id);
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
                    $new_file_name_noext = sanitize_file_name($new_title) . '+' . $current_user->user_login . '+' . filemtime($this->request->server('DOCUMENT_ROOT').$server_file_name);
                    $new_file_name = $new_file_name_noext . $ext;
                    if (!$this->core->renameImageFile($path, $old_file_name, $new_file_name_noext, $ext)) {
                        die("<b>Failed to rename image file</b><br>" . "Path: $path<br>Old Name: $old_file_name<br>" . "New Name: $new_file_name_noext");
                    }

                    // Update the Title and File Name in the database
                    $_result = $this->rpsdb->updateEntriesTitle($new_title, $path . '/' . $new_file_name, $this->_entry_id);
                    if ($_result === false) {
                        wp_die("Failed to UPDATE entry record from database");
                    }

                    $redirect_to = $this->request->input('wp_get_referer');
                    wp_redirect($redirect_to);
                    exit();
                }
            }
        }
    }

    public function actionPreHeaderRpsUploadEntry()
    {
        global $post;

        if (is_object($post) && $post->ID == 89) {
            if ($this->request->has('post')) {
                $redirect_to = $this->request->input('wp_get_referer');

                // Just return if user clicked Cancel
                if ($this->request->has('cancel')) {
                    wp_redirect($redirect_to);
                    exit();
                }

                // If we exceed the post_max_size the $_POST and $_FILES are empty
                if ($this->request->isMethod('POST') && $this->request->request->count() == 0 && $this->request->files->count() == 0 && $this->request->server('CONTENT_LENGTH') > 0) {
                    $this->settings->errmsg = "Your submitted file failed to transfer successfully.<br>The submitted file is " . sprintf("%dMB", $this->request->server('CONTENT_LENGTH') / 1024 / 1024) . " which exceeds the maximum file size of " . ini_get('post_max_size') . "B<br>" . "Click <a href=\"/competitions/resize_digital_images.html#Set_File_Size\">here</a> for instructions on setting the overall size of your file on disk.";
                    return;
                }

                if (!$this->request->hasFile('file_name')) {
                    $this->settings->errmsg = 'You did not select a file to upload';
                    return;
                }
                $file = $this->request->file('file_name');

                // Verify that the uploaded image is a JPEG
                $uploaded_file_name = $file->getRealPath();
                $size_info = getimagesize($uploaded_file_name);
                if ($file->getClientMimeType() != 'image/jpeg') {
                    $this->settings->errmsg = "Submitted file is not a JPEG image.  Please try again.<br>Click the Browse button to select a .jpg image file before clicking Submit";
                    return;
                }
                if (!$this->checkUploadEntry()) {
                    return;
                }
                // Retrieve and parse the selected competition cookie
                if ($this->request->hasCookie('RPS_MyEntries')) {
                    list ($this->settings->comp_date, $this->settings->classification, $this->settings->medium) = explode("|", $this->request->cookie('RPS_MyEntries'));
                } else {
                    $this->settings->errmsg = "Upload Form Error<br>The Selected_Competition cookie is not set.";
                    return;
                }

                $recs = $this->rpsdb->getIdmaxEntries();
                if ($recs) {
                    $comp_id = $recs['ID'];
                    $max_entries = $recs['Max_Entries'];
                } else {
                    $d = $this->comp_date;
                    $c = $this->classification;
                    $m = $this->medium;
                    $this->settings->errmsg = "Upload Form Error<br>Competition $d/$c/$m not found in database<br>";
                    return;
                }

                // Prepare the title and client file name for storing in the database
                $title = trim($this->request->input('title'));
                $client_file_name = $file->getClientOriginalName();

                // Before we go any further, make sure the title is not a duplicate of
                // an entry already submitted to this competition. Dupliacte title result in duplicate
                // file names on the server
                if ($this->rpsdb->checkDuplicateTitle($comp_id, $title)) {
                    $this->settings->errmsg = "You have already submitted an entry with a title of \"" . $title . "\" in this competition<br>Please submit your entry again with a different title.";
                    return;
                }

                // Do a final check that the user hasn't exceeded the maximum images per competition.
                // If we don't check this at the last minute it may be possible to exceed the
                // maximum images per competition by having two upload windows open simultaneously.
                $max_per_id = $this->rpsdb->checkMaxEntriesOnId($comp_id);
                if ($max_per_id >= $max_entries) {
                    $this->settings->errmsg = "You have already submitted the maximum of $max_entries entries into this competition<br>You must Remove an image before you can submit another";
                    return;
                }

                $max_per_date = $this->rpsdb->checkMaxEntriesOnDate();
                if ($max_per_date >= $this->settings->club_max_entries_per_member_per_date) {
                    $x = $this->settings->club_max_entries_per_member_per_date;
                    $this->settings->errmsg = "You have already submitted the maximum of $x entries for this competition date<br>You must Remove an image before you can submit another";
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
                if ($size_info[0] > $this->settings->max_width_entry || $size_info[1] > $this->settings->max_height_entry) {
                    // If this is a landscape image and the aspect ratio is less than the aspect ratio of the projector
                    if ($size_info[0] > $size_info[1] && $size_info[0] / $size_info[1] < $this->settings->max_width_entry / $this->settings->max_height_entry) {
                        // Set the maximum width to ensure the height does not exceed the maximum height
                        $size = $this->settings->max_height_entry * $size_info[0] / $size_info[1];
                    } else {
                        // if its landscape and the aspect ratio is greater than the projector
                        if ($size_info[0] > $size_info[1]) {
                            // Set the maximum width to the width of the projector
                            $size = $this->settings->max_width_entry;

                            // If its a portrait image
                        } else {
                            // Set the maximum height to the height of the projector
                            $size = $this->settings->max_height_entry;
                        }
                    }
                    // Resize the image and deposit it in the destination directory
                    $this->core->rpsResizeImage($uploaded_file_name, $full_path . '.jpg', $size, 95, '');
                    // if (! $this->core->rpsResizeImage($uploaded_file_name, $full_path . '.jpg', $size, 95, ''));
                    // {
                    // $this->settings->errmsg = "There is a problem resizing the picture for the use of the projector.";
                    // return;
                    // }
                    $resized = 1;

                    // The uploaded image does not need to be resized so just move it to the destination directory
                } else {
                    $resized = 0;
                    try {
                        $file->move($path, $dest_name . '.jpg');
                    } catch (FileException $e) {
                        $this->settings->errmsg = $e->getMessage();
                        return;
                    }
                }
                $server_file_name = str_replace($this->request->server('DOCUMENT_ROOT'), '', $full_path . '.jpg');
                $data = array('Competition_ID' => $comp_id, 'Title' => $title, 'Client_File_Name' => $client_file_name, 'Server_File_Name' => $server_file_name);
                $_result = $this->rpsdb->addEntry($data);
                if ($_result === false) {
                    $this->settings->errmsg = "Failed to INSERT entry record into database";
                    return;
                }
                $query = build_query(array('resized' => $resized));
                wp_redirect($redirect_to . '/?' . $query);
                exit();
            }
        }
    }

    // ----- Private Functions --------

    /**
     * Create a XML File with the competition dates
     */
    private function sendXmlCompetitionDates()
    {
        // Connect to the Database
        try {
            $db = new RPSPDO();
        } catch (\PDOException $e) {
            $this->doRESTError("Failed to obtain database handle " . $e->getMessage());
            die($e->getMessage());
        }

        try {
            $select = "SELECT DISTINCT(Competition_Date) FROM competitions ";
            if ($this->request->has('closed') || $this->request->has('scored')) {
                $where = "WHERE";
                if ($this->request->has('closed')) {
                    $where .= " Closed=:closed";
                }
                if ($this->request->has('scored')) {
                    $where .= " AND Scored=:scored";
                }
            } else {
                $where .= " Competition_Date >= CURDATE()";
            }

            $sth = $db->prepare($select . $where);
            if ($this->request->has('closed')) {
                $_closed = $this->request->input('closed');
                $sth->bindParam(':closed', $_closed, \PDO::PARAM_STR, 1);
            }
            if ($this->request->has('scored')) {
                $_scored = $this->request->input('scored');
                $sth->bindParam(':scored', $_scored, \PDO::PARAM_STR, 1);
            }
            $sth->execute();
        } catch (\PDOException $e) {
            $this->doRESTError("Failed to SELECT list of competitions from database - " . $e->getMessage());
            die($e->getMessage());
        }

        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('rsp');
        $dom->appendChild($root);
        $stat = $dom->createAttribute("stat");
        $root->appendChild($stat);
        $value = $dom->CreateTextNode("ok");
        $stat->appendChild($value);
        $recs = $sth->fetch(\PDO::FETCH_ASSOC);
        while ($recs != false) {
            $dateParts = explode(" ", $recs['Competition_Date']);
            $comp_date = $root->appendChild($dom->createElement('Competition_Date'));
            $comp_date->appendChild($dom->createTextNode($dateParts[0]));
            $recs = $sth->fetch(\PDO::FETCH_ASSOC);
        }
        echo $dom->saveXML();
        $db = null;
        die();
    }

    /**
     * Handles request by client to download images for a particular date,
     */
    private function sendCompetitions()
    {
        $username = $this->request->input('username');
        $password = $this->request->input('password');
        try {
            $db = new RPSPDO();
        } catch (\PDOException $e) {
            $this->doRESTError("Failed to obtain database handle " . $e->getMessage());
            die($e->getMessage());
        }
        if ($db !== false) {
            $user = wp_authenticate($username, $password);
            if (is_wp_error($user)) {
                $a = strip_tags($user->get_error_message());
                $this->doRESTError($a);
                die();
            }
            // @todo Check if the user has the role needed.
            $this->sendXmlCompetitions($db, $this->request->input('medium'), $this->request->input('comp_date'));
        }
        die();
    }

    /**
     * Create a XML file for the client with information about images for a particular date
     *
     * @param object $db
     *            Connection to the RPS Database
     * @param string $requested_medium
     *            Which competition medium to use, either digital or print
     * @param string $comp_date
     *            The competition date
     */
    private function sendXmlCompetitions($db, $requested_medium, $comp_date)
    {

        /* @var $db RPSPDO */
        // Start building the XML response
        $dom = new \DOMDocument('1.0');
        // Create the root node
        $node = $dom->CreateElement('rsp');
        $node->SetAttribute('stat', 'ok');
        $rsp = $dom->AppendChild($node);

        $medium_clause = '';
        if (!(empty($requested_medium))) {
            $medium_clause = ($requested_medium == "prints") ? " AND Medium like '%Prints' " : " AND Medium like '%Digital' ";
        }
        $sql = "SELECT ID, Competition_Date, Theme, Medium, Classification
        FROM competitions
        WHERE Competition_Date = DATE(:compdate) AND Closed = 'Y' $medium_clause
        ORDER BY Medium, Classification";
        try {
            $sth_competitions = $db->prepare($sql);
            $sth_competitions->bindParam(':compdate', $comp_date);
            $sth_competitions->execute();
        } catch (\Exception $e) {
            $this->doRESTError("Failed to SELECT competition records with date = " . $comp_date . " from database - " . $e->getMessage());
            die();
        }
        // Create a Competitions node
        $xml_competions = $rsp->AppendChild($dom->CreateElement('Competitions'));
        // Iterate through all the matching Competitions and create corresponding Competition nodes
        $record_competitions = $sth_competitions->fetch(\PDO::FETCH_ASSOC);
        while ($record_competitions !== false) {
            $comp_id = $record_competitions['ID'];
            $dateParts = explode(" ", $record_competitions['Competition_Date']);
            $date = $dateParts[0];
            $theme = $record_competitions['Theme'];
            $medium = $record_competitions['Medium'];
            $classification = $record_competitions['Classification'];
            // Create the competition node in the XML response
            $competition_element = $xml_competions->AppendChild($dom->CreateElement('Competition'));

            $date_element = $competition_element->AppendChild($dom->CreateElement('Date'));
            $date_element->AppendChild($dom->CreateTextNode($date));

            $theme_element = $competition_element->AppendChild($dom->CreateElement('Theme'));
            $theme_element->AppendChild($dom->CreateTextNode($theme));

            $medium_element = $competition_element->AppendChild($dom->CreateElement('Medium'));
            $medium_element->AppendChild($dom->CreateTextNode($medium));

            $xml_classification_node = $competition_element->AppendChild($dom->CreateElement('Classification'));
            $xml_classification_node->AppendChild($dom->CreateTextNode($classification));

            // Get all the entries for this competition
            try {
                $sql = "SELECT entries.ID, entries.Title, entries.Member_ID,
                        entries.Server_File_Name, entries.Score, entries.Award
                        FROM entries
                        WHERE entries.Competition_ID = :comp_id
                        ORDER BY entries.Member_ID, entries.Title";
                $sth_entries = $db->prepare($sql);
                $sth_entries->bindParam(':comp_id', $comp_id, \PDO::PARAM_INT, 11);
                $sth_entries->execute();
            } catch (\Exception $e) {
                $this->doRESTError("Failed to SELECT competition entries from database - " . $e->getMessage());
                die();
            }
            $all_records_entries = $sth_entries->fetchAll();
            // Create an Entries node

            $entries = $competition_element->AppendChild($dom->CreateElement('Entries'));
            // Iterate through all the entries for this competition
            foreach ($all_records_entries as $record_entries) {
                $user = get_user_by('id', $record_entries['Member_ID']);
                if ($this->core->isPaidMember($user->ID)) {
                    $entry_id = $record_entries['ID'];
                    $first_name = $user->first_name;
                    $last_name = $user->last_name;
                    $title = $record_entries['Title'];
                    $score = $record_entries['Score'];
                    $award = $record_entries['Award'];
                    $server_file_name = $record_entries['Server_File_Name'];
                    // Create an Entry node
                    $entry_element = $entries->AppendChild($dom->CreateElement('Entry'));
                    $id = $entry_element->AppendChild($dom->CreateElement('ID'));
                    $id->AppendChild($dom->CreateTextNode($entry_id));
                    $fname = $entry_element->AppendChild($dom->CreateElement('First_Name'));
                    $fname->AppendChild($dom->CreateTextNode($first_name));
                    $lname = $entry_element->AppendChild($dom->CreateElement('Last_Name'));
                    $lname->AppendChild($dom->CreateTextNode($last_name));
                    $title_node = $entry_element->AppendChild($dom->CreateElement('Title'));
                    $title_node->AppendChild($dom->CreateTextNode($title));
                    $score_node = $entry_element->AppendChild($dom->CreateElement('Score'));
                    $score_node->AppendChild($dom->CreateTextNode($score));
                    $award_node = $entry_element->AppendChild($dom->CreateElement('Award'));
                    $award_node->AppendChild($dom->CreateTextNode($award));
                    // Convert the absolute server file name into a URL
                    $image_url = home_url($record_entries['Server_File_Name']);
                    $url_node = $entry_element->AppendChild($dom->CreateElement('Image_URL'));
                    $url_node->AppendChild($dom->CreateTextNode($image_url));
                }
            }
            $record_competitions = $sth_competitions->fetch(\PDO::FETCH_ASSOC);
        }
        // Send the completed XML response back to the client
        // header('Content-Type: text/xml');
        echo $dom->saveXML();
    }

    /**
     * Handle the uploaded score from the RPS Client.
     */
    private function doUploadScore()
    {
        $username = $this->request->input('username');
        $password = $this->request->input('password');
        $comp_date = $this->request->input('date');
        try {
            $db = new RPSPDO();
        } catch (\PDOException $e) {
            $this->doRESTError("Failed to obtain database handle " . $e->getMessage());
            die();
        }
        if ($db !== false) {
            $user = wp_authenticate($username, $password);
            if (is_wp_error($user)) {
                $a = strip_tags($user->get_error_message());
                $this->doRESTError("Unable to authenticate: $a");
                die();
            }
        }
        // Check to see if there were any file upload errors
        $file = $this->request->file('file');
        switch ($file->getError()) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
                $this->doRESTError("The uploaded file exceeds the upload_max_filesize directive (" . ini_get("upload_max_filesize") . ") in php.ini.");
                die();
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $this->doRESTError("The uploaded file exceeds the maximum file size of " . $this->request->input('MAX_FILE_SIZE') / 1000 . "KB allowed by this form.");
                die();
                break;
            case UPLOAD_ERR_PARTIAL:
                $this->doRESTError("The uploaded file was only partially uploaded.");
                die();
                break;
            case UPLOAD_ERR_NO_FILE:
                $this->doRESTError("No file was uploaded.");
                die();
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $this->doRESTError("Missing a temporary folder.");
                die();
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $this->doRESTError("Failed to write file to disk");
                die();
                break;
            default:
                $this->doRESTError("Unknown File Upload Error");
                die();
                break;
        }

        // Move the file to its final location
        $path = $this->request->server('DOCUMENT_ROOT') . '/Digital_Competitions';
        $dest_name = "scores_" . $comp_date . ".xml";
        $file_name = $path . '/' . $dest_name;
        try {
            $file->move($path, $dest_name);
        } catch (FileException $e) {
            $this->doRESTError($e->getMessage());
            die();
        }

        $warning = $this->handleUploadScoresFile($db, $file_name);

        // Remove the uploaded .xml file
        unlink($file_name);

        // Return success to the client
        $warning = "  <info>Scores successfully uploaded</info>\n" . $warning;
        $this->doRESTSuccess($warning);
        die();
    }

    /**
     * Handle the XML file containing the scores and add them to the database
     *
     * @param object $db
     *            Database handle.
     */
    private function handleUploadScoresFile($db, $file_name)
    {
        $warning = '';
        $score = '';
        $award = '';
        $entry_id = '';

        if (!$xml = simplexml_load_file($file_name)) {
            $this->doRESTError("Failed to open scores XML file");
            die();
        }
        try {
            $sql = "UPDATE `entries` SET `Score` = :score, `Date_Modified` = NOW(), `Award` = :award WHERE `ID` = :entryid";
            $sth = $db->prepare($sql);
            $sth->bindParam(':score', $score, PDO::PARAM_STR);
            $sth->bindParam(':award', $award, PDO::PARAM_STR);
            $sth->bindParam(':entryid', $entry_id, PDO::PARAM_INT);
        } catch (\PDOException $e) {
            $this->doRESTError("Error - " . $e->getMessage() . " - $sql");
            die();
        }

        foreach ($xml->Competition as $comp) {
            $comp_date = $comp->Date;
            $classification = $comp->Classification;
            $medium = $comp->Medium;

            foreach ($comp->Entries as $entries) {
                foreach ($entries->Entry as $entry) {
                    $entry_id = $entry->ID;
                    $first_name = html_entity_decode($entry->First_Name);
                    $last_name = html_entity_decode($entry->Last_Name);
                    $title = html_entity_decode($entry->Title);
                    $score = html_entity_decode($entry->Score);
                    if (empty($entry->Award)) {
                        $award = null;
                    } else {
                        $award = html_entity_decode($entry->Award);
                    }

                    if ($entry_id != "") {
                        if ($score != "") {
                            try {
                                $sth->execute();
                            } catch (\PDOException $e) {
                                $this->doRESTError("Failed to UPDATE scores in database - " . $e->getMessage() . " - $sql");
                                die();
                            }
                            if ($sth->rowCount() < 1) {
                                $warning .= "  <info>$comp_date, $first_name $last_name, $title -- Row failed to update</info>\n";
                            }
                        }
                    } else {
                        $warning .= "  <info>$comp_date, $first_name $last_name, $title -- ID is Null -- skipped</info>\n";
                    }
                }
            }

            // Mark this competition as scored
            try {
                $sql = "UPDATE competitions SET Scored='Y', Date_Modified=NOW()
                WHERE Competition_Date='$comp_date' AND
                Classification='$classification' AND
                Medium = '$medium'";
                if (!$rs = mysql_query($sql)) {
                    throw new \Exception(mysql_error());
                }
            } catch (\Exception $e) {
                $this->doRESTError("Failed to execute UPDATE to set Scored flag to Y in database for $comp_date / $classification");
                die();
            }
            if (mysql_affected_rows() < 1) {
                $this->doRESTError("No rows updated when setting Scored flag to Y in database for $comp_date / $classification");
                die();
            }
        }

        return $warning;
    }

    /**
     * Create a REST error
     *
     * @param string $errMsg
     *            The actual error message
     */
    private function doRESTError($errMsg)
    {
        $this->doRESTResponse('fail', '<err msg="' . $errMsg . '" ></err>');
    }

    /**
     * Create a REST success message
     *
     * @param string $message
     *            The actual messsage
     */
    private function doRESTSuccess($message)
    {
        $this->doRESTResponse("ok", $message);
    }

    /**
     * Create the REST respone
     *
     * @param string $status
     * @param string $message
     */
    private function doRESTResponse($status, $message)
    {
        echo '<?xml version="1.0" encoding="utf-8" ?>' . "\n";
        echo '<rsp stat="' . $status . '">' . "\n";
        echo '	' . $message . "\n";
        echo "</rsp>\n";
    }

    /**
     * Check the upload entry for errors.
     */
    private function checkUploadEntry()
    {
        $_upload_ok = false;
        $file = $this->request->file('file_name');
        if (!$this->request->has('title')) {
            $this->settings->errmsg = 'Please enter your image title in the Title field.';
        } else {
            switch ($file->getError()) {
                case UPLOAD_ERR_OK:
                    $_upload_ok = true;
                    break;
                case UPLOAD_ERR_INI_SIZE:
                    $this->settings->errmsg = "The submitted file exceeds the upload_max_filesize directive (" . ini_get("upload_max_filesize") . "B) in php.ini.<br>Please report the exact text of this error message to the Digital Chair.<br>Try downsizing your image to 1024x788 pixels and submit again.";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $this->settings->errmsg = "The submitted file exceeds the maximum file size of " . $this->request->input('MAX_FILE_SIZE') / 1000 . "KB.<br />Click <a href=\"/digital/Resize Digital Images.shtml#Set_File_Size\">here</a> for instructions on setting the overall size of your file on disk.<br>Please report the exact text of this error message to the Digital Chair.</p>";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $this->settings->errmsg = "The submitted file was only partially uploaded.<br>Please report the exact text of this error message to the Digital Chair.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $this->settings->errmsg = "No file was submitted.&nbsp; Please try again.<br>Click the Browse button to select a .jpg image file before clicking Submit";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $this->settings->errmsg = "Missing a temporary folder.<br>Please report the exact text of this error message to the Digital Chair.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $this->settings->errmsg = "Failed to write file to disk on server.<br>Please report the exact text of this error message to the Digital Chair.";
                    break;
                default:
                    $this->settings->errmsg = "Unknown File Upload Error<br>Please report the exact text of this error message to the Digital Chair.";
            }
        }

        return $_upload_ok;
    }

    /**
     * Delete competition entries
     *
     * @param array $entries
     *            Array of entries ID to delete.
     */
    private function deleteCompetitionEntries($entries)
    {
        if (is_array($entries)) {
            foreach ($entries as $id) {

                $recs = $this->rpsdb->getEntryInfo($id, OBJECT);
                if ($recs == false) {
                    $this->settings->errmsg = sprintf("<b>Failed to SELECT competition entry with ID %s from database</b><br>", $id);
                } else {

                    $server_file_name = $this->request->server('DOCUMENT_ROOT') . $recs->Server_File_Name;
                    // Delete the record from the database
                    $result = $this->rpsdb->deleteEntry($id);
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
        $open_competitions = $this->rpsdb->getOpenCompetitions($this->settings->medium_subset);

        if (empty($open_competitions)) {
            return false;
        }

        // Read the competition attributes into a series of arrays
        $index = 0;
        $date_index = -1;
        $medium_index = -1;
        foreach ($open_competitions as $recs) {
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

        return true;
    }
}
