<?php
namespace RpsCompetition;

use Avh\Utility\SettingsAbstract;

/**
 * Create separate Settings Registry for the plugin.
 */
final class Settings extends SettingsAbstract
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
