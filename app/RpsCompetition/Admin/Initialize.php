<?php
namespace RpsCompetition\Admin;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class Initialize
 *
 * @package RpsCompetition\Admin
 */
class Initialize
{
    /**
     * Constructor
     *
     */
    public function __construct()
    {
    }

    public static function load()
    {
        // Room to initialize widgets.
    }
}
