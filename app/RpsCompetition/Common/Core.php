<?php
namespace RpsCompetition\Common;

use RpsCompetition\Settings;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class Core
 *
 * @package RpsCompetition\Common
 */
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
     * Constructor
     *
     * @param Settings $settings
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;

        $this->options = get_option('avh-rps');

        $this->handleInitializePlugin();
        add_action('init', [$this, 'actionInit'], 10);

        return;
    }

    public function actionInit()
    {
        $this->setupRewriteRules();
        add_image_size('150w', 150, 9999);
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
    }

    /**
     * Setup Rewrite rules
     *
     */
    private function setupRewriteRules()
    {
        $options = get_option('avh-rps');
        $url = get_permalink($options['monthly_entries_post_id']);
        if ($url !== false) {
            $url = substr(parse_url($url, PHP_URL_PATH), 1);
            add_rewrite_rule(
                $url . '?([^/]*)',
                'index.php?page_id=' . $options['monthly_entries_post_id'] . '&selected_date=$matches[1]',
                'top'
            );
        }

        $url = get_permalink($options['monthly_winners_post_id']);
        if ($url !== false) {
            $url = substr(parse_url($url, PHP_URL_PATH), 1);
            add_rewrite_rule(
                $url . '?([^/]*)',
                'index.php?page_id=' . $options['monthly_winners_post_id'] . '&selected_date=$matches[1]',
                'top'
            );
        }

        flush_rewrite_rules();
    }
}
