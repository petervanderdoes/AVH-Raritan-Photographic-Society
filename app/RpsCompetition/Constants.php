<?php
namespace RpsCompetition;

/**
 * Class Constants
 *
 * @package   RpsCompetition
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
final class Constants
{
    /**
     *  Image Constants
     */
    const IMAGE_CLIENT_SIZE      = '1400';
    const IMAGE_MAX_HEIGHT_ENTRY = 1536;
    const IMAGE_MAX_WIDTH_ENTRY  = 2048;
    const IMAGE_QUALITY          = 90;
    /**
     * Menu Positions
     * 25 is the position for comments, so it will fit right under comments
     *
     * @see https://codex.wordpress.org/Function_Reference/add_menu_page
     */
    const MENU_POSITION_COMPETITION = '25.avh-rps-plugin.1';
    const MENU_POSITION_ENTRIES     = '25.avh-rps-plugin.2';
    /**
     * Menu Slugs
     */
    const MENU_SLUG                 = 'avh-rps-plugin';
    const MENU_SLUG_COMPETITION     = 'avh-rps-competition';
    const MENU_SLUG_COMPETITION_ADD = 'avh-rps-competition-add';
    const MENU_SLUG_ENTRIES         = 'avh-rps-entries';
    /**
     * Plugin constants
     */
    const PLUGIN_FILE           = 'avh-rps-competition/avh-rps-competition.php';
    const PLUGIN_README_URL     = '';
    const PLUGIN_VERSION        = '3.0.2-dev.8';
    const SLUG_COMPETITION_EDIT = 'avh-rps-competition-edit';

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
