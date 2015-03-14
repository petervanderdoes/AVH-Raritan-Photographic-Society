<?php
namespace RpsCompetition\Frontend;

use Avh\Network\Session;
use Illuminate\Http\Request;
use RpsCompetition\Api\Client;
use RpsCompetition\Application;
use RpsCompetition\Common\Core;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Constants;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Entity\Forms\BanquetCurrentUser as BanquetCurrentUserEntity;
use RpsCompetition\Entity\Forms\UploadImage as UploadImageEntity;
use RpsCompetition\Form\Type\BanquetCurrentUserType;
use RpsCompetition\Form\Type\UploadImageType;
use RpsCompetition\Frontend\Shortcodes\ShortcodeRouter;
use RpsCompetition\Options\General as Options;
use RpsCompetition\Settings;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class Frontend
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend
 */
class Frontend
{
    /** @var Application */
    private $container;
    /** @var Core */
    private $core;
    /** @var \Symfony\Component\Form\FormFactory */
    private $formFactory;
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
    /** @var \Rpscompetition\Frontend\View */
    private $view;

    /**
     * Constructor
     *
     * @param Application $container
     */
    public function __construct(Application $container)
    {
        $this->container = $container;
        $this->session = $container->make('Session');
        $this->session->start();

        $this->settings = $container->make('Settings');
        $this->rpsdb = $container->make('RpsDb');
        $this->request = $container->make('IlluminateRequest');
        $this->options = $container->make('OptionsGeneral');
        $this->core = $container->make('Core');

        $this->view = $container->make('FrontendView');
        $this->formFactory = $container->make('formFactory');

        $this->setupRequestHandling();

        // The actions are in order as how WordPress executes them
        add_action('after_setup_theme', [$this, 'actionAfterThemeSetup'], 14);
        add_action('init', [$this, 'actionInit'], 11);

        add_action('template_redirect', [$this, 'actionTemplateRedirectRpsWindowsClient']);
        add_action('wp_enqueue_scripts', [$this, 'actionEnqueueScripts'], 999);

        add_filter('query_vars', [$this, 'filterQueryVars']);
        add_filter('post_gallery', [$this, 'filterPostGallery'], 10, 2);
        add_filter('_get_page_link', [$this, 'filterPostLink'], 10, 2);
        add_filter('the_title', [$this, 'filterTheTitle'], 10, 2);
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
        add_action('rps_showcase', [$this, 'actionShowcaseCompetitionThumbnails']);
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
        $all_masonry_pages = [];
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

            $entity = new BanquetCurrentUserEntity();
            $form = $this->formFactory->create(
                new BanquetCurrentUserType($entity),
                $entity,
                ['attr' => ['id' => 'banquetentries']]
            )
            ;
            $form->handleRequest($this->request);

            $redirect_to = $entity->getWpGetReferer();
            $this->isRequestCanceled($form, 'cancel', $redirect_to);

            if ($form->get('update')
                     ->isClicked()
            ) {
                $this->handleSubmitBanquetEntries($entity);
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
            $data = [];
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
     * @return string
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
            [
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
            ],
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
            $_attachments = get_posts(
                [
                    'include'        => $include,
                    'post_status'    => 'inherit',
                    'post_type'      => 'attachment',
                    'post_mime_type' => 'image',
                    'order'          => $order,
                    'orderby'        => $orderby
                ]
            );

            $attachments = [];
            foreach ($_attachments as $key => $val) {
                $attachments[$val->ID] = $_attachments[$key];
            }
        } elseif (!empty($exclude)) {
            $attachments = get_children(
                [
                    'post_parent'    => $id,
                    'exclude'        => $exclude,
                    'post_status'    => 'inherit',
                    'post_type'      => 'attachment',
                    'post_mime_type' => 'image',
                    'order'          => $order,
                    'orderby'        => $orderby
                ]
            );
        } else {
            $attachments = get_children(
                [
                    'post_parent'    => $id,
                    'post_status'    => 'inherit',
                    'post_type'      => 'attachment',
                    'post_mime_type' => 'image',
                    'order'          => $order,
                    'orderby'        => $orderby
                ]
            );
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
            $entries = [];
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

        $selector = 'gallery-' . $instance;

        $gallery_style = '';

        $layout = strtolower($layout);

        $size_class = sanitize_html_class($size);
        $gallery_div = '<div id="' . $selector . '" class="gallery galleryid-' . $id . ' gallery-columns-' . $columns . ' gallery-size-' . $size_class . '">';

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

            $output .= '<' . $itemtag . ' class="gallery-item">';
            $output .= '<div class="gallery-item-content">';
            $output .= '<' . $icontag . ' class="gallery-icon ' . $orientation . '" > ' . $image_output . '</' . $icontag . ' >';

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
                $output .= '<' . $captiontag . ' class="wp-caption-text gallery-caption">' . wptexturize(
                        $caption_text
                    ) . '</' . $captiontag . '>';
            }

            $output .= '</div>';
            $output .= '</' . $itemtag . '>';

            if ($columns > 0 && ++$i % $columns == 0) {
                $output .= '</div>';
            }
        }

