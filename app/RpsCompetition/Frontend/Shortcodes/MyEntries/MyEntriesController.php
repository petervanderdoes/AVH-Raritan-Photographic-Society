<?php
namespace RpsCompetition\Frontend\Shortcodes\MyEntries;

use RpsCompetition\Frontend\Shortcodes\ShortcodeView;

/**
 * Class MyEntriesController
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\MyEntries
 */
class MyEntriesController
{
    /** @var MyEntriesModel */
    private $model;
    /** @var ShortcodeView */
    private $view;

    /**
     * @param ShortcodeView  $view
     * @param MyEntriesModel $model
     */
    public function __construct(ShortcodeView $view, MyEntriesModel $model)
    {

        $this->view = $view;
        $this->model = $model;
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
        /** @var \Symfony\Component\Form\Form $form */
        $form = $model_data['form'];

        return $this->view->fetch('add_entries.html.twig', ['data' => $data, 'form' => $form->createView()]);
    }
}
