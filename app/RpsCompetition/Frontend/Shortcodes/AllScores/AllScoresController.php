<?php
namespace RpsCompetition\Frontend\Shortcodes\AllScores;

use RpsCompetition\Frontend\Shortcodes\ShortcodeView;

/**
 * Class AllScoresController
 *
 * @package   RpsCompetition\Frontend\Shortcodes\AllScores
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class AllScoresController
{
    private $model;
    private $view;

    /**
     * Constructor
     *
     * @param ShortcodeView  $view
     * @param AllScoresModel $model
     */
    public function __construct(ShortcodeView $view, AllScoresModel $model)
    {

        $this->view = $view;
        $this->model = $model;
    }

    /**
     * Return all scores.
     *
     * @return string
     */
    public function shortcodeAllScores()
    {
        $model_data = $this->model->getAllData();

        $data = $model_data['data'];
        /** @var \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface $form */
        $form = $model_data['form'];
        $output = $this->view->fetch('all-scores.html.twig', ['data' => $data, 'form' => $form->createView()]);

        return $output;
    }
}
