<?php

namespace RpsCompetition\Entity\Forms;

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
     * @return mixed
     */
    public function getSeasons()
    {
        return $this->seasons;
    }

    /**
     * @param mixed $seasons
     */
    public function setSeasons($seasons)
    {
        $this->seasons = $seasons;
    }

    public function getSeasonsChoices()
    {
        return $this->season_choices;
    }

    /**
     * @return mixed
     */
    public function getWpGetReferer()
    {
        return $this->wp_get_referer;
    }

    /**
     * @param mixed $wp_get_referer
     */
    public function setWpGetReferer($wp_get_referer)
    {
        $this->wp_get_referer = $wp_get_referer;
    }
}
