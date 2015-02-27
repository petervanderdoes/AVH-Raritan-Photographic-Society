<?php

namespace RpsCompetition\Libs;

use Illuminate\Http\Request;
use RpsCompetition\Application;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Options\General;
use RpsCompetition\Settings;
use Twig_Environment;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class Controller
 *
 * @package RpsCompetition\Libs
 */
class Controller
{
    /** @var  Application $container */
    protected $container;
    /** @var   \RpsCompetition\Options\General $options */
    protected $options;
    /** @var \Illuminate\Http\Request $request */
    protected $request;
    /** @var  \RpsCompetition\Db\RpsDb $rpsdb */
    protected $rpsdb;
    /** @var  \Avh\Network\Session */
    protected $session;
    /** @var  Settings $settings */
    protected $settings;
    /** @var  Twig_Environment */
    protected $twig;

    /**
     * Renders a view.
     *
     * @param string $view The view name
     * @param array  $data An array of parameters to pass to the view
     *
     * @return string Rendered output
     */
    public function render($view, array $data = [])
    {
        $template = $this->twig->loadTemplate($view);

        return $template->render($data);
    }

    /**
     * Sets the Container associated with this Controller.
     *
     * @param Application $container
     */
    public function setContainer(Application $container)
    {
        $this->container = $container;
    }

    /**
     * @param General $options
     */
    public function setOptions(General $options)
    {
        $this->options = $options;
    }

    /**
     * @param Request $request
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @param RpsDb $rpsdb
     */
    public function setRpsdb(RpsDb $rpsdb)
    {
        $this->rpsdb = $rpsdb;
    }

    /**
     * @param $session
     */
    public function setSession($session)
    {
        $this->session = $session;
    }

    /**
     * @param Settings $settings
     */
    public function setSettings(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @param Twig_Environment $twig
     */
    public function setTemplateEngine(Twig_Environment $twig)
    {
        $this->twig = $twig;
    }
}
