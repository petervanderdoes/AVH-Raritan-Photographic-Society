<?php
namespace RpsCompetition\Frontend\Shortcodes\MonthlyEntries;

use RpsCompetition\Frontend\Shortcodes\ShortcodeView;
use \Illuminate\Config\Repository as Settings;

/**
 * Class MonthlyEntriesController
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\MonthlyEntries
 */
class MonthlyEntriesController
{
    private $model;
    private $settings;
    private $view;

    /**
     * Contructor
     *
     * @param ShortcodeView       $view
     * @param MonthlyEntriesModel $model
     * @param Settings            $settings
     */
    public function __construct(ShortcodeView $view, MonthlyEntriesModel $model, Settings $settings)
    {

        $this->view = $view;
        $this->model = $model;
        $this->settings = $settings;
    }

    /**
     * Show all entries for a month.
     * The default is to show the entries for the latest closed competition.
     * A dropdown selection to choose different months and/or season is also displayed.
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     *
     * @internal Shortcode: rps_monthly_entries
     */
    public function shortcodeMonthlyEntries($attr, $content, $tag)
    {
        $output = '';
        $selected_date = $this->model->getSelectedDate();

        if ($this->model->isScoredCompetition($selected_date)) {
            /**
             * Check if we ran the filter filterWpseoPreAnalysisPostsContent.
             *
             * @see Frontend::filterWpseoPreAnalysisPostsContent
             */
            $didFilterWpseoPreAnalysisPostsContent = $this->settings->get(
                'didFilterWpseoPreAnalysisPostsContent',
                false
            )
            ;
            if (!$didFilterWpseoPreAnalysisPostsContent) {
                $data = $this->model->getFacebookData($selected_date, $selected_date);
                $output = $this->view->fetch('facebook.html.twig', $data);
            } else {
                $data = $this->model->getMonthlyEntriesData($selected_date);
                $output = $this->view->fetch('monthly-entries.html.twig', $data);
            }
        }

        return $output;
    }
}
