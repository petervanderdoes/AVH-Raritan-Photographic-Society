<?php

namespace RpsCompetition\Entity\Forms;

/**
 * Class BanquetCurrentUser
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Entity\Forms
 */
class AllScores
{
    protected $allentries;
    protected $banquetids;
    protected $season_choices = [];
    protected $seasons;
    protected $wp_get_referer;

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
