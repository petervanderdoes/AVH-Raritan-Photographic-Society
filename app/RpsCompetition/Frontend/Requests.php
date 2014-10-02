<?php
namespace RpsCompetition\Frontend;

use Avh\Network\Session;
use Illuminate\Http\Request;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Competition\Helper as CompetitionHelper;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Season\Helper as SeasonHelper;
use RpsCompetition\Settings;

class Requests
{
    private $request;
    private $rpsdb;
    private $settings;
    private $session;

    public function __construct(Settings $settings, RpsDb $rpsdb, Request $request, Session $session)
    {
        $this->settings = $settings;
        $this->rpsdb = $rpsdb;
        $this->request = $request;
        $this->session = $session;
    }

    /**
     * Handle HTTP Requests.
     *
     * @param $wp_query
     *
     * @internal Hook: parse_query
     * @see      Frontend::__construct
     */
    public function actionHandleRequests($wp_query)
    {

        $options = get_option('avh-rps');
        if (isset($wp_query->query['page_id'])) {
            if ($wp_query->query['page_id'] == $options['monthly_entries_post_id']) {
                $this->handleRequestMonthlyEntries();
            }
            if ($wp_query->query['page_id'] == $options['monthly_winners_post_id']) {
                $this->handleRequestMonthlyWinners();
            }
        }
    }

    /**
     * Handle HTTP requests for Monthly Entries before the page is displayed.
     */
    private function handleRequestMonthlyEntries()
    {

        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
        $season_helper = new SeasonHelper($this->settings, $this->rpsdb);
        $competition_helper = new CompetitionHelper($this->settings, $this->rpsdb);

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
                if ($query_var_selected_date === false || (!CommonHelper::isValidDate($query_var_selected_date, 'Y-m-d'))) {
                    $last_scored = $query_competitions->query(array('where' => 'Scored="Y"', 'orderby' => 'Competition_Date', 'order' => 'DESC', 'number' => 1));
                    $date_object = new \DateTime($last_scored->Competition_Date);
                    $selected_date = $date_object->format(('Y-m-d'));
                    $redirect = true;
                } else {
                    $selected_date = $query_var_selected_date;
                }
                $selected_season = $season_helper->getSeasonId($selected_date);
                break;
        }

        if (!$season_helper->isValidSeason($selected_season)) {
            $selected_season = $season_helper->getSeasonId(date('r'));
            $competitions = $query_competitions->getCompetitionBySeasonId($selected_season, array('Scored' => 'Y'));
            /** @var QueryCompetitions $competition */
            $competition = end($competitions);
            $date_object = new \DateTime($competition->Competition_Date);
            $selected_date = $date_object->format(('Y-m-d'));
        }

        if (!$competition_helper->isScoredCompetitionDate($selected_date)) {
            $competitions = $query_competitions->getCompetitionBySeasonId($selected_season, array('Scored' => 'Y'));
            /** @var QueryCompetitions $competition */
            $competition = end($competitions);
            $date_object = new \DateTime($competition->Competition_Date);
            $selected_date = $date_object->format(('Y-m-d'));
        }

        if ($selected_date != $query_var_selected_date) {
            $redirect = true;
        }

        if ($redirect) {
            wp_redirect('/events/monthly-entries/' . $selected_date . '/', $status);
            exit();
        }

        $this->session->set('monthly_entries_selected_date', $selected_date);
        $this->session->set('monthly_entries_selected_season', $selected_season);
        $this->session->save();
    }

    /**
     * Handle HTTP requests for Monthly Winners before the page is displayed.
     */
    private function handleRequestMonthlyWinners()
    {
        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
        $season_helper = new SeasonHelper($this->settings, $this->rpsdb);
        $competition_helper = new CompetitionHelper($this->settings, $this->rpsdb);

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
                if ($query_var_selected_date === false || (!CommonHelper::isValidDate($query_var_selected_date, 'Y-m-d'))) {
                    $last_scored = $query_competitions->query(array('where' => 'Scored="Y" AND Special_Event="N"', 'orderby' => 'Competition_Date', 'order' => 'DESC', 'number' => 1));
                    $date_object = new \DateTime($last_scored->Competition_Date);
                    $selected_date = $date_object->format(('Y-m-d'));
                    $redirect = true;
                } else {
                    $selected_date = $query_var_selected_date;
                }
                $selected_season = $season_helper->getSeasonId($selected_date);
                break;
        }

        if (!$season_helper->isValidSeason($selected_season)) {
            $selected_season = $season_helper->getSeasonId(date('r'));
            $competitions = $query_competitions->getCompetitionBySeasonId($selected_season, array('Scored' => 'Y'));
            /** @var QueryCompetitions $competition */
            $competition = end($competitions);
            $date_object = new \DateTime($competition->Competition_Date);
            $selected_date = $date_object->format(('Y-m-d'));
        }

        if (!$competition_helper->isScoredCompetitionDate($selected_date)) {
            $competitions = $query_competitions->getCompetitionBySeasonId($selected_season, array('Scored' => 'Y'));
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