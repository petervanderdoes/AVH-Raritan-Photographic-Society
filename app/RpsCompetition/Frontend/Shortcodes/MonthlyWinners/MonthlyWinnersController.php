<?php
namespace RpsCompetition\Frontend\Shortcodes\MonthlyWinners;

use Illuminate\Config\Repository as Settings;
use RpsCompetition\Frontend\Shortcodes\ShortcodeView;

/**
 * Class MonthlyWinnersController
 *
 * @package   RpsCompetition\Frontend\Shortcodes\MonthlyWinners
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class MonthlyWinnersController
{
    private $model;
    private $settings;
    private $view;

    /**
     * Contructor
     *
     * @param ShortcodeView       $view
     * @param MonthlyWinnersModel $model
     * @param Settings            $settings
     */
    public function __construct(ShortcodeView $view, MonthlyWinnersModel $model, Settings $settings)
    {
        $this->view = $view;
        $this->model = $model;
        $this->settings = $settings;
    }

    /**
     * Display all winners for the month.
     * All winners of the month are shown, which defaults to the latest month.
     * A dropdown selection to choose different months and/or season is also displayed.
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     *
     * @internal Shortcode: rps_monthly_winners
     */
    public function shortcodeMonthlyWinners($attr, $content, $tag)
    {

        $output = '';
        $selected_date = $this->model->getSelectedDate();
        $selected_season = $this->model->getSelectedSeason();

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
                $data = $this->model->getMonthlyWinners($selected_season, $selected_date);
                $output = $this->view->fetch('monthly-winners.html.twig', $data);
            }
        }

        return $output;
    }
}
