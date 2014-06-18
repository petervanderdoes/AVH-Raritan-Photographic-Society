<?php
namespace RpsCompetition\Db;

class QueryBanquet
{
    private $rpsdb;

    /**
     * PHP5 constructor
     *
     * @param RpsDb $rpsdb
     */
    public function __construct(RpsDb $rpsdb)
    {
        $this->rpsdb = $rpsdb;
    }

    /**
     * Get the banquets between the given dates
     *
     * @param string $season_start_date
     * @param string $season_end_date
     *
     * @return array
     */
    public function getBanquets($season_start_date, $season_end_date)
    {
        $sql = $this->rpsdb->prepare("SELECT *
		FROM competitions
		WHERE Competition_Date >= %s AND
		  Competition_Date < %s AND
		  Theme LIKE %s
		ORDER BY ID",
                                     $season_start_date,
                                     $season_end_date,
                                     '%banquet%');
        $return = $this->rpsdb->get_results($sql, ARRAY_A);

        return $return;
    }
}