        if ($columns > 0 && $i % $columns !== 0) {
            $output .= '</div>';
        }
        $output .= '</div>' . "\n";

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
     * Handles the required functions for when a user submits their Banquet Entries
     *
     * @param BanquetCurrentUserEntity $entity
     */
    private function handleSubmitBanquetEntries(BanquetCurrentUserEntity $entity)
    {
        $query_entries = $this->container->make('QueryEntries');
        $query_competitions = $this->container->make('QueryCompetitions');
        $photo_helper = $this->container->make('PhotoHelper');

        $all_entries = json_decode(base64_decode($entity->getAllentries()));
        foreach ($all_entries as $entry_id) {
            $entry = $query_entries->getEntryById($entry_id);
            if ($entry !== null) {
                $query_entries->deleteEntry($entry->ID);
                $photo_helper->deleteEntryFromDisk($entry);
            }
        }

        $entries = (array) $this->request->input('form.entry_id', []);
        foreach ($entries as $entry_id) {
            $entry = $query_entries->getEntryById($entry_id);
            $competition = $query_competitions->getCompetitionByID($entry->Competition_ID);
            $banquet_ids = json_decode(base64_decode($entity->getBanquetids()));
            foreach ($banquet_ids as $banquet_id) {
                $banquet_record = $query_competitions->getCompetitionByID($banquet_id);
                if ($competition->Medium == $banquet_record->Medium && $competition->Classification == $banquet_record->Classification) {
                    // Move the file to its final location
                    $path = $photo_helper->getCompetitionPath(
                        $banquet_record->Competition_Date,
                        $banquet_record->Classification,
                        $banquet_record->Medium
                    )
                    ;
                    CommonHelper::createDirectory($path);
                    $file_info = pathinfo($entry->Server_File_Name);
                    $new_file_name = $path . '/' . $file_info['basename'];
                    $original_filename = html_entity_decode(
                        $this->request->server('DOCUMENT_ROOT') . $entry->Server_File_Name,
                        ENT_QUOTES,
                        get_bloginfo('charset')
                    );
                    // Need to create the destination folder?
                    copy($original_filename, $this->request->server('DOCUMENT_ROOT') . $new_file_name);
                    $data = [
                        'Competition_ID'   => $banquet_record->ID,
                        'Title'            => $entry->Title,
                        'Client_File_Name' => $entry->Client_File_Name,
                        'Server_File_Name' => $new_file_name
                    ];
                    $query_entries->addEntry($data, get_current_user_id());
                }
            }
        }
        unset($query_entries, $query_competitions, $photo_helper);
    }

