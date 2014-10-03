<?php
namespace RpsCompetition\Competition;

use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Settings;

class Helper
{
    private $rpsdb;
    private $settings;

    public function __construct(Settings $settings, RpsDb $rpsdb)
    {
        $this->rpsdb = $rpsdb;
        $this->settings = $settings;
    }

    public function getMedium($competitions)
    {

        $medium = array();

        foreach ($competitions as $competition) {
            if (in_array($competition->Medium, $medium)) {
                continue;
            }
            $medium[$competition->Medium] = $competition->Medium;
        }

        return $medium;
    }

    /**
     * Check if there is a Scored competition for the given date.
     *
     * @param string $date
     *
     * @return bool
     */
    public function isScoredCompetitionDate($date)
    {
        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);

        $date = $this->rpsdb->getMysqldate($date);
        $return = false;
        $competitions = $query_competitions->getScoredCompetitions($date);
        if (is_array($competitions) && (!empty($competitions))) {
            $return = true;
        }

        return $return;
    }
}
