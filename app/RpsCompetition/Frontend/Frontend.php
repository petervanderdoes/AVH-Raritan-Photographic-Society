<?php
namespace RpsCompetition\Frontend;

use Avh\Framework\Network\Session;
use Illuminate\Config\Repository as Settings;
use Illuminate\Http\Request;
use RpsCompetition\Application;
use RpsCompetition\Frontend\Shortcodes\ShortcodeRouter;
use RpsCompetition\Helpers\CommonHelper;

/**
 * Class Frontend
 *
 * @package   RpsCompetition\Frontend
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class Frontend
{
    private $app;
    /** @var \Rpscompetition\Frontend\FrontendModel */
    private $model;
    /** @var Request */
    private $request;
    /** @var Session */
    private $session;
    /** @var Settings */
    private $settings;
    /** @var \Rpscompetition\Frontend\FrontendView */
    private $view;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app     = $app;
        $this->session = $app->make('Session');
        $this->session->start();

        $this->settings = $app->make('Settings');
        $this->request  = $app->make('IlluminateRequest');

        $this->view  = $app->make('FrontendView');
        $this->model = $app->make('FrontendModel');

        $this->setupRequestHandling();

        // The actions are in order as how WordPress executes them
        $this->setupActionsFilters();
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->session->save();
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

        $options                                                = get_option('avh-rps');
        $all_masonry_pages                                      = [];
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

        $query_competitions = $this->app->make('QueryCompetitions');
        $query_competitions->setAllPastCompetitionsClose();

        $this->setupWpSeoActionsFilters();
        $this->setupUserMeta();
        $this->setupSocialButtons();

        $this->registerScriptsStyles();

        unset($query_competitions);
    }

    /**
     * Display the showcase on the front page.
     * This will display the showcase as used on the front page.
     *
     * @internal Hook: rps_showcase
     * @see      actionAfterThemeSetup
     *
     * @param null $foo
     */
    public function actionShowcaseCompetitionThumbnails($foo)
    {
        if (is_front_page()) {
            $query_miscellaneous = $this->app->make('QueryMiscellaneous');
            $records             = $query_miscellaneous->getEightsAndHigher(5);
            $data                = $this->model->getShowcaseData($records, '150');
            $this->view->renderShowcaseCompetitionThumbnails($data);
            unset($query_miscellaneous);
        }
    }

    /**
     * Filter the output of the standard WordPress gallery.
     * Through this filter we create our own gallery layout.
     *
     * @param string $output   The gallery output. Default empty.
     * @param array  $attr     Attributes of the gallery shortcode.
     * @param int    $instance Unique numeric ID of this gallery shortcode instance.
     *
     * @return string
     */
    public function filterPostGallery($output, $attr, $instance)
    {
        $post = get_post();

        // We're trusting author input, so let's at least make sure it looks like a valid orderby statement
        if (isset($attr['orderby'])) {
            $attr['orderby'] = sanitize_sql_orderby($attr['orderby']);
            if (!$attr['orderby']) {
                unset($attr['orderby']);
            }
        }

        $short_code_atts = shortcode_atts([
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
                                          'gallery');

        $id = (int)($short_code_atts['id']);
        if ('RAND' == $short_code_atts['order']) {
            $short_code_atts['orderby'] = 'none';
        }

        $attachments = $this->model->getPostGalleryAttachments($short_code_atts, $id);

        if (empty($attachments)) {
            return '';
        }

        /**
         * Check if we ran the filter filterWpseoPreAnalysisPostsContent.
         *
         * @see \RpsCompetition\Frontend\WpseoHelper::filterWpseoPreAnalysisPostsContent
         */
        $didFilterWpseoPreAnalysisPostsContent = $this->settings->get('didFilterWpseoPreAnalysisPostsContent', false);

        if (!$didFilterWpseoPreAnalysisPostsContent) {
            $data = $this->model->getFacebookData($attachments);
            /**
             * The output is just a list of img tags with source set to Facebook thumbnails.
             * This soutput is used by WordPressSeo to create Facebook meta tags
             *
             * @see: \WPSEO_OpenGraph_Image::get_content_images
             */
            $output = $this->view->renderFacebookThumbs($data);

            return $output;
        }

        if (is_feed()) {
            $output = $this->view->renderPostGalleryFeed($attachments, $short_code_atts['size']);

            return $output;
        }

        if (strtolower($short_code_atts['layout']) == 'masonry') {
            $data   = $this->model->getPostGalleryMasonryData($attachments);
            $output = $this->view->renderGalleryMasonry($data);

            return $output;
        }

        $data   = $this->model->getPostGalleryData($short_code_atts, $id, $instance, $attachments);
        $output = $this->view->renderPostGallery($data);

        return $output;
    }

    /**
     * Change the permalink for the dynamic pages.
     *
     * @param string $link
     * @param int    $post_id
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
     * @param string $title
     * @param int    $post_id
     *
     * @return string
     */
    public function filterTheTitle($title, $post_id)
    {
        $pages_array = CommonHelper::getDynamicPages();
        if (isset($pages_array[$post_id])) {
            $query_competitions = $this->app->make('QueryCompetitions');
            $selected_date      = get_query_var('selected_date');
            $competitions       = $query_competitions->getCompetitionByDates($selected_date);
            $competition        = current($competitions);
            $theme              = ucfirst($competition->Theme);
            $date               = new \DateTime($selected_date);
            $date_text          = $date->format('F j, Y');
            $title .= ' for the theme "' . $theme . '" on ' . $date_text;
        }

        return $title;
    }

    /**
     * Register all javascript and css files for use in WordPress
     */
    private function registerScriptsStyles()
    {
        if (WP_LOCAL_DEV !== true) {
            $rps_competition_css_version = 'a06a6dd';
            $rps_masonry_version         = 'a172153';
            $masonry_version             = 'f833162';
            $imagesloaded_version        = 'bce608e';
            $version_separator           = '-';
        } else {
            $rps_competition_css_version = '';
            $rps_masonry_version         = '';
            $masonry_version             = '';
            $imagesloaded_version        = '';
            $version_separator           = '';
        }

        $rps_masonry_script    = 'rps.masonry' . $version_separator . $rps_masonry_version . '.js';
        $masonry_script        = 'masonry' . $version_separator . $masonry_version . '.js';
        $imagesloaded_script   = 'imagesloaded' . $version_separator . $imagesloaded_version . '.js';
        $rps_competition_style = 'rps-competition' . $version_separator . $rps_competition_css_version . '.css';

        $javascript_directory = $this->settings->get('javascript_dir');
        wp_deregister_script('masonry');
        wp_register_script('masonry',
                           CommonHelper::getPluginUrl($masonry_script, $javascript_directory),
                           [],
                           'to_remove',
                           1);
        wp_register_script('rps-imagesloaded',
                           CommonHelper::getPluginUrl($imagesloaded_script, $javascript_directory),
                           ['masonry'],
                           'to_remove',
                           true);
        wp_register_script('rps-masonryInit',
                           CommonHelper::getPluginUrl($rps_masonry_script, $javascript_directory),
                           ['rps-imagesloaded'],
                           'to_remove',
                           true);
        if (is_ssl()) {
            $font_awesome = 'https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css';
        } else {
            $font_awesome = 'http://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css';
        }
        wp_register_style('rps-competition.fontawesome.style', $font_awesome);
        wp_register_style('rps-competition.general.style',
                          CommonHelper::getPluginUrl($rps_competition_style, $this->settings->get('css_dir')),
                          ['rps-competition.fontawesome.style'],
                          'to_remove');
    }

    /**
     * Set up all needed actions and filters
     */
    private function setupActionsFilters()
    {
        add_action('after_setup_theme', [$this, 'actionAfterThemeSetup'], 14);
        add_action('init', [$this, 'actionInit'], 11);

        add_action('wp_enqueue_scripts', [$this, 'actionEnqueueScripts'], 999);

        add_filter('query_vars', [$this, 'filterQueryVars']);
        add_filter('post_gallery', [$this, 'filterPostGallery'], 10, 3);
        add_filter('_get_page_link', [$this, 'filterPostLink'], 10, 2);
        add_filter('the_title', [$this, 'filterTheTitle'], 10, 2);
    }

    /**
     * Setup the action to be handles by the Request Controller
     */
    private function setupRequestHandling()
    {
        /** @var \RpsCompetition\Frontend\Requests\RequestController $requests_controller */
        $requests_controller = $this->app->make('RequestController');
        add_action('parse_query', [$requests_controller, 'handleParseQuery']);

        if ($this->request->isMethod('POST')) {
            add_action('wp', [$requests_controller, 'handleWp']);
        }
        if ($this->request->has('rpswinclient')) {
            add_action('template_redirect', [$requests_controller, 'handleTemplateRedirect']);
        }
    }

    /**
     * Setup shortcodes.
     * Setup all the need shortcodes.
     */
    private function setupShortcodes()
    {
        /** @var ShortcodeRouter $shortcode */
        $shortcode = $this->app->make('ShortcodeRouter');
        $shortcode->setShortcodeController($this->app->make('ShortcodeController'));
        $shortcode->setContainer($this->app);
        $shortcode->initializeShortcodes();
    }

    /**
     * Setup the Social Buttons.
     */
    private function setupSocialButtons()
    {
        $social_networks_controller = $this->app->make('SocialNetworksRouter');

        if (WP_LOCAL_DEV !== true) {
            $social_buttons_script_version = 'a172153';
            $version_separator             = '-';
        } else {
            $social_buttons_script_version = '';
            $version_separator             = '';
        }
        $data           = [];
        $data['script'] = 'rps-competition.social-buttons' .
                          $version_separator .
                          $social_buttons_script_version .
                          '.js';
        $social_networks_controller->initializeSocialNetworks($data);
    }

    /**
     * Setup the needed user meta information.
     */
    private function setupUserMeta()
    {
        $user_id   = get_current_user_id();
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
     */
    private function setupWpSeoActionsFilters()
    {
        $wpseo = $this->app->make('WpSeoHelper');
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
