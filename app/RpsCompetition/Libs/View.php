<?php
namespace RpsCompetition\Libs;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

class View
{
    /**
     * @var array The options for the Twig environment, see http://www.twig-project.org/book/03-Twig-for-Developers
     */
    public $environmentOptions = array();
    /**
     * @var \Twig_Extension The Twig extensions you want to load
     */
    public $parserExtensions = array();
    /**
     * @var \Twig_Environment The Twig environment for rendering templates.
     */
    private $environmentInstance = null;
    /**
     * @var  \Twig_Loader_Filesystem The Twig Loader class.
     */
    private $loader;

    public function __construct($template_dir, $cache_dir)
    {
        $this->loader = new \Twig_Loader_Filesystem($template_dir);
        if (WP_LOCAL_DEV !== true) {
            $this->environmentOptions['cache'] = $cache_dir;
        }
    }

    /**
     * Add Environment Options
     *
     * @param string $key
     * @param mixed  $value
     */
    public function addEnvironmentOptions($key, $value)
    {
        $this->environmentOptions[$key] = $value;
    }

    /**
     * Add template directory
     *
     * @param string $dir
     *
     * @throws \Twig_Error_Loader
     */
    public function addTemplateDir($dir = '')
    {
        $this->loader->addPath($dir);
    }

    /**
     * Display template
     *
     * @param string     $template
     * @param null|array $data
     */
    public function display($template, $data = null)
    {
        echo $this->fetch($template, $data);
    }

    /**
     * Return template
     *
     * @param string     $template
     * @param null|array $data
     *
     * @return string
     *
     */
    public function fetch($template, $data = null)
    {
        return $this->render($template, $data);
    }

    /**
     * Get the Twig Environment
     *
     * @return \Twig_Environment
     */
    private function getEnvironmentInstance()
    {
        if (!$this->environmentInstance) {
            $this->environmentInstance = new \Twig_Environment($this->loader, $this->environmentOptions);
            foreach ($this->parserExtensions as $ext) {
                $extension = is_object($ext) ? $ext : new $ext;
                $this->environmentInstance->addExtension($extension);
            }
        }

        return $this->environmentInstance;
    }

    /**
     * Render the template
     *
     * @param string     $template
     * @param null|array $data
     *
     * @return string
     */
    private function render($template, $data = null)
    {
        $twig = $this->getEnvironmentInstance();
        $parser = $twig->loadTemplate($template);
        return $parser->render($data);
    }
}
