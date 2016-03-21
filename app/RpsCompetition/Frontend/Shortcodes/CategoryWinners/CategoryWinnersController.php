<?php

namespace RpsCompetition\Frontend\Shortcodes\CategoryWinners;

use Illuminate\Config\Repository as Settings;
use RpsCompetition\Frontend\Shortcodes\ShortcodeView;

/**
 * Class CategoryWinnersController
 *
 * @package   RpsCompetition\Frontend\Shortcodes\CategoryWinners
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class CategoryWinnersController
{
    private $model;
    private $settings;
    private $view;

    /**
     * Constructor
     *
     * @param ShortcodeView        $view
     * @param CategoryWinnersModel $model
     * @param Settings             $settings
     */
    public function __construct(ShortcodeView $view, CategoryWinnersModel $model, Settings $settings)
    {

        $this->view     = $view;
        $this->model    = $model;
        $this->settings = $settings;
    }

    /**
     * Display the given awards for the given classification on a given date.
     *
     * @param array  $attr    The shortcode argument list. Allowed arguments:
     *                        - class
     *                        - award
     *                        - date
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     * @internal Shortcode: rps_category_winners
     */
    public function shortcodeCategoryWinners($attr, $content, $tag)
    {
        $class  = 'Beginner';
        $award  = '1';
        $date   = '';
        $output = '';
        extract($attr, EXTR_OVERWRITE);

        $entries = $this->model->getWinner($class, $award, $date);

        /**
         * Check if we ran the filter filterWpseoPreAnalysisPostsContent.
         *
         * @see Frontend::filterWpseoPreAnalysisPostsContent
         */
        $didFilterWpseoPreAnalysisPostsContent = $this->settings->get('didFilterWpseoPreAnalysisPostsContent', false);

        if (is_array($entries)) {
            if (!$didFilterWpseoPreAnalysisPostsContent) {
                $data   = $this->model->getFacebookData($entries);
                $output = $this->view->fetch('facebook.html.twig', $data);
            } else {
                $data   = $this->model->getCategoryWinners($class, $entries, '250');
                $output = $this->view->fetch('category-winners.html.twig', $data);
            }
        }

        return $output;
    }
}
