<?php
namespace RpsCompetition\Frontend\Shortcodes\AllScores;

use RpsCompetition\Frontend\Shortcodes\ShortcodeView;

/**
 * Class AllScoresController
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\AllScores
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
        $form = $model_data['form'];
        $output = $this->view->fetch('all-scores.html.twig', ['data' => $data, 'form' => $form->createView()]);

        return $output;
    }
}
