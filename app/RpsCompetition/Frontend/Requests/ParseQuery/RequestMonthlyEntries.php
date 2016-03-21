<?php

namespace RpsCompetition\Frontend\Requests\ParseQuery;

use Avh\Framework\Network\Session;
use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Helpers\CommonHelper;
use RpsCompetition\Helpers\SeasonHelper;

/**
 * Class RequestMonthlyEntries
 *
 * @package   RpsCompetition\Frontend\Requests\ParseQuery
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class RequestMonthlyEntries
{
    private $pq_helper;
    private $query_competitions;
    private $request;
    private $season_helper;
    private $session;

    /**
     * Constructor
     *
     * @param ParseQueryHelper  $parse_query_helper
     * @param QueryCompetitions $query_competitions
     * @param SeasonHelper      $season_helper
     * @param IlluminateRequest $request
     * @param Session           $session
     */
    public function __construct(ParseQueryHelper $parse_query_helper,
                                QueryCompetitions $query_competitions,
                                SeasonHelper $season_helper,
                                IlluminateRequest $request,
                                Session $session)
    {

        $this->query_competitions = $query_competitions;
        $this->season_helper      = $season_helper;
        $this->request            = $request;
        $this->session            = $session;
        $this->pq_helper          = $parse_query_helper;
    }

    /**
     * Handle HTTP requests for Monthly Entries before the page is displayed.
     */
    public function handleRequestMonthlyEntries()
    {

        $redirect                = false;
        $status                  = 303;
        $query_var_selected_date = get_query_var('selected_date', false);

        /**
         * When a new season or new month is selected from the form the submit_control is set.
         * If it's not set we came to page directly and that's handles by the default section.
         */
        switch ($this->request->input('submit_control', null)) {
            case 'new_season':
                $this->pq_helper->setSelectedSeason(esc_attr($this->request->input('new_season')));
                $this->pq_helper->setSelectedDate('');
                break;
            case 'new_month':
                $this->pq_helper->setSelectedSeason(esc_attr($this->request->input('selected_season')));
                $this->pq_helper->setSelectedDate(esc_attr($this->request->input('new_month')));
                break;
            default:
                if ($query_var_selected_date === false || (!CommonHelper::isValidDate($query_var_selected_date,
                                                                                      'Y-m-d'))
                ) {
                    $last_scored = $this->query_competitions->query(['where'   => 'Scored="Y"',
                                                                     'orderby' => 'Competition_Date',
                                                                     'order'   => 'DESC',
                                                                     'number'  => 1
                                                                    ]);
                    $date_object = new \DateTime($last_scored->Competition_Date);
                    $this->pq_helper->setSelectedDate($date_object->format(('Y-m-d')));
                    $redirect = true;
                } else {
                    $this->pq_helper->setSelectedDate($query_var_selected_date);
                }
                $this->pq_helper->setSelectedSeason($this->season_helper->getSeasonId($this->pq_helper->getSelectedDate()));
                break;
        }

        $this->pq_helper->checkValidSeason();
        $this->pq_helper->checkScoredCompetition();

        if ($this->pq_helper->getSelectedDate() != $query_var_selected_date) {
            $redirect = true;
        }

        if ($redirect) {
            wp_redirect('/events/monthly-entries/' . $this->pq_helper->getSelectedDate() . '/', $status);
            exit();
        }

        $this->session->set('monthly_entries_selected_date', $this->pq_helper->getSelectedDate());
        $this->session->set('monthly_entries_selected_season', $this->pq_helper->getSelectedSeason());
    }
}
