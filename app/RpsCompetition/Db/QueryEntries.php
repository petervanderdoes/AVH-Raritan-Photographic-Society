<?php
namespace RpsCompetition\Db;

use RpsCompetition\Db\RpsDb;

class QueryEntries
{

    private $rpsdb;

    /**
     * PHP5 constructor
     */
    public function __construct(RpsDb $rpsdb)
    {
        $this->rpsdb = $rpsdb;
    }

    /**
     * Count entries submitted by this member for this competition date.
     *
     * @return Ambigous <string, NULL>
     */
    public function countEntriesSubmittedByMember($user_id)
    {
        $sql = $this->rpsdb->prepare("SELECT COUNT(entries.ID) as Total_Submitted
            FROM competitions, entries
            WHERE competitions.ID = entries.Competition_ID
                AND	entries.Member_ID=%s
                AND competitions.Competition_Date = DATE %s ", $user_id, $this->settings->comp_date);
        $return = $this->rpsdb->get_var($sql);

        return $return;
    }

    /**
     * Get entries submitted for the member on the given competition date in the given classification and medium
     *
     * @param unknown $user_id
     * @param unknown $competition_date
     * @param unknown $classification
     * @param unknown $medium
     * @return Ambigous <mixed, NULL, multitype:multitype: , multitype:unknown >
     */
    public function getEntriesSubmittedByMember($user_id, $competition_date, $classification, $medium)
    {
        $sql = $this->rpsdb->prepare("SELECT entries.ID, entries.Title, entries.Client_File_Name, entries.Server_File_Name, competitions.Max_Entries
            FROM competitions, entries
            WHERE competitions.ID = entries.Competition_ID
                AND entries.Member_ID = %s
                AND competitions.Competition_Date = DATE %s
                AND competitions.Classification = %s
                AND competitions.Medium = %s", $user_id, $this->settings->comp_date, $this->settings->classification, $this->settings->medium);
        $return = $this->rpsdb->get_results($sql);

        return $return;
    }

    /**
     * Get the Entry Record by Id
     *
     * @param integer $id
     * @param string $output
     * @return Ambigous <mixed, NULL, multitype:>|multitype:
     */
    public function getEntryById($id, $output = ARRAY_A)
    {
        $sql = $this->rpsdb->prepare("SELECT *
            FROM entries
            WHERE ID = %s", $id);
        return $this->rpsdb->get_row($sql, $output);
    }

    /**
     * Check if the title already exists for the given competition.
     *
     * @param integer $id
     * @param string $title
     * @param integer $user_id
     * @return Ambigous <boolean, string, NULL>
     */
    public function checkDuplicateTitle($id, $title, $user_id)
    {
        $sql = $this->rpsdb->prepare("SELECT ID
            FROM entries
            WHERE Competition_ID = %s
                AND Member_ID = %s
                AND Title = %s", $id, $user_id, $title);
        $return = $this->rpsdb->get_var($sql);
        if ($return > 0) {
            $return = true;
        } else {
            $return = false;
        }

        return $return;
    }

    /**
     * Get the amount of entries for the given competition.
     *
     * @param integer $id
     * @return Ambigous <string, NULL>
     */
    public function countEntriesByCompetitionId($id, $user_id)
    {
        $sql = $this->rpsdb->prepare("SELECT count(ID) FROM entries
            WHERE Competition_ID = %s
                AND Member_ID = %s", $id, $user_id);
        $return = $this->rpsdb->get_var($sql);

        return $return;
    }

    /**
     * Count the entries for a member on a given competition date
     *
     * @param unknown $date
     * @param unknown $user_id
     * @return Ambigous <string, NULL>
     */
    public function countEntriesByCompetitionDate($date, $user_id)
    {
        $sql = $this->rpsdb->prepare("SELECT count(e.ID) as Total_Entries_Submitted
                    FROM entries e, competitions c
                    WHERE e.Competition_ID = c.ID AND
                    c.Competition_Date = DATE(%s) AND
                    e.Member_ID = %s", $date, $user_id);
        $return = $this->rpsdb->get_var($sql);

        return $return;
    }

    /**
     * Add an entry
     *
     * @param array $data
     * @param integer $user_id
     * @return Ambigous <number, false>
     */
    public function addEntry($data, $user_id)
    {
        $data['Member_ID'] = $user_id;
        $data['Date_Created'] = current_time('mysql');
        $return = $this->rpsdb->insert('entries', $data);

        return $return;
    }

    /**
     * Update an entry.
     *
     * If the $data parameter has 'ID' set to a value, then entry will be updated.
     *
     * @param array $data
     * @param bool $wp_error
     *            Optional. Allow return of WP_Error on failure.
     * @return object WP_Error on failure. The post ID on success.
     */
    public function updateEntry($data)
    {
        if (!empty($data['ID'])) {
            $entry_ID = (int) $data['ID'];
            $where = array('ID' => $entry_ID);
            if (!isset($data['Date_Modified'])) {
                $data['Date_Modified'] = current_time('mysql');
            }
            $data = stripslashes_deep($data);
            if (false === $this->rpsdb->update('entries', $data, $where)) {
                return new \WP_Error('db_update_error', 'Could not update entry in the database', $this->rpsdb->last_error);
            }
        }

        return true;
    }

    /**
     * Delete an entry
     *
     * @param integer $id
     * @return unknown
     */
    public function deleteEntry($id)
    {
        $result = $this->rpsdb->delete('entries', array('ID' => $id));
        return $result;
    }

    /**
     * General query method
     *
     * @param array $query_vars
     * @param string $table
     * @return Ambigous <string, NULL>|Ambigous <\RpsCompetition\Db\mixed, mixed>
     */
    public function query(array $query_vars, $output = OBJECT)
    {
        $defaults = array('join' => '', 'where' => '1=1', 'offset' => '', 'number' => '', 'orderby' => 'ID', 'order' => 'ASC', 'count' => false);
        $this->_query_vars = wp_parse_args($query_vars, $defaults);
        extract($this->_query_vars, EXTR_SKIP);

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
}
