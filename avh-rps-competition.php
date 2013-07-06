<?php
/**
 * Plugin Name: AVH RPS Competition
 * Plugin URI: http://blog.avirtualhome.com/wordpress-plugins
 * Description: This plugin was written to manage the competitions of the Raritan Photographic Society.
 * Version: 1.0.1-rc.1
 * Author: Peter van der Does
 * Author URI: http://blog.avirtualhome.com/
 *
 * Copyright 2011 Peter van der Does (email : peter@avirtualhome.com)
*/
if ( !defined('AVH_FRAMEWORK') ) {
	define('AVH_FRAMEWORK', true);
}
$_dir = pathinfo(__FILE__, PATHINFO_DIRNAME);
$_basename = plugin_basename(__FILE__);
require_once ( $_dir . '/libs/avh-registry.php' );
require_once ( $_dir . '/libs/avh-common.php' );
require_once ( $_dir . '/libs/avh-security.php' );
require_once ( $_dir . '/libs/avh-visitor.php' );
require_once ( $_dir . '/class/avh-rps.registry.php' );
require_once ( $_dir . '/class/avh-rps.define.php' );

if ( AVH_Common::getWordpressVersion() >= 3.1 ) {
	$_classes = AVH_RPS_Classes::getInstance();
	$_classes->setDir($_dir);
	$_classes->setClassFilePrefix('avh-rps.');
	$_classes->setClassNamePrefix('AVH_RPS_');
	unset($_classes);

	$_settings = AVH_RPS_Settings::getInstance();
	$_settings->storeSetting('plugin_dir', $_dir);
	$_settings->storeSetting('plugin_basename', $_basename);
	require ( $_dir . '/avh-rps.client.php' );
}
