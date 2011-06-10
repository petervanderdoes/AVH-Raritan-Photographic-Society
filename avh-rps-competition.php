<?php
/*
Plugin Name: AVH RPS Competition
Plugin URI: http://blog.avirtualhome.com/wordpress-plugins
Description: This plugin was written to manage the competitions of the Raritan Photographic Society.
Version: 1.0
Author: Peter van der Does
Author URI: http://blog.avirtualhome.com/

Copyright 2011  Peter van der Does  (email : peter@avirtualhome.com)

*/
if (! defined('AVH_FRAMEWORK')) {
	define('AVH_FRAMEWORK', true);
}
$_dir = pathinfo(__FILE__, PATHINFO_DIRNAME);
$_basename = plugin_basename(__FILE__);
require_once ($_dir . '/libs/avh-registry.php');
require_once ($_dir . '/libs/avh-common.php');
require_once ($_dir . '/libs/avh-security.php');
require_once ($_dir . '/libs/avh-visitor.php');
require_once ($_dir . '/class/avh-fdas.registry.php');
require_once ($_dir . '/class/avh-fdas.define.php');

if (AVH_Common::getWordpressVersion() >= 3.1) {
	$_classes = AVH_FDAS_Classes::getInstance();
	$_classes->setDir($_dir);
	$_classes->setClassFilePrefix('avh-fdas.');
	$_classes->setClassNamePrefix('AVH_FDAS_');
	unset($_classes);
	
	$_settings = AVH_FDAS_Settings::getInstance();
	$_settings->storeSetting('plugin_dir', $_dir);
	$_settings->storeSetting('plugin_basename', $_basename);
	require ($_dir . '/avh-rps.client.php');
}
