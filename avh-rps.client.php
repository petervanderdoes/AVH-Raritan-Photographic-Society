<?php
// Stop direct call
if ( !defined('AVH_FRAMEWORK') )
    die('You are not allowed to call this page directly.');

/**
 * Initialize the plugin
 */
function avh_RPS_init()
{
    $_settings = AVH_RPS_Settings::getInstance();
    $_settings->storeSetting('plugin_working_dir', pathinfo(__FILE__, PATHINFO_DIRNAME));
    $_settings->storeSetting('plugin_url', plugins_url('', AVH_RPS_Define::PLUGIN_FILE));
    // Admin
    if ( is_admin() ) {
        require_once ( $_settings->plugin_working_dir . '/class/avh-rps.admin.php' );
        $avh_rps_admin = new AVH_RPS_Admin();
        // Activation Hook
        register_activation_hook(__FILE__, array(&$avh_rps_admin,'installPlugin'));
        // Deactivation Hook
        register_deactivation_hook(__FILE__, array(&$avh_rps_admin,'deactivatePlugin'));
    }
    require_once ( $_settings->plugin_working_dir . '/class/avh-rps.public.php' );
    $avhfdas_public = new AVH_RPS_Public();
} // End avh_RPS__init()
add_action('plugins_loaded', 'avh_RPS_init');