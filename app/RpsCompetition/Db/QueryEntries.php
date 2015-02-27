<?php
namespace RpsCompetition\Db;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class QueryEntries
 *
 * @package RpsCompetition\Db
 * @property integer ID
 * @property integer Competition_ID
 * @property integer Member_ID
 * @property string  Title
 * @property string  Client_File_Name
 * @property string  Server_File_Name
 * @property string  Date_Created
 * @property string  Date_Modified
 * @property float   Score
 * @property string  Award
 */
class QueryEntries
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
     * Add an entry
     *
     * @param array   $data
     * @param integer $user_id
     *
     * @return boolean
     */
    public function addEntry($data, $user_id)
    {
        $data['Member_ID'] = $user_id;
        $data['Date_Created'] = current_time('mysql');
        $return = $this->rpsdb->insert('entries', $data);

        return $return;
    }

    /**
     * Check if the title already exists for the given competition.
     *
     * @param integer $id
     * @param string  $title
     * @param integer $user_id
     *
     * @return boolean
     */
    public function checkDuplicateTitle($id, $title, $user_id)
    {
        $sql = $this->rpsdb->prepare(
            "SELECT ID
            FROM entries
            WHERE Competition_ID = %s
                AND Member_ID = %s
                AND Title = %s",
            $id,
            $user_id,
            $title
        )
        ;
        $return = $this->rpsdb->get_var($sql);
        if ($return > 0) {
            $return = true;
        } else {
            $return = false;
        }

        return $return;
    }

    /**
     * Count the entries for a member on a given competition date
     *
     * @param string  $date
     * @param integer $user_id
     *
     * @return integer
     */
    public function countEntriesByCompetitionDate($date, $user_id)
    {
        $sql = $this->rpsdb->prepare(
            "SELECT count(e.ID) as Total_Entries_Submitted
                    FROM entries e, competitions c
                    WHERE e.Competition_ID = c.ID AND
                    c.Competition_Date = DATE(%s) AND
                    e.Member_ID = %s",
            $date,
            $user_id
        )
        ;
        $return = $this->rpsdb->get_var($sql);

        return $return;
    }

    /**
     * Get the amount of entries for the given competition.
     *
     * @param integer $id
     * @param integer $user_id
     *
     * @return integer
     */
    public function countEntriesByCompetitionId($id, $user_id)
    {
        $sql = $this->rpsdb->prepare(
            "SELECT count(ID) FROM entries
            WHERE Competition_ID = %s
                AND Member_ID = %s",
            $id,
            $user_id
        )
        ;
        $return = $this->rpsdb->get_var($sql);

        return $return;
    }

    /**
     * Count entries submitted by this member for this competition date.
     *
     * @param integer $user_id
     * @param string  $competition_date
     *
     * @return integer
     */
    public function countEntriesSubmittedByMember($user_id, $competition_date)
    {
        $competition_date = $this->rpsdb->getMysqldate($competition_date);

        $sql = $this->rpsdb->prepare(
            "SELECT COUNT(entries.ID) as Total_Submitted
            FROM competitions, entries
            WHERE competitions.ID = entries.Competition_ID
                AND	entries.Member_ID=%s
                AND competitions.Competition_Date = %s ",
            $user_id,
            $competition_date
        )
        ;
        $return = $this->rpsdb->get_var($sql);

        return $return;
    }

    /**
     * Delete an entry
     *
     * @param integer $id
     *
     * @return boolean
     */
    public function deleteEntry($id)
    {
        $result = $this->rpsdb->delete('entries', ['ID' => $id]);

        return $result;
    }

    /**
     * Get entries submitted for the member on the given competition date in the given classification and medium
     *
     * @param integer $user_id
     * @param string  $competition_date
     * @param string  $classification
     * @param string  $medium
     *
     * @return array
     */
    public function getEntriesSubmittedByMember($user_id, $competition_date, $classification, $medium)
    {
        $competition_date = $this->rpsdb->getMysqldate($competition_date);

        $sql = $this->rpsdb->prepare(
            "SELECT entries.*
            FROM competitions, entries
            WHERE competitions.ID = entries.Competition_ID
                AND entries.Member_ID = %s
                AND competitions.Competition_Date = %s
                AND competitions.Classification = %s
                AND competitions.Medium = %s",
            $user_id,
            $competition_date,
            $classification,
            $medium
        )
        ;
        $return = $this->rpsdb->get_results($sql);

        return $return;
    }

    /**
     * Get the Entry Record by Id
     *
     * @param integer $id
     * @param string  $output
     *
     * @return QueryEntries
     */
    public function getEntryById($id, $output = OBJECT)
    {
        $sql = $this->rpsdb->prepare(
            "SELECT *
            FROM entries
            WHERE ID = %s",
            $id
        )
        ;

        return $this->rpsdb->get_row($sql, $output);
    }

    /**
     * General query method
     *
     * @param array  $query_vars
     * @param string $output
     *
     * @return QueryEntries|array|int
     */
    public function query(array $query_vars, $output = OBJECT)
    {
        /**
         * @var string  $join
         * @var string  $where
         * @var integer $offset
         * @var integer $number
         * @var string  $orderby
         * @var string  $order
         * @var boolean $count
         */
        $defaults = [
            'join'    => '',
            'where'   => '1=1',
            'offset'  => '',
            'number'  => '',
            'orderby' => 'ID',
            'order'   => 'ASC',
            'count'   => false
        ];
        $query_vars = wp_parse_args($query_vars, $defaults);
        extract($query_vars, EXTR_SKIP);

        $order = ('ASC' == strtoupper($order)) ? 'ASC' : 'DESC';

        $number = absint($number);
        $offset = absint($offset);

        if (!empty($number)) {
            if ($offset) {
                $limits = 'LIMIT ' . $offset . ',' . $number;
            } else {
                $limits = 'LIMIT ' . $number;
            }
        } else {
            $limits = '';
        }

        if ($count) {
            $fields = 'COUNT(*)';
            $orderby = 'ID';
        } else {
            $fields = '*';
        }

        $query = "SELECT $fields FROM entries $join WHERE $where ORDER BY $orderby $order $limits";

        if ($count) {
            return $this->rpsdb->get_var($query);
        }

        if ($number == 1) {
            return $this->rpsdb->get_row($query, $output);
        }

        return $this->rpsdb->get_results($query, $output);
    }

    /**
     * Update an entry.
     * If the $data parameter has 'ID' set to a value, then entry will be updated.
     *
     * @param array $data
     *
     * @return \WP_Error|boolean
     */
    public function updateEntry($data)
    {
        if (!empty($data['ID'])) {
            $entry_ID = (int) $data['ID'];
            $where = ['ID' => $entry_ID];
            if (!isset($data['Date_Modified'])) {
                $data['Date_Modified'] = current_time('mysql');
            }
            $data = stripslashes_deep($data);
            if (false === $this->rpsdb->update('entries', $data, $where)) {
                return new \WP_Error(
                    'db_update_error',
                    'Could not update entry in the database',
                    $this->rpsdb->last_error
                );
            }
        }

        return true;
    }
}
