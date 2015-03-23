<?php
/**
 * Plugin Name: AVH RPS Competition
 * Plugin URI: http://blog.avirtualhome.com/wordpress-plugins
 * Description: This plugin was written to manage the competitions of the Raritan Photographic Society.
 * Version: 2.0.9-dev.53
 * Author: Peter van der Does
 * Author URI: http://blog.avirtualhome.com/
 * GitHub Plugin URI: https://github.com/petervanderdoes/AVH-Raritan-Photographic-Society
 * GitHub Branch:     master
 * Copyright 2011-2014 Peter van der Does (email : peter@avirtualhome.com)
 */
use RpsCompetition\Admin\Admin;
use RpsCompetition\Application;
use RpsCompetition\Constants;
use RpsCompetition\Frontend\Frontend;
use RpsCompetition\Frontend\Requests\RequestController;
use RpsCompetition\Frontend\Shortcodes\ShortcodeController;
use RpsCompetition\Frontend\Shortcodes\ShortcodeRouter;
use RpsCompetition\Frontend\Shortcodes\ShortcodeView;
use RpsCompetition\Frontend\SocialNetworks\SocialNetworksController;
use RpsCompetition\Frontend\SocialNetworks\SocialNetworksRouter;
use RpsCompetition\Frontend\SocialNetworks\SocialNetworksView;
use RpsCompetition\Frontend\View as FrontendView;
use RpsCompetition\Frontend\WpseoHelper;
use RpsCompetition\Helpers\CompetitionHelper;
use RpsCompetition\Helpers\PhotoHelper;
use RpsCompetition\Helpers\SeasonHelper;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms as SymfonyForms;
use Symfony\Component\Validator\Validation;

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Register The Composer Auto Loader
 * Composer provides a convenient, automatically generated class loader
 * for our application. We just need to utilize it! We'll require it
 * into the script here so that we do not have to worry about the
 * loading of any our classes "manually". Feels great to relax.
 */
require __DIR__ . '/vendor/autoload.php';

$rps_dir = pathinfo($plugin, PATHINFO_DIRNAME);
$rps_basename = plugin_basename($plugin);

