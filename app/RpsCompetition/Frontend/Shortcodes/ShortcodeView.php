<?php
namespace RpsCompetition\Frontend\Shortcodes;

use RpsCompetition\Definitions\ViewAbstract;

/**
 * Class ShortcodeView
 *
 * @package   RpsCompetition\Frontend\Shortcodes
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
final class ShortcodeView extends ViewAbstract
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
