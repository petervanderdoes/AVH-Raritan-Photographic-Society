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
if ( !defined('AVH_FRAMEWORK') ) {
    define('AVH_FRAMEWORK', true);
}
/*
 |--------------------------------------------------------------------------
| Register The Composer Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader
| for our application. We just need to utilize it! We'll require it
| into the script here so that we do not have to worry about the
| loading of any our classes "manually". Feels great to relax.
|
*/
require __DIR__ . '/vendor/autoload.php';

$_dir = pathinfo($plugin, PATHINFO_DIRNAME);
$_basename = plugin_basename($plugin);
require_once ( $_dir . '/libs/avh-common.php' );
require_once ( $_dir . '/libs/avh-security.php' );
require_once ( $_dir . '/libs/avh-visitor.php' );

use Rps\Constants;
use Rps\Admin\Initialize;
use Rps\Admin\Admin;
use Rps\Frontend\Frontend;
use DI\ContainerBuilder;

class AVH_RPS_Client
{
    private $container;

    public function __construct ($_dir, $_basename)
    {
        $builder = new ContainerBuilder();
        $builder->setDefinitionCache(new Doctrine\Common\Cache\ArrayCache());
        $builder->useReflection(false);
        $builder->useAnnotations(false);
        // $builder->writeProxiesToFile(true, 'tmp/proxies');
        if ( defined(WP_LOCAL_DEV) && WP_LOCAL_DEV == true ) {
            $builder->setDefinitionsValidation(true);
        } else {
            $builder->setDefinitionsValidation(false);
        }

        $this->container = $builder->build();
        //@format_off
            $dependencies=array (
            'Rps\\Db\\RpsDb' => [
                'constructor' => ['Rps\\Settings','Rps\\Common\\Core'],
            ],
            'Rps\\Competition\\ListCompetition' => [
                'constructor' => ['Rps\\Settings', 'Rps\\Db\\RpsDb','Rps\\Common\\Core'],
            ],
            'Rps\\Entries\\ListEntries' => [
                'constructor' => ['Rps\\Settings', 'Rps\\Db\\RpsDb','Rps\\Common\\Core'],
            ],
            'Rps\\Settings' => array(),
            'Rps\\Common\\Core' => [
                'constructor' => ['Rps\\Settings'],
            ],
        );
        // @format_on
        $this->container->addDefinitions($dependencies);

        $_settings = $this->container->get('Rps\\Settings');
        $_settings->plugin_dir = $_dir;
        $_settings->plugin_basename = $_basename;
        $_settings->plugin_url = plugins_url('', Constants::PLUGIN_FILE);

        add_action('plugins_loaded', array($this,'init'));
    }

    public function init ()
    {
        $_settings = $this->container->get('Rps\\Settings');
        if ( is_admin() ) {
            Initialize::load();
            add_action('wp_loaded', array($this->admin()));
        } else {
            new Frontend($this->container);
        }
    }

    public function admin() {
        $avh_rps_admin = new Admin($this->container);
        // Activation Hook
        register_activation_hook(Constants::PLUGIN_FILE, array($avh_rps_admin,'installPlugin'));
        // Deactivation Hook
        register_deactivation_hook(Constants::PLUGIN_FILE, array($avh_rps_admin,'deactivatePlugin'));
    }
}

new AVH_RPS_Client($_dir, $_basename);
