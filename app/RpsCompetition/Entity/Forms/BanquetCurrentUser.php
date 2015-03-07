<?php

namespace RpsCompetition\Entity\Forms;

/**
 * Class BanquetCurrentUser
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Entity\Forms
 */
class BanquetCurrentUser
{
    protected $allentries;
    protected $banquetids;
    protected $season_choices = [];
    protected $seasons;
    protected $wp_get_referer;

    /**
     * @return mixed
     */
    public function getAllentries()
    {
        return $this->allentries;
    }

    /**
     * @param mixed $allentries
     */
    public function setAllentries($allentries)
    {
        $this->allentries = $allentries;
    }

    /**
     * @return mixed
     */
    public function getBanquetids()
    {
        return $this->banquetids;
    }

    /**
     * @param mixed $banquetids
     */
    public function setBanquetids($banquetids)
    {
        $this->banquetids = $banquetids;
    }

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
     * @param atring $seasons
     */
    public function setSeasons($seasons)
    {
        $this->seasons = $seasons;
    }

    /**
     * @return array
     */
    public function getSeasonsChoices()
    {
        return $this->season_choices;
    }

    /**
     * @return string
     */
    public function getWpGetReferer()
    {
        return $this->wp_get_referer;
    }

    /**
     * @param string $wp_get_referer
     */
    public function setWpGetReferer($wp_get_referer)
    {
        $this->wp_get_referer = $wp_get_referer;
    }
}
