<?php

namespace RpsCompetition\Frontend\SocialNetworks;

use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Settings;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

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

    public function __construct(Settings $settings, SocialNetworksController $controller)
    {

        $this->settings = $settings;
        $this->controller = $controller;
    }

    public function initializeSocialNetworks($data)
    {
        $rps_social_buttons_script = $data['script'];
        $social_buttons_style = $data['style'];

        wp_register_script('rps-competition.social-buttons.script', CommonHelper::getPluginUrl($rps_social_buttons_script, $this->settings->get('javascript_dir')), array(), 'to_remove', true);
        wp_enqueue_script('rps-competition.social-buttons.script');

        wp_enqueue_style('rps-competition.fontawesome.style', 'http://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css"');
        wp_enqueue_style('rps-competition.social-buttons.style', CommonHelper::getPluginUrl($social_buttons_style, $this->settings->get('css_dir')), array(), 'to_remove');

        add_action('wp_head', array($this->controller, 'actionWpHead'));
        add_action('wp_footer', array($this->controller, 'actionWpFooter'), 999);
        add_action('suffusion_before_page', array($this->controller, 'actionAddFbRoot'));
        add_action('rps-social-buttons', array($this->controller, 'actionSocialButtons'));
    }
}