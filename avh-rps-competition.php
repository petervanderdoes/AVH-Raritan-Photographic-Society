<?php
/**
 * Plugin Name: AVH RPS Competition
 * Plugin URI: http://blog.avirtualhome.com/wordpress-plugins
 * Description: This plugin was written to manage the competitions of the Raritan Photographic Society.
 * Version: 2.0.3-dev.1
 * Author: Peter van der Does
 * Author URI: http://blog.avirtualhome.com/
 * GitHub Plugin URI: https://github.com/petervanderdoes/AVH-Raritan-Photographic-Society
 * GitHub Branch:     master
 * Copyright 2011-2014 Peter van der Does (email : peter@avirtualhome.com)
 */
use Illuminate\Container\Container;
use RpsCompetition\Admin\Admin;
use RpsCompetition\Constants;
use RpsCompetition\Frontend\Frontend;
use RpsCompetition\Frontend\View as FrontendView;
use RpsCompetition\Settings;

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

        $this->setupContainer();
        $upload_dir_info = wp_upload_dir();
        $this->settings = $this->container->make('Settings');
        $this->settings->set('plugin_dir', $dir);
        $this->settings->set('plugin_file', $basename);
        $this->settings->set('template_dir', $dir . '/resources/views/');
        $this->settings->set('plugin_basename', $basename);
        $this->settings->set('upload_dir', $upload_dir_info['basedir'] . '/avh-rps');
        $this->settings->set('javascript_dir', $dir . '/assets/js/');
        $this->settings->set('css_dir', $dir . '/assets/css/');
        $this->settings->set('images_dir', $dir . '/assets/images/');
        $this->settings->set('plugin_url', plugins_url('', Constants::PLUGIN_FILE));
        if (!defined('WP_INSTALLING') || WP_INSTALLING === false) {
            add_action('plugins_loaded', array($this, 'load'));
        }
    }

    public function load()
    {
        if (is_admin()) {
            add_action('activate_' . $this->settings->get('plugin_basename'), array($this, 'pluginActivation'));
            add_action('deactivate_' . $this->settings->get('plugin_basename'), array($this, 'pluginDeactivation'));

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

    public function setupContainer()
    {
        /**
         * Run instances
         *
         */
        $this->container->instance('IlluminateRequest', forward_static_call(array('Illuminate\Http\Request', 'createFromGlobals')));

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
                return new Avh\Network\Session(array('name' => 'raritan_' . COOKIEHASH));
            }
        );

        /**
         * Setup Classes
         *
         */

        $this->container->bind(
            'Core',
            function ($app) {
                return new \RpsCompetition\Common\Core($app->make('Settings'));
            }
        );
        $this->container->bind(
            'FrontendRequests',
            function ($app) {
                return new RpsCompetition\Frontend\Requests($app->make('Settings'), $app->make('RpsDb'), $app->make('IlluminateRequest'), $app->make('Session'));
            }
        );
        $this->container->bind(
            'FrontendView',
            function ($app) {
                return new FrontendView($app->make('Settings'), $app->make('RpsDb'), $app->make('IlluminateRequest'));
            }
        );
        $this->container->bind(
            'QueryEntries',
            function ($app) {
                return new RpsCompetition\Db\QueryEntries($app->make('RpsDb'));
            }
        );
        $this->container->bind(
            'QueryCompetitions',
            function ($app) {
                return new RpsCompetition\Db\QueryCompetitions($app->make('Settings'), $app->make('RpsDb'));
            }
        );
        $this->container->bind(
            'QueryMiscellaneous',
            function ($app) {
                return new RpsCompetition\Db\QueryMiscellaneous($app->make('RpsDb'));
            }
        );
        $this->container->bind(
            'PhotoHelper',
            function ($app) {
                return new RpsCompetition\Photo\Helper($app->make('Settings'), $app->make('IlluminateRequest'), $app->make('RpsDb'));
            }
        );
    }
}

new AVH_RPS_Client($rps_dir, $rps_basename);
