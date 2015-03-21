<?php

namespace RpsCompetition\Entity\Form;

/**
 * Class AllScores
 *
 * @package   RpsCompetition\Entity\Form
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class AllScores
{
    protected $season_choices = [];
    protected $seasons;

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
