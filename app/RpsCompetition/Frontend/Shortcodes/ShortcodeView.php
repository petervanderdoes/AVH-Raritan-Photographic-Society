<?php
namespace RpsCompetition\Frontend\Shortcodes;

use RpsCompetition\Libs\View;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class ShortcodeView
 *
 * @package RpsCompetition\Frontend\Shortcodes
 */
final class ShortcodeView extends View
{
    /**
     * Contructor
     *
     * @param string $template_dir
     * @param string $cache_dir
     */
    public function __construct($template_dir, $cache_dir)
    {
        parent::__construct($template_dir, $cache_dir);
        $this->addTemplateDir($template_dir . '/forms');
    }
}
