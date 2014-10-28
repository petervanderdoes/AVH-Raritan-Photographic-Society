<?php
/**
 * Created by PhpStorm.
 * User: pdoes
 * Date: 10/27/14
 * Time: 3:37 PM
 */

namespace RpsCompetition\Frontend\Shortcodes;


use Avh\Utility\ShortcodesAbstract;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

class ShortcodeRouter  extends ShortcodesAbstract{

} 