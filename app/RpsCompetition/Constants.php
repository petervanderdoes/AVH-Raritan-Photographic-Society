<?php
namespace RpsCompetition;

final class Constants
{

    /**
     * General Constants
     */
    const PLUGIN_VERSION = '1.4.0-dev.23';

    const PLUGIN_README_URL = '';

    const PLUGIN_FILE = 'avh-rps-competition/avh-rps-competition.php';

    /**
     * Plugin Specfic Constants
     */
    // Message Numbers

    // Menu Slugs for Admin menu
    const MENU_SLUG = 'avh-rps-plugin';

    const MENU_SLUG_COMPETITION = 'avh-rps-competition';

    const MENU_SLUG_COMPETITION_ADD = 'avh-rps-competition-add';

    const MENU_SLUG_ENTRIES = 'avh-rps-entries';

    const SLUG_COMPETITION_EDIT = 'avh-rps-competition-edit';

    // Menu Positions
    // 25 is the position for comments, so it will fit right under comments
    // @see https://codex.wordpress.org/Function_Reference/add_menu_page
    const MENU_POSITION_COMPETITION = '25.avh-rps-plugin.1';

    const MENU_POSITION_ENTRIES = '25.avh-rps-plugin.2';

    const IMAGE_QUALITY = 90;

    const IMAGE_MAX_WIDTH_ENTRY = 1024;

    const IMAGE_MAX_HEIGHT_ENTRY = 768;

    // @formatter:off
    private static $image_sizes = array (
        'FULL' => array('width'=>1024,'height'=>768),
        '800' => array('width'=>800,'height'=>800),
        '250' => array('width'=>250,'height'=>250),
        '200' => array('width'=>200,'height'=>200),
        '150' => array('width'=>150,'height'=>150),
        '75' => array('width'=>75,'height'=>75),
    );
    // @formatter:on
    public static function get_image_size($size)
    {
        if (key_exists($size, self::$image_sizes)) {
            return self::$image_sizes[(string) $size];
        }
        return null;
    }
}
