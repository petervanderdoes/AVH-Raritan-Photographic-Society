<?php

namespace RpsCompetition\Entity\Forms;

/**
 * Class ScoresCurrentUser
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Entity\Forms
 */
class ScoresCurrentUser
{
    protected $season_choices = [];
    protected $seasons;
    protected $submit_control;

    /**
     * @return array
     */
    public function getSeasonChoices()
    {
        return $this->season_choices;
    }

    /**
     * @param array $season_choices
     */
    public function setSeasonChoices($season_choices)
    {
        $this->season_choices = $season_choices;
    }

    /**
     * @return string
     */
    public function getSeasons()
    {
        return $this->seasons;
    }

    /**
     * @param string $seasons
     */
    public function setSeasons($seasons)
    {
        $this->seasons = $seasons;
    }
}