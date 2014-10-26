<?php

namespace RpsCompetition\Frontend\SocialNetworks;

use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Frontend\SocialNetworks\View as SocialNetworksView;
use RpsCompetition\Options\General as OptionsGeneral;
use RpsCompetition\Settings;

/**
 * Class SocialNetworksModel
 *
 * @package RpsCompetition\Frontend\SocialNetworks
 */
class SocialNetworksModel
{
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

    /**
     * Return teh networks with the API enabled.
     *
     * @return array
     */
    public function dataApiNetworks()
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
    public function dataSocialButtons(array $networks, $icons = array())
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

}