<?php

namespace RpsCompetition\Frontend\Shortcodes\ScoresCurrentUser;

use RpsCompetition\Frontend\Shortcodes\ShortcodeView;

/**
 * Class ScoresCurrentUserController
 *
 * @package   RpsCompetition\Frontend\Shortcodes\ScoresCurrentUser
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class ScoresCurrentUserController
{
    private $model;
    private $view;

    /**
     * Constructor
     *
     * @param ShortcodeView          $view
     * @param ScoresCurrentUserModel $model
     */
    public function __construct(ShortcodeView $view, ScoresCurrentUserModel $model)
    {
        $this->view = $view;
        $this->model = $model;
    }

    /**
     * Displays the scores of the current user.
     * By default the scores of the latest season is shown.
     * A drop down with a season list is shown for the user to select.
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     */
    public function shortcodeScoresCurrentUser($attr, $content, $tag)
    {
        $model_data = $this->model->getAllData();
        $data = $model_data['data'];
        /** @var \Symfony\Component\Form\Form $form */
        $form = $model_data['form'];

        return $this->view->fetch('scores-current-user.html.twig', ['data' => $data, 'form' => $form->createView()]);
    }
}