    /**
     * Check if user pressed cancel and if so redirect the user
     *
     * @param \Symfony\Component\Form\Form $form   The Form that was submitted
     * @param string                       $cancel The field to check for cancellation
     * @param string                       $redirect_to
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
            $rps_competition_css_version = 'abb1385';
            $rps_masonry_version = 'abb1385';
            $masonry_version = '8cfdecd';
            $imagesloaded_version = '8cfdecd';
            $version_separator = '-';
        } else {
            $rps_competition_css_version = '';
            $rps_masonry_version = '';
            $masonry_version = '';
            $imagesloaded_version = '';
            $version_separator = '';
        }

        $rps_masonry_script = 'rps.masonry' . $version_separator . $rps_masonry_version . '.js';
        $masonry_script = 'masonry' . $version_separator . $masonry_version . '.js';
        $imagesloaded_script = 'imagesloaded' . $version_separator . $imagesloaded_version . '.js';
        $rps_competition_style = 'rps-competition' . $version_separator . $rps_competition_css_version . '.css';

        $javascript_directory = $this->settings->get('javascript_dir');
        wp_deregister_script('masonry');
        wp_register_script(
            'masonry',
            CommonHelper::getPluginUrl($masonry_script, $javascript_directory),
            [],
            'to_remove',
            1
        );
        wp_register_script(
            'rps-imagesloaded',
            CommonHelper::getPluginUrl($imagesloaded_script, $javascript_directory),
            ['masonry'],
            'to_remove',
            true
        );
        wp_register_script(
            'rps-masonryInit',
            CommonHelper::getPluginUrl($rps_masonry_script, $javascript_directory),
            ['rps-imagesloaded'],
            'to_remove',
            true
        );

        wp_register_style(
            'rps-competition.fontawesome.style',
            'http://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css"'
        );
        wp_register_style(
            'rps-competition.general.style',
            CommonHelper::getPluginUrl($rps_competition_style, $this->settings->get('css_dir')),
            ['rps-competition.fontawesome.style'],
            'to_remove'
        );
    }

    /**
     * Setup the action to be handles by the Request Controller
     */
    private function setupRequestHandling()
    {
        /** @var \RpsCompetition\Frontend\Requests\RequestController $requests_controller */
        $requests_controller = $this->container->make('RequestController');
        add_action('parse_query', [$requests_controller, 'handleParseQuery']);

        if ($this->request->isMethod('POST')) {
            add_action('wp', [$requests_controller, 'handleWp']);
        }
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
        $shortcode->setContainer($this->container);
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
            $social_buttons_script_version = '8cfdecd';
            $version_separator = '-';
        } else {
            $social_buttons_script_version = '';
            $version_separator = '';
        }
        $data = [];
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
            update_user_meta($user_id, 'rps_class_bw', 'beginner');
            update_user_meta($user_id, 'rps_class_color', 'beginner');
            update_user_meta($user_id, 'rps_class_print_bw', 'beginner');
            update_user_meta($user_id, 'rps_class_print_color', 'beginner');
        }
    }

    /**
     * Setup the filters and action for the plugin WordPress Seo by Yoast
     *
     */
    private function setupWpSeoActionsFilters()
    {
        $wpseo = $this->container->make('WpSeoHelper');
        add_action('wpseo_register_extra_replacements', [$wpseo, 'actionWpseoRegisterExtraReplacements']);
        add_action('wpseo_do_sitemap_competition-entries', [$wpseo, 'actionWpseoSitemapCompetitionEntries']);
        add_action('wpseo_do_sitemap_competition-winners', [$wpseo, 'actionWpseoSitemapCompetitionWinners']);

        add_filter('wpseo_pre_analysis_post_content', [$wpseo, 'filterWpseoPreAnalysisPostsContent'], 10, 2);
        add_filter('wpseo_opengraph_image', [$wpseo, 'filterWpseoOpengraphImage'], 10, 1);
        add_filter('wpseo_metadesc', [$wpseo, 'filterWpseoMetaDescription'], 10, 1);
        add_filter('wpseo_sitemap_index', [$wpseo, 'filterWpseoSitemapIndex']);
        add_filter('wp_title_parts', [$wpseo, 'filterWpTitleParts'], 10, 1);
        add_filter('wpseo_opengraph_title', [$wpseo, 'filterOpenGraphTitle'], 10, 1);
        add_filter('wpseo_sitemap_entry', [$wpseo, 'filterSitemapEntry'], 10, 3);
    }
}
