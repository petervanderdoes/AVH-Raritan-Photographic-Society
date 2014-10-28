<?php

namespace RpsCompetition\Libs;


use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Http\Request;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Options\General;
use RpsCompetition\Settings;

class Container
{

    protected $container;
    protected $twig;
    protected $settings;
    protected $options;
    protected $rpsdb;
    protected $session;
    protected $request;

    /**
     * Sets the Container associated with this Controller.
     *
     * @param IlluminateContainer $container
     */
    public function setContainer(IlluminateContainer $container = null)
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
     * @param \Twig_Environment $twig
     */
    public function setTemplateEngine(\Twig_Environment $twig) {
        $this->twig = $twig;
    }

    /**
     * Renders a view.
     *
     * @param string   $view The view name
     * @param array    $data An array of parameters to pass to the view
     *
     * @return string Rendered output
     */
    public function render($view, array $data = array())
    {
        $template = $this->twig->loadTemplate($view);

        return $template->render($data);

    }
}