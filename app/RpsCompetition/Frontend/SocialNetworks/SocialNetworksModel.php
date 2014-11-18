<?php

namespace RpsCompetition\Frontend\SocialNetworks;

use RpsCompetition\Frontend\SocialNetworks\View as SocialNetworksView;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class SocialNetworksModel
 *
 * @package RpsCompetition\Frontend\SocialNetworks
 */
class SocialNetworksModel
{
    /**
     * Return the networks with the API enabled.
     *
     * @return array
     */
    public function getNetworksWithApiEnabled()
    {
        $networks = $this->getNetworks();

        $data = [];
        foreach ($networks as $network => $value) {
            if ($value['api'] === true) {
                $data['networks'][] = $network;
            }
        }

        return $data;
    }

    /**
     * Display the social buttons
     *
     * @param array $networks
     * @param array $icons
     *
     * @return array
     */
    public function getSocialButtons(array $networks, $icons = array())
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

        return $data;
    }

    /**
     * Get the default social networks data
     *
     * @param array $networks
     *
     * @return array
     */
    public function getNetworks($networks = array())
    {
        $networks['facebook'] = array('text' => 'facebook', 'api' => true);
        $networks['googleplus'] = array('text' => 'google', 'api' => false);
        $networks['twitter'] = array('text' => 'twitter', 'api' => false);
        $networks['email'] = array('text' => 'email', 'api' => false);

        return $networks;
    }
}