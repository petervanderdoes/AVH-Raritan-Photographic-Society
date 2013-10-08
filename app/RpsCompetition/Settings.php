<?php
namespace RpsCompetition;

/**
 * Create separate Settings Registry for the plugin.
 *
 * @author pdoes
 *
 */
final class Settings extends \Avh\Utility\Settings
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
