<?php
namespace RpsCompetition\Common;

use RpsCompetition\Settings;

class Core
{
    /**
     * Comments used in HTML do identify the plugin
     *
     * @var string
     */
    private $comment;
    private $options;
    private $settings;

    /**
     * PHP5 constructor
     *
     * @param Settings $settings
     *
     * @internal param Request $request
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->options = get_option('avh-rps');

        $this->handleInitializePlugin();

        return;
    }

    /**
     * @param string $str
     *
     * @return string
     */
    public function getComment($str = '')
    {
        return $this->comment . ' ' . trim($str) . ' -->';
    }

    /**
     * Convert a shorthand size to bytes.
     *
     * @param $size_str
     *
     * @return integer
     */
    public function getShorthandToBytes($size_str)
    {
        switch (substr($size_str, -1)) {
            case 'M':
            case 'm':
                return (int) $size_str * 1048576;
            case 'K':
            case 'k':
                return (int) $size_str * 1024;
            case 'G':
            case 'g':
                return (int) $size_str * 1073741824;
            default:
                return $size_str;
        }
    }

    /**
     * Initialize the plugin
     * Set the required settings to be used throughout the plugin
     */
    public function handleInitializePlugin()
    {
        // $old_db_version = get_option('avhrps_db_version', 0);
        //$this->settings->club_name = "Raritan Photographic Society";
        //$this->settings->club_short_name = "RPS";
        $this->settings->set('club_max_entries_per_member_per_date', 4);
        $this->settings->set('club_max_banquet_entries_per_member', 5);
        $this->settings->set('digital_chair_email', 'digitalchair@raritanphoto.com');

        $this->settings->set('siteurl', get_option('siteurl'));
        $this->settings->set('graphics_url', plugins_url('images', $this->settings->get('plugin_basename')));
        $this->settings->set('js_url', plugins_url('js', $this->settings->get('plugin_basename')));
        $this->settings->set('css_url', plugins_url('css', $this->settings->get('plugin_basename')));
    }
}
