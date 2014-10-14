<?php

namespace RpsCompetition;

use Avh\DataHandler\DataHandler;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class Settings
 *
 * @package RpsCompetition
 */
class Settings extends DataHandler
{
}