/**
 * Class AVH_RPS_Client
 *
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class AVH_RPS_Client
{
    /**
     * @var Application
     */
    private $app;
    /** @var \Illuminate\Config\Repository */
    private $settings;

    /**
     * Constructor.
     *
     * @param string $dir
     * @param string $basename
     */
    public function __construct($dir, $basename)
    {
        $this->app = new Application();

        $this->registerBindings();

        $this->settings = $this->app->make('Settings');
        $this->settings->set('plugin_dir', $dir);
        $this->settings->set('plugin_file', $basename);

        if (!defined('WP_INSTALLING') || WP_INSTALLING === false) {
            add_action('plugins_loaded', [$this, 'load']);
        }
    }

    public function actionInit()
    {
        $this->setupRewriteRules();
        add_image_size('150w', 150, 9999);
    }

    public function load()
    {
        $this->app->make('OptionsGeneral');
        $this->setSettings();
        add_action('init', [$this, 'actionInit'], 10);

        if (is_admin()) {
            add_action('activate_' . $this->settings->get('plugin_basename'), [$this, 'pluginActivation']);
            add_action('deactivate_' . $this->settings->get('plugin_basename'), [$this, 'pluginDeactivation']);

            new Admin($this->app);
        } else {
            new Frontend($this->app);
        }
    }

    /**
     * Runs after we activate the plugin.
     *
     * @internal Hook: activate_
     * @see      AVH_RPS_Client::load
     *
     */
    public function pluginActivation()
    {
        flush_rewrite_rules();
    }

    /**
     * Runs after we deactivate the plugin.
     *
     * @internal Hook: deactivate_
     * @see      AVH_RPS_Client::load
     *
     */
    public function pluginDeactivation()
    {
        flush_rewrite_rules();
    }

    public function registerBindings()
    {

        /**
         * Setup Singleton classes
         *
         */
        $this->app->singleton(
            'Settings',
            function () {
                return new \Illuminate\Config\Repository();
            }
        )
        ;

        $this->app->singleton('OptionsGeneral', 'RpsCompetition\Options\General');
        $this->app->singleton(
            'Session',
            function () {
                return new \Avh\Network\Session(['name' => 'raritan_' . COOKIEHASH]);
            }
        )
        ;
        $this->app->singleton('IlluminateRequest', '\Illuminate\Http\Request');
        $this->app->instance(
            'IlluminateRequest',
            forward_static_call(['Illuminate\Http\Request', 'createFromGlobals'])
        )
        ;

        /**
         * Setup Classes
         *
         */

        $this->app->bind(
            'RequestController',
            function (Application $app) {
                return new RequestController($app);
            }
        )
        ;

        $this->app->bind(
            'FrontendView',
            function (Application $app) {
                return new FrontendView($app->make('Settings'), $app->make('RpsDb'), $app->make('IlluminateRequest'));
            }
        )
        ;

        $this->app->bind(
            'PhotoHelper',
            function (Application $app) {
                return new PhotoHelper($app->make('Settings'), $app->make('IlluminateRequest'), $app->make('RpsDb'));
            }
        )
        ;

        $this->app->bind(
            'SeasonHelper',
            function (Application $app) {
                return new SeasonHelper($app->make('Settings'), $app->make('RpsDb'));
            }
        )
        ;

        $this->app->bind(
            'WpSeoHelper',
            function (Application $app) {
                return new WpseoHelper(
                    $app->make('Settings'),
                    $app->make('RpsDb'),
                    $app->make('QueryCompetitions'),
                    $app->make('QueryMiscellaneous'),
                    $app->make('PhotoHelper')
                );
            }
        )
        ;

        $this->app->bind(
            'CompetitionHelper',
            function (Application $app) {
                return new CompetitionHelper($app->make('Settings'), $app->make('RpsDb'));
            }
        )
        ;

        $this->app->bind('HtmlBuilder', '\Avh\Html\HtmlBuilder');

        $this->registerBindingShortCodes();
        $this->registerBindingSocialNetworks();
        $this->registerBindingsForms();
    }

    /**
     * Register all the bindings for the Shortcode classes
     */
    private function registerBindingShortCodes()
    {
        // General Shortcode classes
        $this->app->bind(
            'ShortcodeRouter',
            function () {
                return new ShortcodeRouter();
            }
        )
        ;
        $this->app->bind(
            'ShortcodeController',
            function (Application $app) {
                return new ShortcodeController($app);
            }
        )
        ;

        $this->app->bind(
            'ShortcodeView',
            function (Application $app) {
                $settings = $app->make('Settings');

                return new ShortcodeView($settings->get('template_dir'), $settings->get('upload_dir') . '/twig-cache/');
            }
        )
        ;
    }

    /**
     * Register all the binding for the SocialNetworks clasess
     */
    private function registerBindingSocialNetworks()
    {
        $this->app->bind(
            'SocialNetworksRouter',
            function (Application $app) {
                return new SocialNetworksRouter($app->make('Settings'), $app->make('SocialNetworksController'));
            }
        )
        ;
        $this->app->bind(
            'SocialNetworksController',
            function (Application $app) {
                return new SocialNetworksController($app);
            }
        )
        ;
        $this->app->bind('SocialNetworksModel', 'RpsCompetition\Frontend\SocialNetworks\SocialNetworksModel');
        $this->app->bind(
            'SocialNetworksView',
            function (Application $app) {
                $settings = $app->make('Settings');

                return new SocialNetworksView(
                    $settings->get('template_dir'), $settings->get('upload_dir') . '/twig-cache/'
                );
            }
        )
        ;
    }

    /**
     * Register all the bindings for the Symfony Forms integration.
     */
    private function registerBindingsForms()
    {
        $this->app->bind(
            'formFactory',
            function (Application $app) {
                $validator_builder = Validation::createValidatorBuilder();
                $validator_builder->setApiVersion(Validation::API_VERSION_2_5);
                $validator_builder->addMethodMapping('loadValidatorMetadata');
                $validator = $validator_builder->getValidator();
                $formFactory = SymfonyForms::createFormFactoryBuilder()
                                           ->addExtension(new ValidatorExtension($validator))
                                           ->addExtension(new HttpFoundationExtension())
                                           ->getFormFactory()
                ;

                return $formFactory;
            }
        )
        ;
    }

    /**
     * Set the required settings to be used throughout the plugin
     */
    private function setSettings()
    {

        $dir = $this->settings->get('plugin_dir');
        $basename = $this->settings->get('plugin_file');
        $upload_dir_info = wp_upload_dir();

        $this->settings->set('template_dir', $dir . '/resources/views');
        $this->settings->set('plugin_basename', $basename);
        $this->settings->set('upload_dir', $upload_dir_info['basedir'] . '/avh-rps');
        $this->settings->set('javascript_dir', $dir . '/assets/js/');
        $this->settings->set('css_dir', $dir . '/assets/css/');
        $this->settings->set('images_dir', $dir . '/assets/images/');
        $this->settings->set('plugin_url', plugins_url('', Constants::PLUGIN_FILE));
        $this->settings->set('club_max_entries_per_member_per_date', 4);
        $this->settings->set('club_max_banquet_entries_per_member', 5);
        $this->settings->set('digital_chair_email', 'digitalchair@raritanphoto.com');

        $this->settings->set('siteurl', get_option('siteurl'));
    }

    /**
     * Setup Rewrite rules
     *
     */
    private function setupRewriteRules()
    {
        $options = get_option('avh-rps');
        $url = get_permalink($options['monthly_entries_post_id']);
        if ($url !== false) {
            $url = substr(parse_url($url, PHP_URL_PATH), 1);
            add_rewrite_rule(
                $url . '?([^/]*)',
                'index.php?page_id=' . $options['monthly_entries_post_id'] . '&selected_date=$matches[1]',
                'top'
            );
        }

        $url = get_permalink($options['monthly_winners_post_id']);
        if ($url !== false) {
            $url = substr(parse_url($url, PHP_URL_PATH), 1);
            add_rewrite_rule(
                $url . '?([^/]*)',
                'index.php?page_id=' . $options['monthly_winners_post_id'] . '&selected_date=$matches[1]',
                'top'
            );
        }

        flush_rewrite_rules();
    }
}

new AVH_RPS_Client($rps_dir, $rps_basename);
