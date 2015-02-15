<?php
namespace RpsCompetition\Frontend\Shortcodes;

use RpsCompetition\Libs\View;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

final class ShortcodeView extends View
{
    public function __construct($template_dir, $cache_dir)
    {
        parent::__construct($template_dir, $cache_dir);
    }
}
