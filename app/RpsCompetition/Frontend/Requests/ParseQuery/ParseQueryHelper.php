<?php

namespace RpsCompetition\Frontend\Requests\ParseQuery;

use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Helpers\CompetitionHelper;
use RpsCompetition\Helpers\SeasonHelper;

/**
 * Class ParseQueryHelper
 *
 * @package   RpsCompetition\Frontend\Requests\ParseQuery
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class ParseQueryHelper
{
    private $competition_helper;
    private $query_competitions;
    private $season_helper;
    private $selected_date;
    private $selected_season;

    /**
     * @param QueryCompetitions $query_competitions
     * @param SeasonHelper      $season_helper
     * @param CompetitionHelper $competition_helper
     */
    public function __construct(
        QueryCompetitions $query_competitions,
        SeasonHelper $season_helper,
        CompetitionHelper $competition_helper
    ) {

        $this->query_competitions = $query_competitions;
        $this->season_helper = $season_helper;
        $this->competition_helper = $competition_helper;
    }

    public function checkScoredCompetition()
    {
        if (!$this->competition_helper->isScoredCompetitionDate($this->selected_date)) {
            $competitions = $this->query_competitions->getCompetitionBySeasonId(
                $this->selected_season,
                ['Scored' => 'Y']
            );
            /** @var QueryCompetitions $competition */
            $competition = end($competitions);
            $date_object = new \DateTime($competition->Competition_Date);
            $this->selected_date = $date_object->format(('Y-m-d'));
        }
    }

    public function checkValidSeason()
    {
        if (!$this->season_helper->isValidSeason($this->selected_season)) {
            $this->selected_season = $this->season_helper->getSeasonId(date('r'));
            $competitions = $this->query_competitions->getCompetitionBySeasonId(
                $this->selected_season,
                ['Scored' => 'Y']
            );
            /** @var QueryCompetitions $competition */
            $competition = end($competitions);
            $date_object = new \DateTime($competition->Competition_Date);
            $this->selected_date = $date_object->format(('Y-m-d'));
        }
    }

    /**
     * @return mixed
     */
    public function getSelectedDate()
    {
        return $this->selected_date;
    }

    /**
     * @param mixed $selected_date
     */
    public function setSelectedDate($selected_date)
    {
        $this->selected_date = $selected_date;
    }

    /**
     * @return mixed
     */
    public function getSelectedSeason()
    {
        return $this->selected_season;
    }

    /**
     * @param mixed $selected_season
     */
    public function setSelectedSeason($selected_season)
    {
        $this->selected_season = $selected_season;
    }
}
