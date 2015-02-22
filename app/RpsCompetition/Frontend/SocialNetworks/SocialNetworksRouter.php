<?php

namespace RpsCompetition\Frontend\SocialNetworks;

use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Settings;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class SocialNetworksRouter
 *
 * @package RpsCompetition\Frontend\SocialNetworks
 */
final class SocialNetworksRouter

{
    /**
     * @var SocialNetworksController
     */
    private $controller;
    /**
     * @var Settings
     */
    private $settings;

    /**
     * Constructor
     * @param Settings                 $settings
     * @param SocialNetworksController $controller
     */
    public function __construct(Settings $settings, SocialNetworksController $controller)
    {

        $this->settings = $settings;
        $this->controller = $controller;
    }

    /**
     * Initialize for Social Networks
     *
     * Add the action/filter/style/scripts
     *
     * @param array $data
     */
    public function initializeSocialNetworks($data)
    {
        $rps_social_buttons_script = $data['script'];

        wp_register_script('rps-competition.social-buttons.script', CommonHelper::getPluginUrl($rps_social_buttons_script, $this->settings->get('javascript_dir')), array(), 'to_remove', true);
        wp_enqueue_script('rps-competition.social-buttons.script');

        add_action('wp_head', array($this->controller, 'actionWpHead'));
        add_action('wp_footer', array($this->controller, 'actionWpFooter'), 999);
        add_action('suffusion_before_page', array($this->controller, 'actionAddFbRoot'));
        add_action('rps-social-buttons', array($this->controller, 'actionSocialButtons'));
    }
}
