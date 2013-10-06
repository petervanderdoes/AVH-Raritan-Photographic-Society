<?php
namespace RpsCompetition;
use Avh\Utility\AVH_Settings;

/**
 * Create separate Settings Registry for the plugin.
 *
 * @author pdoes
 *
 */
final class Settings extends AVH_Settings
{

    // prevent directly access.
    public function __construct()
    {
    }

    // prevent clone.
    public function __clone()
    {
    }
}
