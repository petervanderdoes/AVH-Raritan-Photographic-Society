<?php
namespace RpsCompetition\Competition;

use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\RpsDb;

class Helper
{
    private $rpsdb;

    public function __construct(RpsDb $rpsdb)
    {
        $this->rpsdb = $rpsdb;
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

    public function isScoredCompetitionDate($date)
    {
        $query_competitions = new QueryCompetitions($this->rpsdb);

        $date = $this->rpsdb->getMysqldate($date);
        $return = false;
        $competitions = $query_competitions->getCompetitionByDates($date);
        if (is_array($competitions) && (!empty($competitions))) {
            /** @var QueryCompetitions $competition */
            foreach ($competitions as $competition) {
                if ($competition->Scored == 'Y' && $competition->Competition_Date) {
                    $return = true;
                    break;
                }
            }
        }

        return $return;
    }
}
