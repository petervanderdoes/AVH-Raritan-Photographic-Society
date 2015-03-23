<?php
namespace RpsCompetition\Frontend\SocialNetworks;

use RpsCompetition\Definitions\ViewAbstract;

/**
 * Class SocialNetworksView
 *
 * @package   RpsCompetition\Frontend\SocialNetworks
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
final class SocialNetworksView extends ViewAbstract
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
