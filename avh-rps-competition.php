<?php
/**
 * Plugin Name: AVH RPS Competition
 * Plugin URI: http://blog.avirtualhome.com/wordpress-plugins
 * Description: This plugin was written to manage the competitions of the Raritan Photographic Society.
 * Version: 2.0.4-dev.61
 * Author: Peter van der Does
 * Author URI: http://blog.avirtualhome.com/
 * GitHub Plugin URI: https://github.com/petervanderdoes/AVH-Raritan-Photographic-Society
 * GitHub Branch:     master
 * Copyright 2011-2014 Peter van der Does (email : peter@avirtualhome.com)
 */
use Illuminate\Container\Container;
use RpsCompetition\Admin\Admin;
use RpsCompetition\Common\Core;
use RpsCompetition\Competition\Helper as CompetitionHelper;
use RpsCompetition\Constants;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Frontend\Frontend;
use RpsCompetition\Frontend\Requests;
use RpsCompetition\Frontend\Shortcodes;
use RpsCompetition\Frontend\Shortcodes\ShortcodeController;
use RpsCompetition\Frontend\Shortcodes\ShortcodeModel;
use RpsCompetition\Frontend\Shortcodes\ShortcodeRouter;
use RpsCompetition\Frontend\Shortcodes\ShortcodeView;
use RpsCompetition\Frontend\SocialNetworks\SocialNetworksController;
use RpsCompetition\Frontend\SocialNetworks\SocialNetworksRouter;
use RpsCompetition\Frontend\SocialNetworks\SocialNetworksView;
use RpsCompetition\Frontend\View as FrontendView;
use RpsCompetition\Frontend\WpseoHelper;
use RpsCompetition\Photo\Helper as PhotoHelper;
use RpsCompetition\Season\Helper as SeasonHelper;
use RpsCompetition\Settings;
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
 */
class AVH_RPS_Client
{
    /**
     * @var Container
     */
    private $container;
    /** @var  Settings */
    private $settings;

    /**
     * Constructor.
     *
     * @param string $dir
     * @param string $basename
     */
    public function __construct($dir, $basename)
    {
        $this->container = new Container();

        $this->registerBindings();
        $upload_dir_info = wp_upload_dir();
        $this->settings = $this->container->make('Settings');
        $this->settings->set('plugin_dir', $dir);
        $this->settings->set('plugin_file', $basename);
        $this->settings->set('template_dir', $dir . '/resources/views');
        $this->settings->set('plugin_basename', $basename);
        $this->settings->set('upload_dir', $upload_dir_info['basedir'] . '/avh-rps');
        $this->settings->set('javascript_dir', $dir . '/assets/js/');
        $this->settings->set('css_dir', $dir . '/assets/css/');
        $this->settings->set('images_dir', $dir . '/assets/images/');
        $this->settings->set('plugin_url', plugins_url('', Constants::PLUGIN_FILE));
        if (!defined('WP_INSTALLING') || WP_INSTALLING === false) {
            add_action('plugins_loaded', [$this, 'load']);
        }
    }

