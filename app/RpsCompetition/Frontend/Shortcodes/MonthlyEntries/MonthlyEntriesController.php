<?php
namespace RpsCompetition\Frontend\Shortcodes\MonthlyEntries;

use Illuminate\Config\Repository as Settings;
use RpsCompetition\Frontend\Shortcodes\ShortcodeView;

/**
 * Class MonthlyEntriesController
 *
 * @package   RpsCompetition\Frontend\Shortcodes\MonthlyEntries
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
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

        $this->view     = $view;
        $this->model    = $model;
        $this->settings = $settings;
    }

    /**
     * Show all entries for a month.
     * The default is to show the entries for the latest closed competition.
     * A dropdown selection to choose different months and/or season is also displayed.
     *
     * @internal Shortcode: rps_monthly_entries
     *
     * @return string
     */
    public function shortcodeMonthlyEntries()
    {
        $output        = '';
        $selected_date = $this->model->getSelectedDate();

        if ($this->model->isScoredCompetition($selected_date)) {
            /**
             * Check if we ran the filter filterWpseoPreAnalysisPostsContent.
             *
             * @see Frontend::filterWpseoPreAnalysisPostsContent
             */
            $didFilterWpseoPreAnalysisPostsContent = $this->settings->get('didFilterWpseoPreAnalysisPostsContent',
                                                                          false);
            if (!$didFilterWpseoPreAnalysisPostsContent) {
                $data   = $this->model->getFacebookData($selected_date, $selected_date);
                $output = $this->view->fetch('facebook.html.twig', $data);
            } else {
                $data   = $this->model->getMonthlyEntriesData($selected_date);
                $output = $this->view->fetch('monthly-entries.html.twig', $data);
            }
        }

        return $output;
    }
}
