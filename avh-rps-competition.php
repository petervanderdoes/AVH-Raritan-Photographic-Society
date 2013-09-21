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
require_once ( $_dir . '/libs/avh-registry.php' );
require_once ( $_dir . '/libs/avh-common.php' );
require_once ( $_dir . '/libs/avh-security.php' );
require_once ( $_dir . '/libs/avh-visitor.php' );

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
            'AVH_RPS_OldRpsDb' => [
                'constructor' => ['Rps\\Settings','AVH_RPS_Core'],
            ],
            'AVH_RPS_Define' => array(),
            'Rps\\Competition\\ListCompetition' => [
                'constructor' => ['Rps\\Settings', 'AVH_RPS_OldRpsDb','AVH_RPS_Core'],
            ],
            'Rps\\Entries\\ListEntries' => [
                'constructor' => ['Rps\\Settings', 'AVH_RPS_OldRpsDb','AVH_RPS_Core'],
            ],
            'Rps\\Settings' => array(),
            'AVH_RPS_Admin' => array(),
            'AVH_RPS_Core' => [
                'constructor' => ['Rps\\Settings'],
            ],
        );
        // @format_on
        $this->container->addDefinitions($dependencies);

        $_classes = AVH_RPS_Classes::getInstance();
        $_classes->setDir($_dir);
        $_classes->setClassFilePrefix('avh-rps.');
        $_classes->setClassNamePrefix('AVH_RPS_');
        unset($_classes);

        $_settings = $this->container->get('Rps\\Settings');
        $_settings->plugin_dir = $_dir;
        $_settings->plugin_basename = $_basename;
        $_settings->plugin_url = plugins_url('', AVH_RPS_Define::PLUGIN_FILE);

        add_action('plugins_loaded', array($this,'init'));
    }

    public function init ()
    {
        $_settings = $this->container->get('Rps\\Settings');
        if ( is_admin() ) {
            AVH_RPS_AdminInitialize::load();
            add_action('wp_loaded', array($this->admin()));
        } else {
            require_once ( $_settings->plugin_dir . '/class/avh-rps.public.php' );
            $avhfdas_public = new AVH_RPS_Public($this->container);
        }
    }

    public function admin() {
        $avh_rps_admin = new AVH_RPS_Admin($this->container);
        // Activation Hook
        register_activation_hook(AVH_RPS_Define::PLUGIN_FILE, array($avh_rps_admin,'installPlugin'));
        // Deactivation Hook
        register_deactivation_hook(AVH_RPS_Define::PLUGIN_FILE, array($avh_rps_admin,'deactivatePlugin'));
    }
}

new AVH_RPS_Client($_dir, $_basename);
