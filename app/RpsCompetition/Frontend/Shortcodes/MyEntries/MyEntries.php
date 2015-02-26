<?php

namespace RpsCompetition\Frontend\Shortcodes\MyEntries;

use Illuminate\Container\Container as IlluminateContainer;
use RpsCompetition\Libs\Controller;

class MyEntries extends Controller
{
    private $view;

    /**
     * @param IlluminateContainer $container
     */
    public function __construct(IlluminateContainer $container)
    {
        $this->setContainer($container);
        $settings = $container->make('Settings');
        $this->view = $container->make('ShortcodeView', ['template_dir' => $settings->get('template_dir'), 'cache_dir' => $settings->get('upload_dir') . '/twig-cache/']);
        $this->model = $container->make('MyEntriesModel');
    }

    /**
     * Display the entries of the current user.
     * This page shows the current entries for a competition of the current user.
     *
     * @param array  $attr    The shortcode argument list. Allowed arguments:
     *                        - medium
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     *
     * @see Frontend::actionHandleHttpPostRpsMyEntries
     */
    public function shortcodeMyEntries($attr, $content, $tag)
    {

        $attr = shortcode_atts(['medium' => 'digital'], $attr);
        $model_data = $this->model->getMyEntries($attr['medium']);
        $data = $model_data['data'];
        $form = $model_data['form'];

        return $this->view->fetch('add_entries.html.twig', ['data' => $data, 'form' => $form->createView()]);
    }
}
