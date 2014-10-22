<?php
namespace RpsCompetition;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class Constants
 *
 * @package RpsCompetition
 */
final class Constants
{
    const IMAGE_MAX_HEIGHT_ENTRY = 768;
    const IMAGE_MAX_WIDTH_ENTRY = 1024;
    const IMAGE_QUALITY = 90;
    /**
     * Plugin Specfic Constants
     */
    // Message Numbers

    // Menu Slugs for Admin menu
    const MENU_POSITION_COMPETITION = '25.avh-rps-plugin.1';
    const MENU_POSITION_ENTRIES = '25.avh-rps-plugin.2';
    const MENU_SLUG = 'avh-rps-plugin';
    const MENU_SLUG_COMPETITION = 'avh-rps-competition';
    const MENU_SLUG_COMPETITION_ADD = 'avh-rps-competition-add';
    // Menu Positions
    // 25 is the position for comments, so it will fit right under comments
    // @see https://codex.wordpress.org/Function_Reference/add_menu_page
    const MENU_SLUG_ENTRIES = 'avh-rps-entries';
    const PLUGIN_FILE = 'avh-rps-competition/avh-rps-competition.php';
    const PLUGIN_README_URL = '';
    /**
     * General Constants
     */
    const PLUGIN_VERSION = '2.0.2';
    const SLUG_COMPETITION_EDIT = 'avh-rps-competition-edit';
    // @formatter:off
    private static $image_sizes = array (
        '75' => array('width'=>75,'height'=>75),
        '150' => array('width'=>150,'height'=>150),
        '150w' => array('width'=>150,'height'=>null),
        '200' => array('width'=>200,'height'=>200),
        '250' => array('width'=>250,'height'=>250),
        '800' => array('width'=>800,'height'=>800),
        'fb_thumb' => array('width'=>1200,'height'=>628),
        'FULL' => array('width'=>1024,'height'=>768),
    );
    // @formatter:on

    /**
     * Return an array with the available classifications.
     *
     * @TODO: This is needed because of the old program, someday it needs to be cleaned up.
     * @return array
     */
    static public function getClassifications()
    {
        return array(
            'class_b' => 'Beginner',
            'class_a' => 'Advanced',
            'class_s' => 'Salon'
        );
    }

    /**
     * Returns the width and height for the given image size.
     *
     * @param string $size
     *
     * @return array
     */
    public static function getImageSize($size)
    {
        if (array_key_exists($size, self::$image_sizes)) {
            return self::$image_sizes[(string) $size];
        }

        return null;
    }

    /**
     * Return an array with the available mediums.
     *
     * @TODO: This is needed because of the old program, someday it needs to be cleaned up.
     * @return array
     */
    static public function getMediums()
    {
        return array(
            'medium_bwd' => 'B&W Digital',
            'medium_cd'  => 'Color Digital',
            'medium_bwp' => 'B&W Prints',
            'medium_cp'  => 'Color Prints'
        );
    }
}
