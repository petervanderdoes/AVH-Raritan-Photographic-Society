<?php
namespace RpsCompetition;

final class Constants
{

    /**
     * General Constants
     */
    const PLUGIN_VERSION = '1.4.0-dev.12';

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
}
