<?php
namespace RpsCompetition\Frontend;

use Avh\Network\Session;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use RpsCompetition\Api\Client;
use RpsCompetition\Common\Core;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Constants;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Forms\Forms as RpsForms;
use RpsCompetition\Frontend\Shortcodes\ShortcodeRouter;
use RpsCompetition\Options\General as Options;
use RpsCompetition\Settings;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class Frontend
 *
 * @package RpsCompetition\Frontend
 */
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
    /** @var Session */
    private $session;
    /** @var Settings */
    private $settings;
    /** @var Rpscompetition\Frontend\View */
    private $view;

    /**
     * Constructor
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->session = $container->make('Session');
        $this->session->start();

        $this->settings = $container->make('Settings');
        $this->rpsdb = $container->make('RpsDb');
        $this->request = $container->make('IlluminateRequest');
        $this->options = $container->make('OptionsGeneral');
        $this->core = $container->make('Core');
        $requests = $container->make('FrontendRequests');
        $this->view = $container->make('FrontendView');

        // The actions are in order as how WordPress executes them
        add_action('after_setup_theme', array($this, 'actionAfterThemeSetup'), 14);
        add_action('init', array($this, 'actionInit'), 11);
        add_action('parse_query', array($requests, 'actionHandleRequests'));

        if ($this->request->isMethod('POST')) {
            add_action('wp', array($this, 'actionHandleHttpPostRpsMyEntries'));
            add_action('wp', array($this, 'actionHandleHttpPostRpsEditTitle'));
            add_action('wp', array($this, 'actionHandleHttpPostRpsUploadEntry'));
            add_action('wp', array($this, 'actionHandleHttpPostRpsBanquetEntries'));
        }
        add_action('template_redirect', array($this, 'actionTemplateRedirectRpsWindowsClient'));
        add_action('wp_enqueue_scripts', array($this, 'actionEnqueueScripts'), 999);

        add_filter('query_vars', array($this, 'filterQueryVars'));
        add_filter('post_gallery', array($this, 'filterPostGallery'), 10, 2);
        add_filter('_get_page_link', array($this, 'filterPostLink'), 10, 2);
        add_filter('the_title', array($this, 'filterTheTitle'), 10, 2);
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
        global $post;

        //todo Make as an option in the admin section.
        $options = get_option('avh-rps');
        $all_masonry_pages = array();
        $all_masonry_pages[$options['monthly_entries_post_id']] = true;
        if (array_key_exists($wp_query->get_queried_object_id(), $all_masonry_pages)) {
            wp_enqueue_script('rps-masonryInit');
        }

        if (is_object($post)) {
            if (has_shortcode($post->post_content, 'rps_person_winners')) {
                wp_enqueue_script('rps-masonryInit');
            }

            if (has_shortcode($post->post_content, 'gallery')) {
                wp_enqueue_script('rps-masonryInit');
            }
        }

        wp_enqueue_style('rps-competition.general.style');
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
     * @see      Shortcodes::shortcodeEditTitle
     * @internal Hook: suffusion_before_post
     */
    public function actionHandleHttpPostRpsEditTitle()
    {
        global $post;

        $query_entries = $this->container->make('QueryEntries');
        $query_competitions = $this->container->make('QueryCompetitions');
        $photo_helper = $this->container->make('PhotoHelper');

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
                $result = $query_entries->updateEntry($updated_data);
                if ($result === false) {
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
     * @see      Shortcodes::shortcodeMyEntries
     * @internal Hook: suffusion_before_post
     */
    public function actionHandleHttpPostRpsMyEntries()
    {
        global $post;
        $query_competitions = $this->container->make('QueryCompetitions');

        if (is_object($post) && ($post->ID == 56 || $post->ID == 58)) {

            $page = explode('-', $post->post_name);
            $medium_subset = $page[1];
            if ($this->request->has('submit_control')) {
                // @TODO Nonce check

                $comp_date = $this->request->input('comp_date');
                $classification = $this->request->input('classification');
                $medium = $this->request->input('medium');
                $entry_array = $this->request->input('EntryID', null);

                switch ($this->request->input('submit_control')) {
                    case 'add':
                        if (!$query_competitions->checkCompetitionClosed($comp_date, $classification, $medium)) {
                            $query = array('m' => $medium_subset);
                            $query = build_query($query);
                            $loc = '/member/upload-image/?' . $query;
                            wp_redirect($loc);
                            exit();
                        }
                        break;

                    case 'edit':
                        if (!$query_competitions->checkCompetitionClosed($comp_date, $classification, $medium)) {
                            if (is_array($entry_array)) {
                                foreach ($entry_array as $id) {
                                    // @TODO Add Nonce
                                    $query = array('id' => $id, 'm' => $medium_subset);
                                    $query = build_query($query);
                                    $loc = '/member/edit-title/?' . $query;
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
     * @see      Shortcodes::shortcodeUploadImage
     * @internal Hook: suffusion_before_post
     */
    public function actionHandleHttpPostRpsUploadEntry()
    {
        global $post;
        $query_entries = $this->container->make('QueryEntries');
        $query_competitions = $this->container->make('QueryCompetitions');
        $photo_helper = $this->container->make('PhotoHelper');

        if (is_object($post) && $post->ID == 89 && $this->request->isMethod('post')) {

            $form = RpsForms::formUploadEntry('', '', '');
            $form->submit($this->request->get($form->getName()));
            $data = $form->getData();

            $redirect_to = $data['wp_get_referer'];
            // Just return if user clicked Cancel
            $this->isRequestCanceled($form, 'cancel', $redirect_to);

            $file = $this->request->file('form.file_name');
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
            if (!$this->request->has('form.title')) {
                $this->settings->set('errmsg', 'Please enter your image title in the Title field.');
                unset($query_entries, $query_competitions, $photo_helper);

                return;
            }

            // Retrieve and parse the selected competition cookie
            if ($this->session->has('myentries')) {
                $subset = $this->session->get('myentries/subset', null);
                $comp_date = $this->session->get('myentries/' . $subset . '/competition_date', null);
                $medium = $this->session->get('myentries/' . $subset . '/medium', null);
                $classification = $this->session->get('myentries/' . $subset . '/classification', null);
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
                $photo_helper->doResizeImage($uploaded_file_name, $full_server_path, $dest_name . '.jpg', 'FULL');
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

        $query_competitions = $this->container->make('QueryCompetitions');
        $query_competitions->setAllPastCompetitionsClose();

        $this->setupWpSeoActionsFilters();
        $this->setupUserMeta();
        $this->setupSocialButtons();

        $this->register_scripts_styles();

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
            $query_miscellaneous = $this->container->make('QueryMiscellaneous');
            $records = $query_miscellaneous->getEightsAndHigher(5);
            $data = array();
            $data['records'] = $records;
            $data['thumb_size'] = '150';
            echo $this->view->renderShowcaseCompetitionThumbnails($data);
            unset($query_miscellaneous);
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
     * Filter the output of the standard WordPress gallery.
     * Through this filter we create our own gallery layout.
     *
     * @param string $output The gallery output. Default empty.
     * @param array  $attr   Attributes of the gallery shortcode.
     *
     * @return mixed|string|void
     */
    public function filterPostGallery($output, $attr)
    {
        $post = get_post();

        static $instance = 0;
        $instance++;

        if (!empty($attr['ids'])) {
            // 'ids' is explicitly ordered, unless you specify otherwise.
            if (empty($attr['orderby'])) {
                $attr['orderby'] = 'post__in';
            }
            $attr['include'] = $attr['ids'];
        }

        // We're trusting author input, so let's at least make sure it looks like a valid orderby statement
        if (isset($attr['orderby'])) {
            $attr['orderby'] = sanitize_sql_orderby($attr['orderby']);
            if (!$attr['orderby']) {
                unset($attr['orderby']);
            }
        }

        $short_code_atts = shortcode_atts(
            array(
                'order'      => 'ASC',
                'orderby'    => 'menu_order ID',
                'id'         => $post ? $post->ID : 0,
                'itemtag'    => 'figure',
                'icontag'    => 'div',
                'captiontag' => 'figcaption',
                'columns'    => 3,
                'size'       => 'thumbnail',
                'include'    => '',
                'exclude'    => '',
                'link'       => '',
                'layout'     => 'row-equal'
            ),
            $attr,
            'gallery'
        );
        extract(
            $short_code_atts
        );

        $id = intval($id);
        if ('RAND' == $order) {
            $orderby = 'none';
        }

        if (!empty($include)) {
            $_attachments = get_posts(array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby));

            $attachments = array();
            foreach ($_attachments as $key => $val) {
                $attachments[$val->ID] = $_attachments[$key];
            }
        } elseif (!empty($exclude)) {
            $attachments = get_children(array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby));
        } else {
            $attachments = get_children(array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby));
        }

        if (empty($attachments)) {
            return '';
        }

        /**
         * Check if we ran the filter filterWpseoPreAnalysisPostsContent.
         *
         * @see Frontend::filterWpseoPreAnalysisPostsContent
         */
        $didFilterWpseoPreAnalysisPostsContent = $this->settings->get('didFilterWpseoPreAnalysisPostsContent', false);

        if (!$didFilterWpseoPreAnalysisPostsContent) {
            $entries = array();
            foreach ($attachments as $id => $attachment) {
                $img_url = wp_get_attachment_url($id);
                $home_url = home_url();
                if (substr($img_url, 0, strlen($home_url)) == $home_url) {
                    $entry = new \stdClass;
                    $img_relative_path = substr($img_url, strlen($home_url));
                    $entry->Server_File_Name = $img_relative_path;
                    $entries[] = $entry;
                }
            }
            $output = $this->view->renderCategoryWinnersFacebookThumbs($entries);

            return $output;
        }

        if (is_feed()) {
            $output = "\n";
            foreach ($attachments as $att_id => $attachment) {
                $output .= wp_get_attachment_link($att_id, $size, true) . "\n";
            }

            return $output;
        }

        if (strtolower($layout) == 'masonry') {
            $output = $this->view->renderGalleryMasonry($attachments);

            return $output;
        }

        $itemtag = tag_escape($itemtag);
        $captiontag = tag_escape($captiontag);
        $icontag = tag_escape($icontag);
        $valid_tags = wp_kses_allowed_html('post');
        if (!isset($valid_tags[$itemtag])) {
            $itemtag = 'dl';
        }
        if (!isset($valid_tags[$captiontag])) {
            $captiontag = 'dd';
        }
        if (!isset($valid_tags[$icontag])) {
            $icontag = 'dt';
        }

        $columns = intval($columns);

        $selector = "gallery-{$instance}";

        $gallery_style = $gallery_div = '';

        $layout = strtolower($layout);

        $size_class = sanitize_html_class($size);
        $gallery_div = "<div id='$selector' class='gallery galleryid-{$id} gallery-columns-{$columns} gallery-size-{$size_class}'>";

        /**
         * Filter the default gallery shortcode CSS styles.
         *
         * @param string $gallery_style Default gallery shortcode CSS styles.
         * @param string $gallery_div   Opening HTML div container for the gallery shortcode output.
         *
         */
        $output = apply_filters('gallery_style', $gallery_style . $gallery_div);
        $i = 0;
        foreach ($attachments as $id => $attachment) {
            if ($i % $columns == 0) {
                if ($layout == 'row-equal') {
                    $output .= '<div class="gallery-row gallery-row-equal">';
                } else {
                    $output .= '<div class="gallery-row">';
                }
            }
            if (!empty($link) && 'file' === $link) {
                $image_output = wp_get_attachment_link($id, $size, false, false);
            } elseif (!empty($link) && 'none' === $link) {
                $image_output = wp_get_attachment_image($id, $size, false);
            } else {
                $image_output = wp_get_attachment_link($id, $size, true, false);
            }

            $image_meta = wp_get_attachment_metadata($id);

            $orientation = '';
            if (isset($image_meta['height'], $image_meta['width'])) {
                $orientation = ($image_meta['height'] > $image_meta['width']) ? 'portrait' : 'landscape';
            }

            $output .= "<{$itemtag} class='gallery-item'>";
            $output .= "<div class='gallery-item-content'>";
            $output .= "<{$icontag} class='gallery-icon {$orientation}'>$image_output</{$icontag}>";

            $caption_text = '';
            if ($captiontag && trim($attachment->post_excerpt)) {
                $caption_text .= $attachment->post_excerpt;
            }
            $photographer_name = get_post_meta($attachment->ID, '_rps_photographer_name', true);
            // If image credit fields have data then attach the image credit
            if ($photographer_name != '') {
                if (!empty($caption_text)) {
                    $caption_text .= '<br />';
                }
                $caption_text .= '<span class="wp-caption-credit">Credit: ' . $photographer_name . '</span>';
            }
            if (!empty($caption_text)) {
                $output .= "<{$captiontag} class='wp-caption-text gallery-caption'>" . wptexturize($caption_text) . "</{$captiontag}>";
            }

            $output .= "</div>";
            $output .= "</{$itemtag}>";

            if ($columns > 0 && ++$i % $columns == 0) {
                $output .= '</div>';
            }
        }

        if ($columns > 0 && $i % $columns !== 0) {
            $output .= '</div>';
        }
        $output .= "
		</div>\n";

        return $output;
    }

    /**
     * Change the permalink for the dynamic pages.
     *
     * @param string  $link
     * @param integer $post_id
     *
     * @internal Hook: _get_page_link
     * @return string
     */
    public function filterPostLink($link, $post_id)
    {
        $pages_array = CommonHelper::getDynamicPages();
        if (isset($pages_array[$post_id])) {
            $selected_date = get_query_var('selected_date');
            if (!empty($selected_date)) {
                $link = $link . $selected_date . '/';
            }
        }

        return $link;
    }

    /**
     * Add custom query vars.
     *  - selected_date
     *
     * @see Shortcodes::shortcodeMonthlyEntries
     * @see Shortcodes::shortcodeMonthlyWinners
     *
     * @param array $vars
     *
     * @return string[]
     */
    public function filterQueryVars($vars)
    {
        $vars[] = 'selected_date';

        return $vars;
    }

    /**
     * Filter the title
     * For the Dynamic Pages we create a more elaborate title.
     *
     * @param string  $title
     *
     * @param integer $post_id
     *
     * @return string
     */
    public function filterTheTitle($title, $post_id)
    {
        $pages_array = CommonHelper::getDynamicPages();
        if (isset($pages_array[$post_id])) {

            $query_competitions = $this->container->make('QueryCompetitions');
            $selected_date = get_query_var('selected_date');
            $competitions = $query_competitions->getCompetitionByDates($selected_date);
            $competition = current($competitions);
            $theme = ucfirst($competition->Theme);
            $date = new \DateTime($selected_date);
            $date_text = $date->format('F j, Y');
            $title .= ' for the theme "' . $theme . '" on ' . $date_text;
        }

        return $title;
    }

    /**
     * Delete competition entries
     *
     * @param array $entries Array of entries ID to delete.
     */
    private function deleteCompetitionEntries($entries)
    {
        $query_entries = $this->container->make('QueryEntries');
        $photo_helper = $this->container->make('PhotoHelper');

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
     * Handles the required functions for when a user submits their Banquet Entries
     */
    private function handleSubmitBanquetEntries()
    {
        $query_entries = $this->container->make('QueryEntries');
        $query_competitions = $this->container->make('QueryCompetitions');
        $photo_helper = $this->container->make('PhotoHelper');

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
     * @param object $form   The Form that was submitted
     * @param string $cancel The field to check for cancellation
     * @param string $redirect_to
     */
    private function isRequestCanceled($form, $cancel, $redirect_to)
    {
        if ($form->get($cancel)
                 ->isClicked()
        ) {
            wp_redirect($redirect_to);
            exit();
        }
    }

    private function register_scripts_styles()
    {
        if (WP_LOCAL_DEV !== true) {
            $rps_competition_css_version = "c9c4aab";
            $rps_masonry_version = "867ece1";
            $masonry_version = "37b35d4";
            $imagesloaded_version = "37b35d4";
            $version_separator = '-';
        } else {
            $rps_competition_css_version = "";
            $rps_masonry_version = "";
            $masonry_version = "";
            $imagesloaded_version = "";
            $version_separator = '';
        }

        $rps_masonry_script = 'rps.masonry' . $version_separator . $rps_masonry_version . '.js';
        $masonry_script = 'masonry' . $version_separator . $masonry_version . '.js';
        $imagesloaded_script = 'imagesloaded' . $version_separator . $imagesloaded_version . '.js';
        $rps_competition_style = 'rps-competition' . $version_separator . $rps_competition_css_version . '.css';

        $javascript_directory = $this->settings->get('javascript_dir');
        wp_deregister_script('masonry');
        wp_register_script('masonry', CommonHelper::getPluginUrl($masonry_script, $javascript_directory), array(), 'to_remove', 1);
        wp_register_script('rps-imagesloaded', CommonHelper::getPluginUrl($imagesloaded_script, $javascript_directory), array('masonry'), 'to_remove', true);
        wp_register_script('rps-masonryInit', CommonHelper::getPluginUrl($rps_masonry_script, $javascript_directory), array('rps-imagesloaded'), 'to_remove', true);

        wp_register_style('rps-competition.fontawesome.style', 'http://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css"');
        wp_register_style('rps-competition.general.style', CommonHelper::getPluginUrl($rps_competition_style, $this->settings->get('css_dir')), array('rps-competition.fontawesome.style'), 'to_remove');
    }

    /**
     * Setup shortcodes.
     * Setup all the need shortcodes.
     */
    private function setupShortcodes()
    {
        /** @var ShortcodeRouter $shortcode */
        $shortcode = $this->container->make('ShortcodeRouter');
        $shortcode->setShortcodeController($this->container->make('ShortcodeController'));
        $shortcode->initializeShortcodes();
    }

    /**
     * Setup the Social Buttons.
     *
     */
    private function setupSocialButtons()
    {
        $social_networks_controller = $this->container->make('SocialNetworksRouter');

        if (WP_LOCAL_DEV !== true) {
            $social_buttons_script_version = "f233109";
            $version_separator = '-';
        } else {
            $social_buttons_script_version = "";
            $version_separator = '';
        }
        $data = array();
        $data['script'] = 'rps-competition.social-buttons' . $version_separator . $social_buttons_script_version . '.js';
        $social_networks_controller->initializeSocialNetworks($data);
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
        $wpseo = $this->container->make('WpSeoHelper');
        add_action('wpseo_register_extra_replacements', array($wpseo, 'actionWpseoRegisterExtraReplacements'));
        add_action('wpseo_do_sitemap_competition-entries', array($wpseo, 'actionWpseoSitemapCompetitionEntries'));
        add_action('wpseo_do_sitemap_competition-winners', array($wpseo, 'actionWpseoSitemapCompetitionWinners'));

        add_filter('wpseo_pre_analysis_post_content', array($wpseo, 'filterWpseoPreAnalysisPostsContent'), 10, 2);
        add_filter('wpseo_opengraph_image', array($wpseo, 'filterWpseoOpengraphImage'), 10, 1);
        add_filter('wpseo_metadesc', array($wpseo, 'filterWpseoMetaDescription'), 10, 1);
        add_filter('wpseo_sitemap_index', array($wpseo, 'filterWpseoSitemapIndex'));
        add_filter('wp_title_parts', array($wpseo, 'filterWpTitleParts'), 10, 1);
        add_filter('wpseo_opengraph_title', array($wpseo, 'filterOpenGraphTitle'), 10, 1);
        add_filter('wpseo_sitemap_entry', array($wpseo, 'filterSitemapEntry'), 10, 3);
    }
}
