<?php
namespace RpsCompetition\Frontend\SocialNetworks;

use RpsCompetition\Libs\View;

/**
 * Class SocialNetworksView
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\SocialNetworks
 */
final class SocialNetworksView extends View
{
    /**
     * Constructor
     *
     * @param string $template_dir
     * @param string $cache_dir
     */
    public function __construct($template_dir, $cache_dir)
    {
        parent::__construct($template_dir, $cache_dir);
        $this->addTemplateDir($template_dir . '/social-networks');
    }
}
