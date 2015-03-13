<?php

namespace RpsCompetition\Frontend\Requests\ParseQuery;

use Avh\Network\Session;
use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Competition\Helper as CompetitionHelper;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Season\Helper as SeasonHelper;

/**
 * Class RequestMonthlyWinners
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Requests\ParseQuery
 */
class RequestMonthlyWinners
{
    private $competition_helper;
    private $query_competitions;
    private $request;
    private $season_helper;
    private $session;

    /**
     * Constructor
     *
     * @param QueryCompetitions $query_competitions
     * @param SeasonHelper      $season_helper
     * @param CompetitionHelper $competition_helper
     * @param IlluminateRequest $request
     * @param Session           $session
     */
    public function __construct(
        QueryCompetitions $query_competitions,
        SeasonHelper $season_helper,
        CompetitionHelper $competition_helper,
        IlluminateRequest $request,
        Session $session
    ) {

        $this->query_competitions = $query_competitions;
        $this->season_helper = $season_helper;
        $this->competition_helper = $competition_helper;
        $this->request = $request;
        $this->session = $session;
    }

    /**
     * Handle HTTP requests for Monthly Winners before the page is displayed.
     */
    public function handleRequestMonthlyWinners()
    {
        $redirect = false;
        $status = 303;
        $query_var_selected_date = get_query_var('selected_date', false);

        /**
         * When a new season or new month is selected from the form the submit_control is set.
         * If it's not set we came to page directly and that's handles by the default section.
         */
        switch ($this->request->input('submit_control', null)) {
            case 'new_season':
                $selected_season = esc_attr($this->request->input('new_season'));
                $selected_date = '';
                break;
            case 'new_month':
                $selected_date = esc_attr($this->request->input('new_month'));
                $selected_season = esc_attr($this->request->input('selected_season'));
                break;
            default:
                if ($query_var_selected_date === false || (!CommonHelper::isValidDate(
                        $query_var_selected_date,
                        'Y-m-d'
                    ))
                ) {
                    $last_scored = $this->query_competitions->query(
                        [
                            'where'   => 'Scored="Y" AND Special_Event="N"',
                            'orderby' => 'Competition_Date',
                            'order'   => 'DESC',
                            'number'  => 1
                        ]
                    )
                    ;
                    $date_object = new \DateTime($last_scored->Competition_Date);
                    $selected_date = $date_object->format(('Y-m-d'));
                    $redirect = true;
                } else {
                    $selected_date = $query_var_selected_date;
                }
                $selected_season = $this->season_helper->getSeasonId($selected_date);
                break;
        }

        if (!$this->season_helper->isValidSeason($selected_season)) {
            $selected_season = $this->season_helper->getSeasonId(date('r'));
            $competitions = $this->query_competitions->getCompetitionBySeasonId($selected_season, ['Scored' => 'Y']);
            /** @var QueryCompetitions $competition */
            $competition = end($competitions);
            $date_object = new \DateTime($competition->Competition_Date);
            $selected_date = $date_object->format(('Y-m-d'));
        }

        if (!$this->competition_helper->isScoredCompetitionDate($selected_date)) {
            $competitions = $this->query_competitions->getCompetitionBySeasonId($selected_season, ['Scored' => 'Y']);
            /** @var QueryCompetitions $competition */
            $competition = end($competitions);
            $date_object = new \DateTime($competition->Competition_Date);
            $selected_date = $date_object->format(('Y-m-d'));
        }

        if ($selected_date != $query_var_selected_date) {
            $redirect = true;
        }

        if ($redirect) {
            wp_redirect('/events/monthly-winners/' . $selected_date . '/', $status);
            exit();
        }

        $this->session->set('monthly_winners_selected_date', $selected_date);
        $this->session->set('monthly_winners_selected_season', $selected_season);
        $this->session->save();
    }
}
