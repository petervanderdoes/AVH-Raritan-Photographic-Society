<?php
/**
 * Plugin Name: AVH RPS Competition
 * Plugin URI: http://blog.avirtualhome.com/wordpress-plugins
 * Description: This plugin was written to manage the competitions of the Raritan Photographic Society.
 * Version: 1.3.0-dev.1
 * Author: Peter van der Does
 * Author URI: http://blog.avirtualhome.com/
 *
 * Copyright 2011 Peter van der Does (email : peter@avirtualhome.com)
 */
use Rps\Constants;
use Rps\Admin\Initialize;
use Rps\Admin\Admin;
use Rps\Frontend\Frontend;
use League\Di\Container;

/**
 * |--------------------------------------------------------------------------
 * | Register The Composer Auto Loader
 * |--------------------------------------------------------------------------
 * |
 * | Composer provides a convenient, automatically generated class loader
 * | for our application. We just need to utilize it! We'll require it
 * | into the script here so that we do not have to worry about the
 * | loading of any our classes "manually". Feels great to relax.
 * |
 */
require __DIR__ . '/vendor/autoload.php';

$rps_dir = pathinfo($plugin, PATHINFO_DIRNAME);
$rps_basename = plugin_basename($plugin);

class AVH_RPS_Client
{

    private $container;

    public function __construct($dir, $basename)
    {
        $this->container = new League\Di\Container;

        //$this->container->bind('\Rps\Settings');
        $this->container->bind('\Rps\Common\Core')->addArg('\Rps\Settings');
        $this->container->bind('\Rps\Db\RpsDb')->addArg('\Rps\Settings','\Rps\Common\Core');
        $this->container->bind('\Rps\Competition\ListCompetition')->addArg('\Rps\Settings', '\Rps\Db\RpsDb','\Rps\Common\Core');
        $this->container->bind('\Rps\Entries\ListEntries')->addArg('\Rps\Settings', '\Rps\Db\RpsDb','\Rps\Common\Core');

        $settings = $this->container->resolve('\Rps\Settings');
        $settings->plugin_dir = $dir;
        $settings->plugin_basename = $basename;
        $settings->plugin_url = plugins_url('', Constants::PLUGIN_FILE);

        add_action('plugins_loaded', array($this,'init'));
    }

    public function init()
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
