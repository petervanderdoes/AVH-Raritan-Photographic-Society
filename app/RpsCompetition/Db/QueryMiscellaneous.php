<?php
namespace RpsCompetition\Db;

/**
 * Class QueryMiscellaneous
 *
 * @package   RpsCompetition\Db
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class QueryMiscellaneous
{
    private $rpsdb;

    /**
     * Constructor
     *
     * @param RpsDb $rpsdb
     */
    public function __construct(RpsDb $rpsdb)
    {
        $this->rpsdb = $rpsdb;
    }

    /**
     * Count all entries between the given competition dates.
     *
     * @param string      $competition_date_start
     * @param string|null $competition_date_end
     *
     * @return int
     */
    public function countAllEntries($competition_date_start, $competition_date_end = null)
    {
        $competition_date_end = ($competition_date_end === null) ? $competition_date_start : $competition_date_end;
        $competition_date_start = $this->rpsdb->getMysqldate($competition_date_start);
        $competition_date_end = $this->rpsdb->getMysqldate($competition_date_end);

        $return = $this->getAllEntries($competition_date_start, $competition_date_end);

        return count($return);
    }

    /**
     * Get all photos for competitions between the given dates.
     *
     * @param string      $competition_date_start
     * @param null|string $competition_date_end
     *
     * @return array
     */
    public function getAllEntries($competition_date_start, $competition_date_end = null)
    {
        $competition_date_end = ($competition_date_end === null) ? $competition_date_start : $competition_date_end;

        $competition_date_start = $this->rpsdb->getMysqldate($competition_date_start);
        $competition_date_end = $this->rpsdb->getMysqldate($competition_date_end);

        $sql = $this->rpsdb->prepare(
            'SELECT e.*
            FROM competitions c, entries e
                WHERE c.ID = e.Competition_ID and
                    c.Competition_Date >= %s AND
                    c.Competition_Date <= %s
                ORDER BY RAND()',
            $competition_date_start,
            $competition_date_end
        )
        ;
        $return = $this->rpsdb->get_results($sql);

        return $return;
    }

    /**
     * Get result by given date.
     * This will return the results, scores & awards, including member info for competitions given between the dates
     *
     * @param string      $competition_date_start
     * @param null|string $competition_date_end
     *
     * @return array
     */
    public function getCompetitionResultByDate($competition_date_start, $competition_date_end = null)
    {
        $competition_date_end = ($competition_date_end === null) ? $competition_date_start : $competition_date_end;

        $sql = $this->rpsdb->prepare(
            'SELECT c.Competition_Date, c.Medium, c.Classification, c.Special_Event,
            if(c.Classification = "Beginner",0,
            if(c.Classification = "Advanced",1,2)) as "Class_Code",
            e.Score, e.Award, e.Member_ID
            FROM competitions as c, entries as e
            WHERE c.ID = e.Competition_ID AND
                Competition_Date >= %s AND
                Competition_Date < %s AND
                Special_Event = "N" AND
                c.Scored = "Y"
            ORDER BY c.Medium DESC, Class_Code, c.Competition_Date',
            $competition_date_start,
            $competition_date_end
        )
        ;

        $records = $this->rpsdb->get_results($sql, ARRAY_A);
        $return = [];
        foreach ($records as $rec) {
            $user_info = get_userdata($rec['Member_ID']);
            $rec['FirstName'] = $user_info->user_firstname;
            $rec['LastName'] = $user_info->user_lastname;
            $rec['Username'] = $user_info->user_login;
            $return[] = $rec;
        }

        return $return;
    }

    /**
     * Get random entries that scored 8 or higher.
     * The amount of records returned can be set by the $limit argument.
     *
     * @param integer $limit Amount of records to return. Default is 5.
     *
     * @return mixed
     */
    public function getEightsAndHigher($limit = 5)
    {
        $sql = $this->rpsdb->prepare(
            "SELECT c.Competition_Date, c.Classification,
                if(c.Classification = 'Beginner',1,
                if(c.Classification = 'Advanced',2,
                if(c.Classification = 'Salon',3,0))) as \"Class_Code\",
                c.Medium, e.Title, e.Server_File_Name, e.Award, e.Member_ID
            FROM competitions c, entries e
                WHERE c.ID = e.Competition_ID AND
                    e.Score >= 8
                ORDER BY RAND()
                LIMIT %d",
            $limit
        )
        ;
        $return = $this->rpsdb->get_results($sql);

        return $return;
    }

    /**
     * Get all photos of the given member_id with a score that is 8 or higher.
     *
     * @param integer $member_id
     *
     * @return array
     */
    public function getEightsAndHigherPerson($member_id)
    {
        $sql = $this->rpsdb->prepare(
            "SELECT c.Competition_Date, c.Classification,
                if(c.Classification = 'Beginner',1,
                if(c.Classification = 'Advanced',2,
                if(c.Classification = 'Salon',3,0))) as \"Class_Code\",
                c.Medium, e.Title, e.Server_File_Name, e.Award, e.Member_ID
            FROM competitions c, entries e
                WHERE c.ID = e.Competition_ID AND
                    e.Member_ID = %s AND
                    e.Score >= 8
                ORDER BY c.Competition_Date, Class_Code, c.Medium, e.Score",
            $member_id
        )
        ;
        $return = $this->rpsdb->get_results($sql);

        return $return;
    }

    /**
     * Get the maximum awards per competition date, classification and medium between the given competition dates
     *
     * @param string      $competition_date_start
     * @param null|string $competition_date_end
     *
     * @return integer
     */
    public function getMaxAwards($competition_date_start, $competition_date_end = null)
    {
        $competition_date_end = ($competition_date_end === null) ? $competition_date_start : $competition_date_end;

        $competition_date_start = $this->rpsdb->getMysqldate($competition_date_start);
        $competition_date_end = $this->rpsdb->getMysqldate($competition_date_end);

        $sql = $this->rpsdb->prepare(
            "SELECT MAX(z.Num_Awards) AS Max_Num_Awards FROM
                (SELECT c.Competition_Date, c.Classification, c.Medium, COUNT(e.Award) AS Num_Awards
                    FROM competitions c, entries e
                        WHERE c.ID = e.Competition_ID AND
                            c.Competition_Date >= %s AND
                            c.Competition_Date <= %s AND
                            Scored = 'Y' AND
                            e.Award <> ''
                        GROUP BY c.Competition_Date, c.Classification, c.Medium) z",
            $competition_date_start,
            $competition_date_end
        )
        ;
        $return = $this->rpsdb->get_var($sql);

        return $return;
    }

    /**
     * Get the scores for the given user for competitions between the given dates.
     *
     * @param integer     $user_id
     * @param string      $competition_date_start
     * @param null|string $competition_date_end
     *
     * @return array
     */
    public function getScoresUser($user_id, $competition_date_start, $competition_date_end = null)
    {
        $competition_date_end = ($competition_date_end === null) ? $competition_date_start : $competition_date_end;

        $sql = $this->rpsdb->prepare(
            "SELECT c.ID as Competition_ID, c.Competition_Date, c.Classification,
                if(c.Classification = 'Beginner',1,
                if(c.Classification = 'Advanced',2,
                if(c.Classification = 'Salon',3,0))) as \"Class_Code\", c.Medium, c.Theme, e.ID as Entry_ID, e.Title, e.Server_File_Name,
                e.Score, e.Award
            FROM competitions as c, entries as e
            WHERE c.ID = e.Competition_ID AND
                c.Competition_Date >= %s AND
                c.Competition_Date <= %s AND
                e.Member_ID = %s
            ORDER BY c.Competition_Date, c.Medium",
            $competition_date_start,
            $competition_date_end,
            $user_id
        )
        ;
        $return = $this->rpsdb->get_results($sql, ARRAY_A);

        return $return;
    }

    /**
     * Get a list of seasons
     *
     * @param string  $order
     * @param integer $season_start_month_num
     * @param integer $season_end_month_num
     *
     * @return array
     */
    public function getSeasonList($order = 'ASC', $season_start_month_num, $season_end_month_num)
    {
        $sql = $this->rpsdb->prepare(
            'SELECT if(month(Competition_Date) >= %s and month(Competition_Date) <= %s,
            concat_WS("-",year(Competition_Date),substr(year(Competition_Date)+1,3,2)),
            concat_WS("-",year(Competition_Date)-1,substr(year(Competition_Date),3,2))) as "Season"
            FROM competitions
            GROUP BY Season
            ORDER BY Season ' . $order,
            $season_start_month_num,
            $season_end_month_num
        )
        ;

        $result = $this->rpsdb->get_results($sql, ARRAY_A);
        $seasons = [];
        foreach ($result as $key => $value) {
            $seasons[$key] = $value['Season'];
        }

        return $seasons;
    }

    /**
     * Get Season list plus amount of entries and teh season has at least 1 entry.
     *
     * @param integer $season_start_month_num
     * @param integer $season_end_month_num
     *
     * @return array
     */
    public function getSeasonListWithEntries($season_start_month_num, $season_end_month_num)
    {
        $sql = $this->rpsdb->prepare(
            'SELECT if(month(c.Competition_Date) >= %s and month(c.Competition_Date) <= %s,
            concat_WS(" - ",year(c.Competition_Date),substr(year(c.Competition_Date)+1,3,2)),
            concat_WS(" - ",year(c.Competition_Date)-1,substr(year(c.Competition_Date),3,2))) as "Season",
            count(e.ID)
            FROM competitions c, entries e
            WHERE c.ID = e.Competition_ID
            GROUP BY Season
            HAVING count(e.ID) > 0
            ORDER BY Season',
            $season_start_month_num,
            $season_end_month_num
        )
        ;

        $result = $this->rpsdb->get_results($sql, ARRAY_A);
        $seasons = [];
        foreach ($result as $key => $value) {
            $seasons[$key] = $value['Season'];
        }

        return $seasons;
    }

    /**
     * Get the winners of the given classification and award on the given competition date
     *
     * @param string $date
     * @param string $award
     * @param string $class
     *
     * @return QueryMiscellaneous
     */
    public function getWinner($date, $award, $class)
    {
        $sql = $this->rpsdb->prepare(
            'SELECT c.Competition_Date, c.Classification,
                c.Medium, e.Title, e.Server_File_Name, e.Award, e.Member_ID
            FROM competitions c, entries e
                WHERE c.ID = e.Competition_ID and
                    c.Competition_Date = %s AND
                    e.Award =%s AND
                    c.Classification = %s
                ORDER BY c.Medium',
            $date,
            $award,
            $class
        )
        ;
        $results = $this->rpsdb->get_results($sql);

        return $results;
    }

    /**
     * Get all photos that have an award for competitions between the given dates.
     *
     * @param string      $competition_date_start
     * @param null|string $competition_date_end
     *
     * @return array
     */
    public function getWinners($competition_date_start, $competition_date_end = null)
    {
        $competition_date_end = ($competition_date_end === null) ? $competition_date_start : $competition_date_end;

        $sql = $this->rpsdb->prepare(
            'SELECT c.ID, c.Competition_Date, c.Classification,
                if(c.Classification = "Beginner",1,
                if(c.Classification = "Advanced",2,
                if(c.Classification = "Salon",3,0))) as "Class_Code",
                c.Medium, e.Title, e.Server_File_Name, e.Award, e.Member_ID
            FROM competitions c, entries e
                WHERE c.ID = e.Competition_ID and
                    c.Competition_Date >= %s AND
                    c.Competition_Date <= %s AND
                    e.Award <> ""
                ORDER BY c.Competition_Date, Class_Code, c.Medium, e.Award',
            $competition_date_start,
            $competition_date_end
        )
        ;
        $results = $this->rpsdb->get_results($sql);

        return $results;
    }
}
