<?php
namespace RpsCompetition\Frontend\SocialNetworks;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class SocialNetworksModel
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\SocialNetworks
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
    public function getNetworks($networks = [])
    {
        $networks['facebook'] = ['text' => 'facebook', 'api' => true];
        $networks['googleplus'] = ['text' => 'google', 'api' => false];
        $networks['twitter'] = ['text' => 'twitter', 'api' => false];
        $networks['email'] = ['text' => 'email', 'api' => false];

        return $networks;
    }

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
    public function getSocialButtons(array $networks, $icons = [])
    {
        $default_icons = [
            'facebook'   => 'facebook-square',
            'twitter'    => 'twitter',
            'googleplus' => 'google-plus',
            'email'      => 'envelope-o'
        ];
        $data = [];

        $network_icons = array_merge($default_icons, $icons);
        $data['url'] = get_permalink();
        $data['id'] = 'share';
        $data['title'] = get_the_title();
        foreach ($networks as $network => $value) {
            $data['networks'][$network] = ['text' => $value['text'], 'icon' => $network_icons[$network]];
        }

        return $data;
    }
}
