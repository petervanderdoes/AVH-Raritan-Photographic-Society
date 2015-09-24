<?php
namespace RpsCompetition;

/**
 * Class Constants
 *
 * @package   RpsCompetition
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
final class Constants
{
    const IMAGE_MAX_HEIGHT_ENTRY = 1536;
    const IMAGE_MAX_WIDTH_ENTRY = 2048;
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
    const PLUGIN_VERSION = '2.0.17-dev.5';
    const SLUG_COMPETITION_EDIT = 'avh-rps-competition-edit';
    private static $image_sizes = [
        '75'       => ['width' => 75, 'height' => 75],
        '150'      => ['width' => 150, 'height' => 150],
        '150w'     => ['width' => 150, 'height' => null],
        '200'      => ['width' => 200, 'height' => 200],
        '250'      => ['width' => 250, 'height' => 250],
        '800'      => ['width' => 800, 'height' => 800],
        'fb_thumb' => ['width' => 1200, 'height' => 628],
        'FULL'     => ['width' => self::IMAGE_MAX_WIDTH_ENTRY, 'height' => self::IMAGE_MAX_HEIGHT_ENTRY],
    ];

    /**
     * Return an array with the available classifications.
     *
     * @TODO: This is needed because of the old program, someday it needs to be cleaned up.
     * @return array
     */
    public static function getClassifications()
    {
        return [
            'class_b' => 'Beginner',
            'class_a' => 'Advanced',
            'class_s' => 'Salon'
        ];
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
    public static function getMediums()
    {
        return [
            'medium_bwd' => 'B&W Digital',
            'medium_cd'  => 'Color Digital',
            'medium_bwp' => 'B&W Prints',
            'medium_cp'  => 'Color Prints'
        ];
    }
}
