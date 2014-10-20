<?php

namespace RpsCompetition\Frontend\SocialNetworks;

use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Frontend\SocialNetworks\View as SocialNetworksView;
use RpsCompetition\Options\General as OptionsGeneral;
use RpsCompetition\Settings;

/**
 * Class SocialNetworksHelper
 *
 * @package RpsCompetition\Frontend\SocialNetworks
 */
class SocialNetworksHelper
{
    private $settings;
    private $view;

    /**
     * Constructor
     *
     * @param View     $view
     * @param Settings $settings
     *
     */
    public function __construct(SocialNetworksView $view, Settings $settings, OptionsGeneral $options)
    {
        $this->settings = $settings;
        $this->view = $view;
        $this->options = $options;
    }

    /**
     * Prepare for rendering of the social buttons.
     * We add this method as we can not add data that might be needed during the rendering.
     * We can not add the data at the hook level.
     *
     * @internal Hook: rps-social-buttons
     *
     */
    public function actionSocialButtons()
    {
        global $post;
        $options = get_option('avh-rps');

        if ($post->post_type == 'page' && ($post->ID == $options['members_page'] || $post->post_parent == $options['members_page'])) {
            return;
        }
        $networks = $this->getNetworks();

        $this->displaySocialButtons($networks);
    }

    /**
     * Preparation for rendering content in the WordPress footer.
     * We add this method as we can not add data that might be needed during the rendering.
     * We can not add the data at the hook level.
     *
     * @internal Hook: wp_footer
     *
     */
    public function actionWpFooter()
    {
        $networks = $this->getNetworks();

        $this->displayWpFooter($networks);
    }

    /**
     * Preparation for rendering content in the WordPress header.
     * We add this method as we can not add data that might be needed during the rendering.
     * We can not add the data at the hook level.
     *
     * @internal Hook: wp_head
     *
     */
    public function actionWpHead()
    {
        $this->displayWpHeader();
    }

    /**
     * Initialize all the scripts, styles and hooks used by the class
     *
     * @param array $data
     *
     */
    public function initClass($data)
    {
        $rps_social_buttons_script = $data['script'];
        $social_buttons_style = $data['style'];

        wp_register_script('rps-competition.social-buttons.script', CommonHelper::getPluginUrl($rps_social_buttons_script, $this->settings->get('javascript_dir')), array(), 'to_remove', true);
        wp_enqueue_script('rps-competition.social-buttons.script');

        wp_enqueue_style('rps-competition.fontawesome.style', 'http://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css"');
        wp_enqueue_style('rps-competition.social-buttons.style', CommonHelper::getPluginUrl($social_buttons_style, $this->settings->get('css_dir')), array(), 'to_remove');

        add_action('wp_head', array($this, 'actionWpHead'));
        add_action('wp_footer', array($this, 'actionWpFooter'), 999);
        add_action('rps-social-buttons', array($this, 'actionSocialButtons'));
    }

    /**
     * Display the social buttons
     *
     * @param array $networks
     * @param array $icons
     *
     */
    private function displaySocialButtons(array $networks, $icons = array())
    {
        $default_icons = array('facebook' => 'facebook-square', 'twitter' => 'twitter', 'googleplus' => 'google-plus', 'email' => 'envelope-o');
        $data = array();

        $network_icons = array_merge($default_icons, $icons);
        $data['url'] = get_permalink();
        $data['id'] = 'share';
        $data['title'] = get_the_title();
        foreach ($networks as $network => $value) {
            $data['networks'][$network] = array('text' => $value['text'], 'icon' => $network_icons[$network]);
        }

        echo $this->view->renderSocialNetworksButtons($data);
    }

    /**
     * Display the content in the WordPress footer
     *
     * @param array $data
     *
     */
    private function displayWpFooter($data)
    {
        echo $this->view->renderWpFooter($data);
    }

    /**
     * Display the content in the WordPress header
     *
     */
    private function displayWpHeader()
    {
        echo $this->view->renderWpHeader();
    }

    /**
     * Get the default social networks data
     *
     * @param array $networks
     *
     * @return array
     */
    private function getNetworks($networks = array())
    {
        $networks['facebook'] = array('text' => 'facebook', 'api' => false);
        $networks['googleplus'] = array('text' => 'google', 'api' => false);
        $networks['twitter'] = array('text' => 'twitter', 'api' => false);
        $networks['email'] = array('text' => 'email', 'api' => false);

        return $networks;
    }
}