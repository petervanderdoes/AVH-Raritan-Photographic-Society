<?php
/**
 * Plugin Name: AVH RPS Competition
 * Plugin URI: http://blog.avirtualhome.com/wordpress-plugins
 * Description: This plugin was written to manage the competitions of the Raritan Photographic Society.
 * Version: 1.5.2
 * Author: Peter van der Does
 * Author URI: http://blog.avirtualhome.com/
 * GitHub Plugin URI: https://github.com/petervanderdoes/AVH-Raritan-Photographic-Society
 * GitHub Branch:     master
 * Copyright 2011-2014 Peter van der Does (email : peter@avirtualhome.com)
 */
use Illuminate\Container\Container;
use RpsCompetition\Admin\Admin;
use RpsCompetition\Constants;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Frontend\Frontend;
use RpsCompetition\Options\General as OptionsGeneral;
use RpsCompetition\Settings;

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

class AVH_RPS_Client
{
    /**
     * @var Container
     */
    private $container;
    /** @var  Settings */
    private $settings;

    public function __construct($dir, $basename)
    {
        $this->container = new Container();

        $this->container->singleton(
            'RpsCompetition\Settings',
            function () {
                return new Settings(new Avh\DataHandler\NamespacedAttributeBag());
            }
        );
        $this->container->singleton(
            'RpsCompetition\Db\RpsDb',
            function () {
                return new RpsDb();
            }
        );
        $this->container->singleton(
            'RpsCompetition\Options\General',
            function () {
                return new OptionsGeneral();
            }
        );

        $this->settings = $this->container->make('RpsCompetition\Settings');
        $this->container->make('RpsCompetition\Db\RpsDb');
        $this->container->make('RpsCompetition\Options\General');
        $this->container->instance('Illuminate\Http\Request', forward_static_call(array('Illuminate\Http\Request', 'createFromGlobals')));
        $this->settings->set('plugin_dir', $dir);
        $this->settings->set('plugin_basename', $basename);
        $this->settings->set('plugin_file', $basename);
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
}

new AVH_RPS_Client($rps_dir, $rps_basename);
