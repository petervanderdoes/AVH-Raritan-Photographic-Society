<?php
namespace RpsCompetition\Competition;

use RpsCompetition\Db\RpsDb;

class Helper
{
    private $rpsdb;

    function __construct(RpsDb $rpsdb)
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
            $medium[] = $competition->Medium;
        }

        return $medium;
    }
}
