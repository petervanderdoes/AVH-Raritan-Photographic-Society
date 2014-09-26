<?php
namespace RpsCompetition\Frontend;

use Avh\Network\Session;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use RpsCompetition\Api\Client;
use RpsCompetition\Common\Core;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Competition\Helper as CompetitionHelper;
use RpsCompetition\Constants;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Options\General as Options;
use RpsCompetition\Photo\Helper as PhotoHelper;
use RpsCompetition\Season\Helper as SeasonHelper;
use RpsCompetition\Settings;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class Frontend
{
    /** @var Container */
    private $container;
    /** @var Core */
    private $core;
    /** @var Options */
    private $options;
    /** @var Request */
    private $request;
    /** @var RpsDb */
    private $rpsdb;
    /** @var Settings */
    private $settings;

    /**
     * PHP5 Constructor
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->settings = $container->make('RpsCompetition\Settings');
        $this->rpsdb = $container->make('RpsCompetition\Db\RpsDb');
        $this->request = $container->make('Illuminate\Http\Request');
        $this->options = $container->make('RpsCompetition\Options\General');
        $this->core = new Core($this->settings);

        // The actions are in order as how WordPress executes them
        add_action('after_setup_theme', array($this, 'actionAfterThemeSetup'), 14);
        add_action('init', array($this, 'actionInit'));
        add_action('parse_query', array($this, 'actionHandleRequests'));
        add_action('wp_enqueue_scripts', array($this, 'actionEnqueueScripts'), 999);

        if ($this->request->isMethod('POST')) {
            add_action('suffusion_before_post', array($this, 'actionHandleHttpPostRpsMyEntries'));
            add_action('suffusion_before_post', array($this, 'actionHandleHttpPostRpsEditTitle'));
            add_action('suffusion_before_post', array($this, 'actionHandleHttpPostRpsUploadEntry'));
            add_action('suffusion_before_post', array($this, 'actionHandleHttpPostRpsBanquetEntries'));
        }
        add_action('template_redirect', array($this, 'actionTemplateRedirectRpsWindowsClient'));
        add_filter('query_vars', array($this, 'filterQueryVars'));
    }

    /**
     * Implement actions.
     * This method is called by the action after_setup_theme and is used to setup:
     *  - New actions
     *
     * @internal Hook: after_setup_theme
     */
    public function actionAfterThemeSetup()
    {
        add_action('rps_showcase', array($this, 'actionShowcaseCompetitionThumbnails'));
    }

    /**
     * Enqueue the needed javascripts.
     *
     * @internal Hook: wp_enqueue_scripts
     */
    public function actionEnqueueScripts()
    {
        global $wp_query;

        if (!is_admin()) {
            $scripts_directory_uri = $this->settings->get('plugin_url') . '/js/';
            if (WP_LOCAL_DEV == true) {
                $rps_masonry_script = 'rps.masonry.js';
            } else {
                $rps_masonry_version = "a128c24";
                $rps_masonry_script = 'rps.masonry-' . $rps_masonry_version . '.js';
            }

            //todo Make as an option in the admin section.
            $all_masonry_pages = array(1005);
            if (in_array($wp_query->get_queried_object_id(), $all_masonry_pages)) {
                wp_enqueue_script('rps-masonryInit', $scripts_directory_uri . $rps_masonry_script, array('masonry'), 'to_remove', false);
            }
        }
    }

    /**
     * Handle POST request for the Banquet Entries.
     * This method handles the POST request generated on the page for Banquet Entries
     * The action is called from the theme!
     *
     * @internal Hook: suffusion_before_post
     */
    public function actionHandleHttpPostRpsBanquetEntries()
    {
        global $post;

        if (is_object($post) && $post->post_title == 'Banquet Entries') {
            $redirect_to = $this->request->input('wp_get_referer');

            // Just return if user clicked Cancel
            $this->isRequestCanceled($redirect_to);

            if ($this->request->has('submit')) {
                $this->handleSubmitBanquetEntries();
            }
        }
    }

    /**
     * Handle POST request for the editing the title of a photo.
     * This method handles the POST request generated on the page Edit Title
     * The action is called from the theme!
     *
     * @see      Shortcodes::displayEditTitle
     * @internal Hook: suffusion_before_post
     */
    public function actionHandleHttpPostRpsEditTitle()
    {
        global $post;

        $query_entries = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
        $photo_helper = new PhotoHelper($this->settings, $this->request, $this->rpsdb);

        if (is_object($post) && $post->ID == 75) {
            $redirect_to = $this->request->input('wp_get_referer');
            $entry_id = $this->request->input('id');

            // Just return to the My Images page is the user clicked Cancel
            $this->isRequestCanceled($redirect_to);

            // makes sure they filled in the title field
            if (!$this->request->has('new_title')) {
                $this->settings->set('errmsg', 'You must provide an image title.<br><br>');
            } else {
                $server_file_name = $this->request->input('server_file_name');
                $new_title = trim($this->request->input('new_title'));
                if (get_magic_quotes_gpc()) {
                    $server_file_name = stripslashes($this->request->input('server_file_name'));
                    $new_title = stripslashes(trim($this->request->input('new_title')));
                }

                $competition = $query_competitions->getCompetitionByEntryId($entry_id);
                if ($competition == null) {
                    wp_die("Failed to SELECT competition for entry ID: " . $entry_id);
                }

                // Rename the image file on the server file system
                $path = $photo_helper->getCompetitionPath($competition->Competition_Date, $competition->Classification, $competition->Medium);
                $old_file_parts = pathinfo($server_file_name);
                $old_file_name = $old_file_parts['filename'];
                $ext = $old_file_parts['extension'];
                $current_user = wp_get_current_user();
                $new_file_name_noext = sanitize_file_name($new_title) . '+' . $current_user->user_login . '+' . filemtime($this->request->server('DOCUMENT_ROOT') . $server_file_name);
                $new_file_name = $new_file_name_noext . $ext;
                if (!$photo_helper->renameImageFile($path, $old_file_name, $new_file_name_noext, $ext)) {
                    die('<b>Failed to rename image file</b><br>Path: ' . $path . '<br>Old Name: ' . $old_file_name . '<br>New Name: ' . $new_file_name_noext);
                }

                // Update the Title and File Name in the database
                $updated_data = array('ID' => $entry_id, 'Title' => $new_title, 'Server_File_Name' => $path . '/' . $new_file_name, 'Date_Modified' => current_time('mysql'));
                $_result = $query_entries->updateEntry($updated_data);
                if ($_result === false) {
                    wp_die("Failed to UPDATE entry record from database");
                }

                $redirect_to = $this->request->input('wp_get_referer');
                wp_redirect($redirect_to);
                exit();
            }
        }
        unset($query_entries, $query_competitions, $photo_helper);
    }

    /**
     * Handle POST request for editing the entries of a user.
     * This method handles the POST request generated on the page for editing entries
     * The action is called from the theme!
     *
     * @see      Shortcodes::displayMyEntries
     * @internal Hook: suffusion_before_post
     */
    public function actionHandleHttpPostRpsMyEntries()
    {
        global $post;
        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);

        if (is_object($post) && ($post->ID == 56 || $post->ID == 58)) {

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
                            exit();
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
                                    exit();
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
     * @see      Shortcodes::displayUploadEntry
     * @internal Hook: suffusion_before_post
     */
    public function actionHandleHttpPostRpsUploadEntry()
    {
        global $post;
        $query_entries = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
        $photo_helper = new PhotoHelper($this->settings, $this->request, $this->rpsdb);

        if (is_object($post) && $post->ID == 89 && $this->request->isMethod('post')) {

            $redirect_to = $this->request->input('wp_get_referer');

            // Just return if user clicked Cancel
            $this->isRequestCanceled($redirect_to);

            $file = $this->request->file('file_name');
            if ($file === null) {
                $this->settings->set('errmsg', 'You did not select a file to upload');
                unset($query_entries, $query_competitions, $photo_helper);

                return;
            }

            if (!$file->isValid()) {
                $this->settings->set('errmsg', $file->getErrorMessage());
                unset($query_entries, $query_competitions, $photo_helper);

                return;
            }

            // Verify that the uploaded image is a JPEG
            $uploaded_file_name = $file->getRealPath();
            $uploaded_file_info = getimagesize($uploaded_file_name);
            if ($uploaded_file_info === false || $uploaded_file_info[2] != IMAGETYPE_JPEG) {
                $this->settings->set('errmsg', "Submitted file is not a JPEG image.  Please try again.<br>Click the Browse button to select a .jpg image file before clicking Submit");
                unset($query_entries, $query_competitions, $photo_helper);

                return;
            }
            if (!$this->request->has('title')) {
                $this->settings->set('errmsg', 'Please enter your image title in the Title field.');
                unset($query_entries, $query_competitions, $photo_helper);

                return;
            }

            // Retrieve and parse the selected competition cookie
            if ($this->request->hasCookie('RPS_MyEntries')) {
                list ($comp_date, $classification, $medium) = explode("|", $this->request->cookie('RPS_MyEntries'));
            } else {
                $this->settings->set('errmsg', "Upload Form Error<br>The Selected_Competition cookie is not set.");
                unset($query_entries, $query_competitions, $photo_helper);

                return;
            }

            $recs = $query_competitions->getCompetitionByDateClassMedium($comp_date, $classification, $medium, ARRAY_A);
            if ($recs) {
                $comp_id = $recs['ID'];
                $max_entries = $recs['Max_Entries'];
            } else {
                $this->settings->set('errmsg', "Upload Form Error<br>Competition $comp_date/$classification/$medium not found in database<br>");
                unset($query_entries, $query_competitions, $photo_helper);

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
            // an entry already submitted to this competition. Duplicate title result in duplicate
            // file names on the server
            if ($query_entries->checkDuplicateTitle($comp_id, $title, get_current_user_id())) {
                $this->settings->set('errmsg', "You have already submitted an entry with a title of \"" . $title . "\" in this competition<br>Please submit your entry again with a different title.");
                unset($query_entries, $query_competitions, $photo_helper);

                return;
            }

            // Do a final check that the user hasn't exceeded the maximum images per competition.
            // If we don't check this at the last minute it may be possible to exceed the
            // maximum images per competition by having two upload windows open simultaneously.
            $max_per_id = $query_entries->countEntriesByCompetitionId($comp_id, get_current_user_id());
            if ($max_per_id >= $max_entries) {
                $this->settings->set('errmsg', "You have already submitted the maximum of $max_entries entries into this competition<br>You must Remove an image before you can submit another");
                unset($query_entries, $query_competitions, $photo_helper);

                return;
            }

            $max_per_date = $query_entries->countEntriesByCompetitionDate($comp_date, get_current_user_id());
            if ($max_per_date >= $this->settings->get('club_max_entries_per_member_per_date')) {
                $max_entries_member_date = $this->settings->get('club_max_entries_per_member_per_date');
                $this->settings->set('errmsg', "You have already submitted the maximum of $max_entries_member_date entries for this competition date<br>You must Remove an image before you can submit another");
                unset($query_entries, $query_competitions, $photo_helper);

                return;
            }

            // Move the file to its final location
            $relative_server_path = $photo_helper->getCompetitionPath($comp_date, $classification, $medium);
            $full_server_path = $this->request->server('DOCUMENT_ROOT') . $relative_server_path;

            $user = wp_get_current_user();
            $dest_name = sanitize_file_name($title) . '+' . $user->user_login . '+' . filemtime($uploaded_file_name);
            // Need to create the destination folder?
            CommonHelper::createDirectory($full_server_path);

            // If the .jpg file is too big resize it
            if ($uploaded_file_info[0] > Constants::IMAGE_MAX_WIDTH_ENTRY || $uploaded_file_info[1] > Constants::IMAGE_MAX_HEIGHT_ENTRY) {

                // Resize the image and deposit it in the destination directory
                $photo_helper->rpsResizeImage($uploaded_file_name, $full_server_path, $dest_name . '.jpg', 'FULL');
                $resized = 1;
            } else {
                // The uploaded image does not need to be resized so just move it to the destination directory
                $resized = 0;
                try {
                    $file->move($full_server_path, $dest_name . '.jpg');
                } catch (FileException $e) {
                    $this->settings->set('errmsg', $e->getMessage());
                    unset($query_entries, $query_competitions, $photo_helper);

                    return;
                }
            }
            $server_file_name = $relative_server_path . '/' . $dest_name . '.jpg';
            $data = array('Competition_ID' => $comp_id, 'Title' => $title, 'Client_File_Name' => $client_file_name, 'Server_File_Name' => $server_file_name);
            $result = $query_entries->addEntry($data, get_current_user_id());
            if ($result === false) {
                $this->settings->set('errmsg', "Failed to INSERT entry record into database");
                unset($query_entries, $query_competitions, $photo_helper);

                return;
            }

            $photo_helper->createCommonThumbnails($query_entries->getEntryById($this->rpsdb->insert_id));
            $query = build_query(array('resized' => $resized));
            wp_redirect($redirect_to . '/?' . $query);
            exit();
        }
        unset($query_entries, $query_competitions, $photo_helper);
    }

    /**
     * Handle HTTP Requests.
     *
     * @param $wp_query
     */
    public function actionHandleRequests($wp_query)
    {

        $options = get_option('avh-rps');
        if (isset($wp_query->query['page_id'])) {
            if ($wp_query->query['page_id'] == $options['monthly_entries_post_id']) {
                $this->handleRequestMonthlyEntries();
            }
            if ($wp_query->query['page_id'] == $options['monthly_winners_post_id']) {
                $this->handleRequestMonthlyWinners();
            }
        }
    }

    /**
     * Setup all that is needed to run the plugin.
     * This method runs during the init hook and you can basically add everything that needs
     * to be setup for plugin.
     * - Shortcodes
     * - User meta information concerning their classification
     * - Rewrite rules
     *
     * @internal Hook: init
     */
    public function actionInit()
    {

        $this->setupShortcodes();

        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
        $query_competitions->setAllPastCompetitionsClose();

        add_filter('wp_title_parts', array($this, 'filterWpTitleParts'), 10, 1);
        $this->setupWpSeoActionsFilters();
        $this->setupUserMeta();

        $this->setupRewriteRules();

        unset($query_competitions);
    }

    /**
     * Display the showcase on the front page.
     * This will display the showcase as used on the front page.
     *
     * @see      actionAfterThemeSetup
     * @internal Hook: rps_showcase
     *
     * @param null $foo
     */
    public function actionShowcaseCompetitionThumbnails($foo)
    {
        if (is_front_page()) {
            $query_miscellaneous = new QueryMiscellaneous($this->rpsdb);
            $photo_helper = new PhotoHelper($this->settings, $this->request, $this->rpsdb);

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
                echo '<a href="' . $photo_helper->rpsGetThumbnailUrl($recs, 800) . '" rel="rps-showcase" title="' . $title . ' by ' . $first_name . ' ' . $last_name . '">';
                echo '<img src="' . $photo_helper->rpsGetThumbnailUrl($recs, 150) . '" /></a>';
                echo '</div>';
                $caption = "${title}<br /><span class='wp-caption-credit'>Credit: ${first_name} ${last_name}";
                echo "<figcaption class='wp-caption-text showcase-caption'>" . wptexturize($caption) . "</figcaption>\n";
                echo '</div>';

                echo '</figure>' . "\n";
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';

            unset($query_miscellaneous, $photo_helper);
        }
    }

    /**
     * Handles the requests by the RPS Windows Client
     *
     * @internal Hook: template_redirect
     */
    public function actionTemplateRedirectRpsWindowsClient()
    {
        if ($this->request->has('rpswinclient')) {

            $api_client = new Client();

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
     * Setup replace variables for WordPress Seo by Yoast.
     */
    public function actionWpseoRegisterExtraReplacements()
    {
        wpseo_register_var_replacement('%%rpstitle%%', array($this, 'handleWpSeoTitleReplace'));
    }

    /**
     * Build sitemap for Competition Entries
     *
     */
    public function actionWpseoSitemapCompetitionEntries()
    {
        $options = get_option('avh-rps');
        $url = get_permalink($options['monthly_entries_post_id']);
        $this->buildWpseoSitemap($url);
        exit();
    }

    /**
     * Build sitemap for Competition Winners
     *
     */
    public function actionWpseoSitemapCompetitionWinners()
    {
        $options = get_option('avh-rps');
        $url = get_permalink($options['monthly_winners_post_id']);
        $this->buildWpseoSitemap($url);
        exit();
    }

    /**
     * Add custom query vars.
     *  - selected_date
     *
     * @see Shortcodes::displayMonthlyEntries
     * @see Shortcodes::displayMonthlyWinners
     *
     * @param array $vars
     *
     * @return array
     */
    public function filterQueryVars($vars)
    {
        $vars[] = 'selected_date';

        return $vars;
    }

    /**
     * Filter for the title of pages.
     *
     * @param array $title_array
     *
     * @return array
     */
    public function filterWpTitleParts($title_array)
    {
        global $post;

        $options = get_option('avh-rps');
        $pages_array = array($options['monthly_entries_post_id'] => true, $options['monthly_winners_post_id'] => true);
        if (isset($pages_array[$post->ID])) {
            $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
            $selected_date = get_query_var('selected_date');
            $competitions = $query_competitions->getCompetitionByDates($selected_date);
            $competition = current($competitions);

            $new_title_array = array();
            $new_title_array[] = $post->post_title . ' - ' . $selected_date . ' - ' . $competition->Theme;
            $title_array = $new_title_array;
        }

        return $title_array;
    }

    /**
     * Filter the meta description for the following pages:
     * - Monthly Entries
     * - Monthly Winners
     *
     * @param string $meta_description
     *
     * @return string
     */
    public function filterWpseoMetaDescription($meta_description)
    {
        global $post;

        $options = get_option('avh-rps');
        $pages_array = array($options['monthly_entries_post_id'] => true, $options['monthly_winners_post_id'] => true);
        if (isset($pages_array[$post->ID])) {

            $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
            $selected_date = get_query_var('selected_date');
            $competitions = $query_competitions->getCompetitionByDates($selected_date);
            $competition = current($competitions);
            $theme = ucfirst($competition->Theme);
            $date = new \DateTime($selected_date);
            $date_text = $date->format('F j, Y');

            if ($post->ID == $options['monthly_entries_post_id']) {
                $meta_description = 'All entries submitted to Raritan Photographic Society for the theme "' . $theme . '" held on ' . $date_text;
            }
            if ($post->ID == $options['monthly_winners_post_id']) {
                $meta_description = 'All winners of the competition held by Raritan Photographic Society for the theme "' . $theme . '" held on ' . $date_text;
            }
        }

        return $meta_description;
    }

    /**
     * Filter for WordPress SEO plugin by Yoast: Use for the OpenGraph image property.
     * We only want to use images that are resized for Facebook shared link.
     * We add "_fb_thumb" to those thumbnail files.
     * If we return an empty string the image is not selected for the og:image meta information.
     *
     * @param string $img
     *
     * @return string
     */
    public function filterWpseoOpengraphImage($img)
    {
        if (strpos($img, '_fb_thumb.jpg') !== false) {
            return $img;
        }

        return '';
    }

    /**
     * Filter for WordPress SEO plugin by Yoast: Before analyzing the post content.
     * As some of the pages create dynamic images through shortcode we need to run the shortcode.
     * That's the only way the WordPress SEO plugin can see the images.
     * Running the shortcodes now does not effect the final rendering of the post.
     *
     * @param string $post_content
     * @param object $post
     *
     * @return string
     */
    public function filterWpseoPreAnalysisPostsContent($post_content, $post)
    {
        if (has_shortcode($post_content, 'rps_category_winners')) {
            $post_content = do_shortcode($post_content);
            $this->settings->set('didFilterWpseoPreAnalysisPostsContent', true);
        }

        return $post_content;
    }

    public function filterWpseoSitemapIndex()
    {
        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);

        $last_scored = $query_competitions->query(array('where' => 'Scored="Y"', 'orderby' => 'Competition_Date', 'order' => 'DESC', 'number' => 1));
        $date = new \DateTime($last_scored->Date_Modified);

        $sitemap = '';
        $sitemap .= '<sitemap>' . "\n";
        $sitemap .= '<loc>' . wpseo_xml_sitemaps_base_url('competition-entries') . '-sitemap.xml' . '</loc>' . "\n";
        $sitemap .= '<lastmod>' . htmlspecialchars($date->format('c')) . '</lastmod>' . "\n";
        $sitemap .= '</sitemap>' . "\n";
        $sitemap .= '<sitemap>' . "\n";
        $sitemap .= '<loc>' . wpseo_xml_sitemaps_base_url('competition-winners') . '-sitemap.xml' . '</loc>' . "\n";
        $sitemap .= '<lastmod>' . htmlspecialchars($date->format('c')) . '</lastmod>' . "\n";
        $sitemap .= '</sitemap>' . "\n";

        return $sitemap;
    }

    /**
     * Handle the replacement variable for WordPress Seo by Yoast.
     *
     * @param $foo
     *
     * @return string
     */
    public function handleWpSeoTitleReplace($foo)
    {
        global $post;

        $replacement = null;
        $title_array = array();

        if (is_string($post->post_title) && $post->post_title !== '') {
            $replacement = stripslashes($post->post_title);
        }
        $title_array[] = $replacement;

        $new = $this->filterWpTitleParts($title_array);

        return $new[0];
    }

    /**
     * Build actual sitemap for Competition Entries or Competition Winners
     *
     * @param string $url
     */
    private function buildWpseoSitemap($url)
    {
        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
        $scored_competitions = $query_competitions->getScoredCompetitions('1970-01-01', '2200-01-01');

        $old_mod_date = 0;
        $old_key = 0;
        /** @var QueryCompetitions $competition */
        foreach ($scored_competitions as $competition) {
            $competition_date = new \DateTime($competition->Competition_Date);
            $key = $competition_date->format('U');
            $date = new \DateTime($competition->Date_Modified, new \DateTimeZone(get_option('timezone_string')));
            $mod_date = $date->format('U');

            if ($key != $old_key) {
                $old_mod_date = 0;
                $sitemap_loc = $url . $competition_date->format('Y-m-d') . '/';
            }
            if ($mod_date > $old_mod_date) {
                $old_mod_date = $mod_date;
                $sitemap_date = $date->format('c');
            }

            $site_url[$key] = array(
                'loc' => $sitemap_loc,
                'pri' => 0.8,
                'chf' => 'monthly',
                'mod' => $sitemap_date,
            );
        }
        $output = '';
        foreach ($site_url as $url) {
            $output .= "\t<url>\n";
            $output .= "\t\t<loc>" . $url['loc'] . "</loc>\n";
            $output .= "\t\t<lastmod>" . $url['mod'] . "</lastmod>\n";
            $output .= "\t\t<changefreq>" . $url['chf'] . "</changefreq>\n";
            $output .= "\t\t<priority>" . $url['pri'] . "</priority>\n";
            $output .= "\t</url>\n";
        }

        $sitemap = '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $sitemap .= 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" ';
        $sitemap .= 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $sitemap .= $output;
        $sitemap .= '</urlset>';

        header('HTTP/1.1 200 OK', true, 200);
        // Prevent the search engines from indexing the XML Sitemap.
        header('X-Robots-Tag: noindex,follow', true);
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-16"?>';
        echo '<?xml-stylesheet type="text/xsl" href="' . preg_replace('/(^http[s]?:)/', '', esc_url(home_url('main-sitemap.xsl'))) . '"?>';
        echo $sitemap;
        echo "\n";
    }

    /**
     * Delete competition entries
     *
     * @param array $entries Array of entries ID to delete.
     */
    private function deleteCompetitionEntries($entries)
    {
        $query_entries = new QueryEntries($this->rpsdb);
        $photo_helper = new PhotoHelper($this->settings, $this->request, $this->rpsdb);

        if (is_array($entries)) {
            foreach ($entries as $id) {

                $entry_record = $query_entries->getEntryById($id);
                if ($entry_record == false) {
                    $this->settings->set('errmsg', sprintf("<b>Failed to SELECT competition entry with ID %s from database</b><br>", $id));
                } else {
                    // Delete the record from the database
                    $result = $query_entries->deleteEntry($id);
                    if ($result === false) {
                        $this->settings->set('errmsg', sprintf("<b>Failed to DELETE competition entry %s from database</b><br>"));
                    } else {
                        // Delete the file from the server file system
                        $photo_helper->deleteEntryFromDisk($entry_record);
                    }
                }
            }
        }
        unset($query_entries, $photo_helper);
    }

    /**
     * Handle HTTP requests for Monthly Entries before the page is displayed.
     */
    private function handleRequestMonthlyEntries()
    {

        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
        $season_helper = new SeasonHelper($this->settings, $this->rpsdb);
        $competition_helper = new CompetitionHelper($this->settings, $this->rpsdb);

        $redirect = false;
        $status = 303;
        $query_var_selected_date = get_query_var('selected_date', false);

        /**
         * When a new season or new month is selected from the form the submit_control is set.
         * If it's not set we came to page directly and that's handles by the default section.
         */
        switch ($this->request->input('submit_control', null)) {
            case 'new_season':
                $selected_season = esc_attr($this->request->input('new_season'));
                $selected_date = '';
                break;
            case 'new_month':
                $selected_date = esc_attr($this->request->input('new_month'));
                $selected_season = esc_attr($this->request->input('selected_season'));
                break;
            default:
                if ($query_var_selected_date === false || (!CommonHelper::isValidDate($query_var_selected_date, 'Y-m-d'))) {
                    $last_scored = $query_competitions->query(array('where' => 'Scored="Y"', 'orderby' => 'Competition_Date', 'order' => 'DESC', 'number' => 1));
                    $date_object = new \DateTime($last_scored->Competition_Date);
                    $selected_date = $date_object->format(('Y-m-d'));
                    $redirect = true;
                } else {
                    $selected_date = $query_var_selected_date;
                }
                $selected_season = $season_helper->getSeasonId($selected_date);
                break;
        }

        if (!$season_helper->isValidSeason($selected_season)) {
            $selected_season = $season_helper->getSeasonId(date('r'));
            $competitions = $query_competitions->getCompetitionBySeasonId($selected_season, array('Scored' => 'Y'));
            /** @var QueryCompetitions $competition */
            $competition = end($competitions);
            $date_object = new \DateTime($competition->Competition_Date);
            $selected_date = $date_object->format(('Y-m-d'));
        }

        if (!$competition_helper->isScoredCompetitionDate($selected_date)) {
            $competitions = $query_competitions->getCompetitionBySeasonId($selected_season, array('Scored' => 'Y'));
            /** @var QueryCompetitions $competition */
            $competition = end($competitions);
            $date_object = new \DateTime($competition->Competition_Date);
            $selected_date = $date_object->format(('Y-m-d'));
        }

        if ($selected_date != $query_var_selected_date) {
            $redirect = true;
        }

        if ($redirect) {
            wp_redirect('/events/monthly-entries/' . $selected_date . '/', $status);
            exit();
        }

        $session = new Session(array('name' => 'monthly_entries_' . COOKIEHASH));
        $session->start();
        $session->set('selected_date', $selected_date);
        $session->set('selected_season', $selected_season);
        $session->save();
    }

    /**
     * Handle HTTP requests for Monthly Winners before the page is displayed.
     */
    private function handleRequestMonthlyWinners()
    {
        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
        $season_helper = new SeasonHelper($this->settings, $this->rpsdb);
        $competition_helper = new CompetitionHelper($this->settings, $this->rpsdb);

        $redirect = false;
        $status = 303;
        $query_var_selected_date = get_query_var('selected_date', false);

        /**
         * When a new season or new month is selected from the form the submit_control is set.
         * If it's not set we came to page directly and that's handles by the default section.
         */
        switch ($this->request->input('submit_control', null)) {
            case 'new_season':
                $selected_season = esc_attr($this->request->input('new_season'));
                $selected_date = 'latest';
                break;
            case 'new_month':
                $selected_date = esc_attr($this->request->input('new_month'));
                $selected_season = esc_attr($this->request->input('selected_season'));
                break;
            default:
                if ($query_var_selected_date === false || (!CommonHelper::isValidDate($query_var_selected_date, 'Y-m-d'))) {
                    $last_scored = $query_competitions->query(array('where' => 'Scored="Y" AND Special_Event="N"', 'orderby' => 'Competition_Date', 'order' => 'DESC', 'number' => 1));
                    $date_object = new \DateTime($last_scored->Competition_Date);
                    $selected_date = $date_object->format(('Y-m-d'));
                    $redirect = true;
                } else {
                    $selected_date = $query_var_selected_date;
                }
                $selected_season = $season_helper->getSeasonId($selected_date);
                break;
        }

        if (!$season_helper->isValidSeason($selected_season)) {
            $selected_season = $season_helper->getSeasonId(date('r'));
            $competitions = $query_competitions->getCompetitionBySeasonId($selected_season, array('Scored' => 'Y'));
            /** @var QueryCompetitions $competition */
            $competition = end($competitions);
            $date_object = new \DateTime($competition->Competition_Date);
            $selected_date = $date_object->format(('Y-m-d'));
        }

        if (!$competition_helper->isScoredCompetitionDate($selected_date)) {
            $competitions = $query_competitions->getCompetitionBySeasonId($selected_season, array('Scored' => 'Y'));
            /** @var QueryCompetitions $competition */
            $competition = end($competitions);
            $date_object = new \DateTime($competition->Competition_Date);
            $selected_date = $date_object->format(('Y-m-d'));
        }

        if ($selected_date != $query_var_selected_date) {
            $redirect = true;
        }

        if ($redirect) {
            wp_redirect('/events/monthly-winners/' . $selected_date . '/', $status);
            exit();
        }

        $session = new Session(array('name' => 'monthly_winners_' . COOKIEHASH));
        $session->start();
        $session->set('selected_date', $selected_date);
        $session->set('selected_season', $selected_season);
        $session->save();
    }

    /**
     * Handles the required functions for when a user submits their Banquet Entries
     */
    private function handleSubmitBanquetEntries()
    {
        $query_entries = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
        $photo_helper = new PhotoHelper($this->settings, $this->request, $this->rpsdb);

        if ($this->request->has('allentries')) {
            $all_entries = explode(',', $this->request->input('allentries'));
            foreach ($all_entries as $entry_id) {
                $entry = $query_entries->getEntryById($entry_id);
                if (!is_null($entry)) {
                    $query_entries->deleteEntry($entry->ID);
                    $photo_helper->deleteEntryFromDisk($entry);
                }
            }
        }

        $entries = (array) $this->request->input('entry_id', array());
        foreach ($entries as $entry_id) {
            $entry = $query_entries->getEntryById($entry_id);
            $competition = $query_competitions->getCompetitionByID($entry->Competition_ID);
            $banquet_ids = explode(',', $this->request->input('banquetids'));
            foreach ($banquet_ids as $banquet_id) {
                $banquet_record = $query_competitions->getCompetitionByID($banquet_id);
                if ($competition->Medium == $banquet_record->Medium && $competition->Classification == $banquet_record->Classification) {
                    // Move the file to its final location
                    $path = $photo_helper->getCompetitionPath($banquet_record->Competition_Date, $banquet_record->Classification, $banquet_record->Medium);
                    CommonHelper::createDirectory($path);
                    $file_info = pathinfo($entry->Server_File_Name);
                    $new_file_name = $path . '/' . $file_info['basename'];
                    $original_filename = html_entity_decode($this->request->server('DOCUMENT_ROOT') . $entry->Server_File_Name, ENT_QUOTES, get_bloginfo('charset'));
                    // Need to create the destination folder?
                    copy($original_filename, $this->request->server('DOCUMENT_ROOT') . $new_file_name);
                    $data = array('Competition_ID' => $banquet_record->ID, 'Title' => $entry->Title, 'Client_File_Name' => $entry->Client_File_Name, 'Server_File_Name' => $new_file_name);
                    $query_entries->addEntry($data, get_current_user_id());
                }
            }
        }
        unset($query_entries, $query_competitions, $photo_helper);
    }

    /**
     * Check if user pressed cancel and if so redirect the user
     *
     * @param string $redirect_to
     */
    private function isRequestCanceled($redirect_to)
    {
        if ($this->request->has('cancel')) {
            wp_redirect($redirect_to);
            exit();
        }
    }

    private function setupRewriteRules()
    {
        $options = get_option('avh-rps');
        $url = get_permalink($options['monthly_entries_post_id']);
        if ($url !== false) {
            $url = substr(parse_url($url, PHP_URL_PATH), 1);
            add_rewrite_rule($url . '?([^/]*)', 'index.php?page_id=' . $options['monthly_entries_post_id'] . '&selected_date=$matches[1]', 'top');
        }

        $url = get_permalink($options['monthly_winners_post_id']);
        if ($url !== false) {
            $url = substr(parse_url($url, PHP_URL_PATH), 1);
            add_rewrite_rule($url . '?([^/]*)', 'index.php?page_id=' . $options['monthly_winners_post_id'] . '&selected_date=$matches[1]', 'top');
        }
    }

    /**
     * Setup shortcodes.
     * Setup all the need shortcodes.
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
        $user_meta = get_user_meta($user_id, 'rps_class_bw', true);
        if (empty($user_meta)) {
            update_user_meta($user_id, "rps_class_bw", 'beginner');
            update_user_meta($user_id, "rps_class_color", 'beginner');
            update_user_meta($user_id, "rps_class_print_bw", 'beginner');
            update_user_meta($user_id, "rps_class_print_color", 'beginner');
        }
    }

    /**
     * Setup the filters and action for the plugin WordPress Seo by Yoast
     *
     */
    private function setupWpSeoActionsFilters()
    {
        add_action('wpseo_register_extra_replacements', array($this, 'actionWpseoRegisterExtraReplacements'));
        add_filter('wpseo_pre_analysis_post_content', array($this, 'filterWpseoPreAnalysisPostsContent'), 10, 2);
        add_filter('wpseo_opengraph_image', array($this, 'filterWpseoOpengraphImage'), 10, 1);
        add_filter('wpseo_metadesc', array($this, 'filterWpseoMetaDescription'), 10, 1);
        add_filter('wpseo_sitemap_index', array($this, 'filterWpseoSitemapIndex'));
        add_action('wpseo_do_sitemap_competition-entries', array($this, 'actionWpseoSitemapCompetitionEntries'));
        add_action('wpseo_do_sitemap_competition-winners', array($this, 'actionWpseoSitemapCompetitionWinners'));
    }
}
