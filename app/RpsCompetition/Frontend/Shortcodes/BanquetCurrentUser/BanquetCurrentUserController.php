<?php

namespace RpsCompetition\Frontend\Shortcodes\BanquetCurrentUser;

use RpsCompetition\Frontend\Shortcodes\ShortcodeView;
use RpsCompetition\Libs\Controller;

class BanquetCurrentUserController extends Controller
{
    private $model;
    private $view;

    /**
     * @param ShortcodeView           $view
     * @param BanquetCurrentUserModel $model
     */
    public function __construct(ShortcodeView $view, BanquetCurrentUserModel $model)
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
    public function shortcodeBanquetCurrentUser($attr, $content, $tag)
    {
        $attr = shortcode_atts(['medium' => 'digital'], $attr);
        $model_data = $this->model->getEntries();
        $data = $model_data['data'];
        $form = $model_data['form'];

        return $this->view->fetch('banquet-current-user.html.twig', ['data' => $data, 'form' => $form->createView()]);

    }
}
