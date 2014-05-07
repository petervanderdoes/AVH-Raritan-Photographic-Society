<?php
/**
 * Plugin Name: AVH RPS Competition
 * Plugin URI: http://blog.avirtualhome.com/wordpress-plugins
 * Description: This plugin was written to manage the competitions of the Raritan Photographic Society.
 * Version: 1.4.0-dev.23
 * Author: Peter van der Does
 * Author URI: http://blog.avirtualhome.com/
 * GitHub Plugin URI: https://github.com/petervanderdoes/AVH-Raritan-Photographic-Society
 * GitHub Branch:     master
 * Copyright 2011-2014 Peter van der Does (email : peter@avirtualhome.com)
 */
use Illuminate\Container\Container;
use RpsCompetition\Admin\Admin;
use RpsCompetition\Admin\Initialize;
use RpsCompetition\Constants;
use RpsCompetition\Frontend\Frontend;
use RpsCompetition\Settings;

/**
 * Register The Composer Auto Loader
 *
 * Composer provides a convenient, automatically generated class loader
 * for our application. We just need to utilize it! We'll require it
 * into the script here so that we do not have to worry about the
 * loading of any our classes "manually". Feels great to relax.
 *
 */
require __DIR__ . '/vendor/autoload.php';

$rps_dir = pathinfo($plugin, PATHINFO_DIRNAME);
$rps_basename = plugin_basename($plugin);

class AVH_RPS_Client
{

    /**
     *
     * @var Illuminate\Container\Container
     */
    private $container;

    private $settings;

    public function __construct($dir, $basename)
    {
        $this->container = new Container();

        $this->container->singleton('RpsCompetition\Settings', function ()
        {
            return new Settings();
        });

        $this->settings = $this->container->make('RpsCompetition\Settings');
        $this->container->instance('Illuminate\Http\Request', forward_static_call(array('Illuminate\Http\Request', 'createFromGlobals')));
        $this->settings->plugin_dir = $dir;
        $this->settings->plugin_basename = $basename;
        $this->settings->plugin_url = plugins_url('', Constants::PLUGIN_FILE);

        add_action('plugins_loaded', array($this, 'load'));
    }

    public function load()
    {
        if (is_admin()) {
            Initialize::load();
            add_action('wp_loaded', array($this->admin()));
        } else {
            new Frontend($this->container);
        }
    }

    public function admin()
    {
        new Admin($this->container);
    }
}

new AVH_RPS_Client($rps_dir, $rps_basename);
