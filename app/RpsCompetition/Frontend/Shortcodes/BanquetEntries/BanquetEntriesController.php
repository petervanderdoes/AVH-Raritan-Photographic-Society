<?php

namespace RpsCompetition\Frontend\Shortcodes\BanquetEntries;

use RpsCompetition\Frontend\Shortcodes\ShortcodeView;

/**
 * Class BanquetEntriesController
 *
 * @package   RpsCompetition\Frontend\Shortcodes\BanquetEntries
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class BanquetEntriesController
{
    private $model;
    private $view;

    /**
     * @param ShortcodeView       $view
     * @param BanquetEntriesModel $model
     */
    public function __construct(ShortcodeView $view, BanquetEntriesModel $model)
    {

        $this->view  = $view;
        $this->model = $model;
    }

    /**
     * Display the possible Banquet entries for the current user.
     *
     * @see Frontend::actionHandleHttpPostRpsBanquetEntries
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     */
    public function shortcodeBanquetEntries($attr, $content, $tag)
    {
        $model_data = $this->model->getAllData();
        $data       = $model_data['data'];
        /** @var \Symfony\Component\Form\Form $form */
        $form = $model_data['form'];

        return $this->view->fetch('banquet-entries.html.twig', ['data' => $data, 'form' => $form->createView()]);
    }
}
