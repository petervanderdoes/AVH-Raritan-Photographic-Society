<?php
/**
 * Plugin Name: AVH RPS Competition
 * Plugin URI: http://blog.avirtualhome.com/wordpress-plugins
 * Description: This plugin was written to manage the competitions of the Raritan Photographic Society.
 * Version: 1.2.7-rc.1
 * Author: Peter van der Does
 * Author URI: http://blog.avirtualhome.com/
 *
 * Copyright 2011 Peter van der Does (email : peter@avirtualhome.com)
 */
use RpsCompetition\Constants;
use RpsCompetition\Admin\Initialize;
use RpsCompetition\Admin\Admin;
use RpsCompetition\Frontend\Frontend;
use Avh\Di\Container;

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

    /**
     *
     * @var Avh\Di\Container
     */
    private $container;

    private $settings;

    public function __construct($dir, $basename)
    {
        $this->container = new Container();

        $this->container->register('\RpsCompetition\Settings', null, true);
        $this->container->register('\RpsCompetition\Common\Core')->withArgument('\RpsCompetition\Settings');
        $this->container->register('\RpsCompetition\Db\RpsDb')->withArguments(array('\RpsCompetition\Settings', '\RpsCompetition\Common\Core'));
        $this->container->register('\RpsCompetition\Competition\ListTable')->withArguments(array('\RpsCompetition\Settings', '\RpsCompetition\Db\RpsDb', '\RpsCompetition\Common\Core'));
        $this->container->register('\RpsCompetition\Entries\ListTable')->withArguments(array('\RpsCompetition\Settings', '\RpsCompetition\Db\RpsDb', '\RpsCompetition\Common\Core'));
        $this->container->register('\RpsCompetition\Frontend\Shortcodes')->withArguments(array('\RpsCompetition\Settings', '\RpsCompetition\Db\RpsDb', '\RpsCompetition\Common\Core'));

        $this->settings = $this->container->resolve('\RpsCompetition\Settings');
        $this->settings->plugin_dir = $dir;
        $this->settings->plugin_basename = $basename;
        $this->settings->plugin_url = plugins_url('', Constants::PLUGIN_FILE);

        add_action('plugins_loaded', array($this, 'init'));
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
