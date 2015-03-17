<?php

namespace RpsCompetition\Frontend\Shortcodes\BanquetEntries;

use RpsCompetition\Frontend\Shortcodes\ShortcodeView;

/**
 * Class BanquetEntriesController
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\BanquetEntries
 */
class BanquetEntriesController
{
    private $model;
    private $view;

    /**
     * @param ShortcodeView           $view
     * @param BanquetEntriesModel $model
     */
    public function __construct(ShortcodeView $view, BanquetEntriesModel $model)
    {

        $this->view = $view;
        $this->model = $model;
    }

    /**
     * Display the possible Banquet entries for the current user.
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @see Frontend::actionHandleHttpPostRpsBanquetEntries
     *
     * @return string
     */
    public function shortcodeBanquetEntries($attr, $content, $tag)
    {
        $model_data = $this->model->getAllData();
        $data = $model_data['data'];
        /** @var \Symfony\Component\Form\Form $form */
        $form = $model_data['form'];

        return $this->view->fetch('banquet-current-user.html.twig', ['data' => $data, 'form' => $form->createView()]);
    }
}
