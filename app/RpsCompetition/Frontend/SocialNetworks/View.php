<?php
namespace RpsCompetition\Frontend\SocialNetworks;

use RpsCompetition\Settings;
use Twig_Environment;
use Twig_Loader_Filesystem;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class View
 *
 * @package RpsCompetition\Frontend\SocialNetworks
 */
class View
{
    private $settings;
    private $twig;

    /**
     * Constructor
     *
     * @param Settings $settings
     *
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $loader = new Twig_Loader_Filesystem($this->settings->get('template_dir') . '/social-networks');
        if (WP_LOCAL_DEV !== true) {
            $this->twig = new Twig_Environment($loader, array('cache' => $this->settings->get('upload_dir') . '/twig-cache/'));
        } else {
            $this->twig = new Twig_Environment($loader);
        }

    }

    /**
     * Render the content of the social buttons.
     *
     * @param array $data
     *
     * @return string
     */
    public function renderSocialNetworksButtons($data = array())
    {
        $template = $this->twig->loadTemplate('buttons.html.twig');

        return $template->render($data);
    }

    /**
     * Render the content to be used in the WordPress footer.
     *
     * @param array $data
     *
     * @return string
     */
    public function renderWpFooter($data = array())
    {
        $template = $this->twig->loadTemplate('in-footer.html.twig');

        return $template->render($data);
    }

    /**
     * Render the content to be used in the WordPress header.
     *
     * @param array $data
     *
     * @return string
     */
    public function renderWpHeader($data = array())
    {
        $template = $this->twig->loadTemplate('in-header.html.twig');

        return $template->render($data);
    }
}