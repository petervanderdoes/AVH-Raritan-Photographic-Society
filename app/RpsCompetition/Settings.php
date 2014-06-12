<?php
namespace RpsCompetition;

// ---------- Private methods ----------
/**
 * Create separate Settings Registry for the plugin.
 *
 * @author pdoes
 *
 */
final class Settings extends \Avh\Utility\SettingsAbstract
{

    // prevent directly access.
    public function __construct()
    {
    }

    // prevent clone.
// ---------- Public methods ----------
    public function __clone()
    {
    }
}
