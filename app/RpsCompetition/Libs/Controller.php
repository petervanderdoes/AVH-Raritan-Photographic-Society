<?php

namespace RpsCompetition\Libs;


use Illuminate\Container\Container as IlluminateContainer;

class Container
{

    protected $container;
    protected $twig;
    protected $settings;
    protected $options;

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
     * @param mixed $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    /**
     * @param mixed $settings
     */
    public function setSettings($settings)
    {
        $this->settings = $settings;
    }

    public function setTemplateEngine(\Twig_Environment $twig) {
        $this->twig = $twig;
    }

    /**
     * Renders a view.
     *
     * @param string   $view       The view name
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