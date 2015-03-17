<?php
namespace RpsCompetition\Frontend\Shortcodes;

use RpsCompetition\Libs\View;

/**
 * Class ShortcodeView
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes
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
        $this->addTemplateDir($template_dir . '/shortcodes');
    }
}