    public function load()
    {
        if (is_admin()) {
            add_action('activate_' . $this->settings->get('plugin_basename'), [$this, 'pluginActivation']);
            add_action('deactivate_' . $this->settings->get('plugin_basename'), [$this, 'pluginDeactivation']);

            new Admin($this->container);
        } else {
            new Frontend($this->container);
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
         * Setup Interfaces
         *
         */
        $this->container->bind('Avh\DataHandler\AttributeBagInterface', 'Avh\DataHandler\NamespacedAttributeBag');

        /**
         * Setup Singleton classes
         *
         */
        $this->container->singleton('Settings', 'RpsCompetition\Settings');
        $this->container->singleton('RpsDb', 'RpsCompetition\Db\RpsDb');
        $this->container->singleton('OptionsGeneral', 'RpsCompetition\Options\General');
        $this->container->singleton(
            'Session',
            function () {
                return new Avh\Network\Session(['name' => 'raritan_' . COOKIEHASH]);
            }
        )
        ;
        $this->container->singleton('IlluminateRequest', '\Illuminate\Http\Request');
        $this->container->instance(
            'IlluminateRequest',
            forward_static_call(['Illuminate\Http\Request', 'createFromGlobals'])
        )
        ;

        /**
         * Setup Classes
         *
         */

        $this->container->bind(
            'Core',
            function ($app) {
                return new Core($app->make('Settings'));
            }
        )
        ;
        $this->container->bind(
            'FrontendRequests',
            function ($app) {
                return new Requests(
                    $app->make('Settings'), $app->make('RpsDb'), $app->make('IlluminateRequest'), $app->make('Session')
                );
            }
        )
        ;
        $this->container->bind(
            'FrontendView',
            function ($app) {
                return new FrontendView($app->make('Settings'), $app->make('RpsDb'), $app->make('IlluminateRequest'));
            }
        )
        ;

        $this->container->bind(
            'PhotoHelper',
            function ($app) {
                return new PhotoHelper($app->make('Settings'), $app->make('IlluminateRequest'), $app->make('RpsDb'));
            }
        )
        ;

        $this->container->bind(
            'SeasonHelper',
            function ($app) {
                return new SeasonHelper($app->make('Settings'), $app->make('RpsDb'));
            }
        )
        ;

        $this->container->bind(
            'WpSeoHelper',
            function ($app) {
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

        $this->container->bind(
            'CompetitionHelper',
            function ($app) {
                return new CompetitionHelper($app->make('Settings'), $app->make('RpsDb'));
            }
        )
        ;

        $this->container->bind('HtmlBuilder', '\Avh\Html\HtmlBuilder');

        $this->registerBindingDb();
        $this->registerBindingShortCodes();
        $this->registerBindingSocialNetworks();
        $this->registerBindingsForms();

        $this->container->bind(
            'Templating',
            function ($app, $param) {
                $template_dir = $param['template_dir'];
                $cache_dir = $param['cache_dir'];
                if (WP_LOCAL_DEV !== true) {
                    return new Twig_Environment(new Twig_Loader_Filesystem($template_dir), ['cache' => $cache_dir]);
                } else {
                    return new Twig_Environment(new Twig_Loader_Filesystem($template_dir));
                }
            }
        )
        ;
    }

    /**
     * Register all the binding for the Database classes
     *
     */
    private function registerBindingDb()
    {
        $this->container->bind(
            'QueryEntries',
            function ($app) {
                return new QueryEntries($app->make('RpsDb'));
            }
        )
        ;
        $this->container->bind(
            'QueryCompetitions',
            function ($app) {
                return new QueryCompetitions($app->make('Settings'), $app->make('RpsDb'));
            }
        )
        ;
        $this->container->bind(
            'QueryMiscellaneous',
            function ($app) {
                return new QueryMiscellaneous($app->make('RpsDb'));
            }
        )
        ;
        $this->container->bind(
            'QueryBanquet',
            function ($app) {
                return new QueryBanquet($app->make('RpsDb'));
            }
        )
        ;
    }

    /**
     * Register all the bindings for the Shortcode classes
     */
    private function registerBindingShortCodes()
    {
        // General Shortcode classes
        $this->container->bind(
            'ShortcodeRouter',
            function ($app) {
                return new ShortcodeRouter();
            }
        )
        ;;
        $this->container->bind(
            'ShortcodeController',
            function ($app) {
                return new ShortcodeController($app);
            }
        )
        ;

        $this->container->bind(
            'ShortcodeModel',
            function ($app) {
                return new ShortcodeModel(
                    $app->make('QueryCompetitions'),
                    $app->make('QueryEntries'),
                    $app->make('QueryMiscellaneous'),
                    $app->make('PhotoHelper'),
                    $app->make('SeasonHelper'),
                    $app->make('CompetitionHelper'),
                    $app->make('Session'),
                    $app->make('formFactory'),
                    $app->make('Settings')
                );
            }
        )
        ;

        $this->container->bind(
            'ShortcodeView',
            function ($app) {
                $settings = $app->make('Settings');

                return new ShortcodeView($settings->get('template_dir'), $settings->get('upload_dir') . '/twig-cache/');
            }
        )
        ;

        // My Entries Shortcode
        $this->container->bind(
            'MyEntriesController',
            function ($app) {
                $settings = $app->make('Settings');

                return new Shortcodes\MyEntries\MyEntries(
                    $app->make('ShortcodeView'), $app->make('MyEntriesModel')
                );
            }
        )
        ;

        $this->container->bind(
            'MyEntriesModel',
            function ($app) {
                return new Shortcodes\MyEntries\MyEntriesModel(
                    $app->make('QueryCompetitions'),
                    $app->make('QueryEntries'),
                    $app->make('QueryMiscellaneous'),
                    $app->make('PhotoHelper'),
                    $app->make('SeasonHelper'),
                    $app->make('CompetitionHelper'),
                    $app->make('Session'),
                    $app->make('formFactory'),
                    $app->make('Settings')
                );
            }
        )
        ;
    }

    /**
     * Register all the binding for the SocialNetworks clasess
     */
    private function registerBindingSocialNetworks()
    {
        $this->container->bind(
            'SocialNetworksRouter',
            function ($app) {
                return new SocialNetworksRouter($app->make('Settings'), $app->make('SocialNetworksController'));
            }
        )
        ;
        $this->container->bind(
            'SocialNetworksController',
            function ($app) {
                return new SocialNetworksController($app);
            }
        )
        ;
        $this->container->bind('SocialNetworkModel', 'RpsCompetition\Frontend\SocialNetworks\SocialNetworksModel');
        $this->container->bind(
            'SocialNetworksView',
            function ($app, $param) {
                return new SocialNetworksView($param['template_dir'], $param['cache_dir']);
            }
        )
        ;
    }

    /**
     * Register all the bindings for the Symfony Forms integration.
     */
    private function registerBindingsForms()
    {
        $this->container->bind(
            'formFactory',
            function ($app) {
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
}

new AVH_RPS_Client($rps_dir, $rps_basename);
