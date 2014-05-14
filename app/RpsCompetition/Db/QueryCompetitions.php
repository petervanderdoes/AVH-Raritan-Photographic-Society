<?php
namespace RpsCompetition\Db;

use RpsCompetition\Db\RpsDb;

class QueryCompetitions
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
     * Get open competitions
     *
     * @param string $subset
     * @return multitype:
     */
    public function getOpenCompetitions($user_id, $subset = '')
    {

        // Select the list of open competitions that match this member's classification(s)
        if ($subset) {
            $and_medium_subset = " AND c.Medium like %s";
        } else {
            $and_medium_subset = '';
        }
        $user = get_userdata($user_id);

        if ($subset == 'digital') {
            $class1 = get_user_meta($user_id, 'rps_class_bw', true);
            $class2 = get_user_meta($user_id, 'rps_class_color', true);
        } else {
            $class1 = get_user_meta($user_id, 'rps_class_print_bw', true);
            $class2 = get_user_meta($user_id, 'rps_class_print_color', true);
        }
        // Select the list of open competitions that match this member's classification(s)
        $sql_pre_prepare = "SELECT c.Competition_Date, c.Classification, c.Medium, c.Theme, c.Closed
            FROM competitions c
            WHERE c.Classification IN  (%s)
                AND c.Closed = 'N'";
        $sql_pre_prepare .= $and_medium_subset;
        $sql_pre_prepare .= " GROUP BY c.ID ORDER BY c.Competition_Date, c.Medium";

        $subset_detail = 'color ' . $subset;
        $sql = $this->rpsdb->prepare($sql_pre_prepare, $class2, '%' . $subset_detail . '%');
        $color_set = $this->rpsdb->get_results($sql, ARRAY_A);
        $subset_detail = 'b&w ' . $subset;
        $sql = $this->rpsdb->prepare($sql_pre_prepare, $class1, '%' . $subset_detail . '%');
        $bw_set = $this->rpsdb->get_results($sql, ARRAY_A);
        $return = array_merge($color_set, $bw_set);
        sort($return);

        return $return;
    }

    /**
     * Get competitions between given dates
     *
     * @return Ambigous <mixed, NULL, multitype:multitype: , multitype:unknown >
     */
    public function getCompetitionDates($date_start, $date_end)
    {
        $sql = $this->rpsdb->prepare('SELECT Competition_Date, max(Max_Entries) as Max_Entries,
            max(Num_Judges) as Num_Judges
            FROM competitions
            WHERE Competition_Date >= %s AND
                Competition_Date < %s AND
                Special_Event = "N"
            GROUP BY Competition_Date
            ORDER BY Competition_Date', $date_start, $date_end);
        $return = $this->rpsdb->get_results($sql, ARRAY_A);

        return $return;
    }

    /**
     * Get closing date for specific competition
     *
     * @return Ambigous <string, NULL>
     */
    public function getCompetitionCloseDate($competition_date, $classification, $medium)
    {
        $sql = $this->rpsdb->prepare("SELECT Close_Date
            FROM competitions
            WHERE Competition_Date = DATE(%s)
                AND Classification = %s
                AND Medium = %s", $competition_date, $classification, $medium);
        $return = $this->rpsdb->get_var($sql);

        return $return;
    }

    /**
     * Check if competition is closed
     *
     * @return boolean
     */
    public function checkCompetitionClosed($competition_date, $classification, $medium)
    {
        $sql = $this->rpsdb->prepare("SELECT Closed
            FROM competitions
            WHERE Competition_Date = DATE(%s)
                AND Classification = %s
                AND Medium = %s", $competition_date, $classification, $medium);
        $closed = $this->rpsdb->get_var($sql);

        if ($closed == "Y") {
            $return = true;
        } else {
            $return = false;
        }

        return $return;
    }

    /**
     * Close all past competition
     */
    public function setAllPastCompetitionsClose()
    {
        $current_time = current_time('mysql');
        $sql = $this->rpsdb->prepare("UPDATE competitions
            SET Closed='Y',  Date_Modified = %s
            WHERE Closed='N'
                AND Close_Date < %s", $current_time, $current_time);
        $result = $this->rpsdb->query($sql);
    }

    /**
     * Get max entries for given competition.
     *
     * @return Ambigous <string, NULL>
     */
    public function getCompetitionMaxEntries($competiton_date, $classification, $medium)
    {
        $sql = $this->rpsdb->prepare("SELECT Max_Entries FROM competitions
                WHERE Competition_Date = DATE %s AND
                Classification = %s AND
                Medium = %s", $competiton_date, $classification, $medium);
        $return = $this->rpsdb->get_var($sql);

        return $return;
    }

    /**
     * Get competition by entry ID
     *
     * @param unknown $id
     * @param string $output
     */
    public function getCompetitionByEntryId($entry_id, $output = ARRAY_A)
    {
        $sql = $this->rpsdb->prepare("SELECT *
            FROM competitions c, entries e
            WHERE c.ID =  e.Competition_ID
                AND e.ID = %s", $entry_id);
        $result = $this->rpsdb->get_row($sql, $output);

        return $result;
    }

    /**
     * Get the whole competition record
     *
     * @param integer $id
     * @param string $output
     *            default is OBJECT
     * @return Ambigous <boolean, multitype:, NULL>
     */
    public function getCompetitionById($id, $output = OBJECT)
    {
        $where = $this->rpsdb->prepare('ID=%d', $id);
        $result = $this->query(array('where' => $where, 'number' => 1), $output);
        return $result;
    }

    /**
     * Delete a competition
     *
     * @param unknown $id
     * @return boolean
     */
    public function deleteCompetition($id)
    {
        $result = $this->rpsdb->delete('competitions', array('ID' => $id));

        return $result;
    }

    /**
     * Get competition by date, classification, medium
     *
     * @param unknown $competition_date
     * @param unknown $classification
     * @param unknown $medium
     * @return Ambigous <mixed, NULL, multitype:>
     */
    public function getCompetitionByDateClassMedium($competition_date, $classification, $medium)
    {
        $sql = $this->rpsdb->prepare("SELECT *
            FROM competitions
            WHERE Competition_Date = DATE %s
                AND Classification = %s
                AND Medium = %s", $competition_date, $classification, $medium);
        $return = $this->rpsdb->get_row($sql, ARRAY_A);

        return $return;
    }

    /**
     * Count competitions
     * Returns a class with the count of open, closed and total.
     *
     * @return StdClass
     */
    public function countCompetitions()
    {
        $where = '';

        $count = $this->rpsdb->get_results("SELECT Closed, COUNT( * ) AS num_competitions FROM competitions GROUP BY Closed", ARRAY_A);

        $total = 0;
        $status = array('N' => 'open', 'Y' => 'closed');
        $known_types = array_keys($status);
        foreach ((array) $count as $row) {
            // Don't count post-trashed toward totals
            $total += $row['num_competitions'];
            if (in_array($row['Closed'], $known_types)) {
                $stats[$status[$row['Closed']]] = (int) $row['num_competitions'];
            }
        }

        $stats['all'] = $total;
        foreach ($status as $key) {
            if (empty($stats[$key])) {
                $stats[$key] = 0;
            }
        }

        $stats = (object) $stats;

        return $stats;
    }

    /**
     * Insert a competition.
     *
     * If the $data parameter has 'ID' set to a value, then competition will be updated.
     *
     * @param array $data
     * @param bool $wp_error
     *            Optional. Allow return of WP_Error on failure.
     * @return object WP_Error on failure. The post ID on success.
     */
    public function insertCompetition($data)
    {
        // Are we updating or creating?
        if (!empty($data['ID'])) {
            $competition_ID = (int) $data['ID'];
            $where = array('ID' => $competition_ID);
            if (!isset($data['Date_Modified'])) {
                $data['Date_Modified'] = current_time('mysql');
            }
            if (false === $this->rpsdb->update('competitions', stripslashes_deep($data), $where)) {
                return new \WP_Error('db_update_error', 'Could not update competition into the database', $this->rpsdb->last_error);
            }
        } else {
            $current_time = current_time('mysql');
            //@formatter:off
            $default_options = array('Competition_Date' => $current_time,
                'Medium' => '',
                'Classification' => '',
                'Theme' => '',
                'Date_Created' => $current_time,
                'Date_Modified' => $current_time,
                'Closed' => 'N',
                'Scored' => 'N',
                'Max_Entries' => 2,
                'Num_Judges' => 1,
                'Special_Event' => 'N'
            );
            // @formatter:on
            $data = $data + $default_options;

            if (!isset($data['Close_Date'])) {
                $data['Close_Date'] = strtotime('-2 day', strtotime($data['Competition_Date']));
                $date_array = getdate($data['Close_Date']);
                $data['Close_Date'] = date('Y-m-d H:i:s', mktime(21, 00, 00, $date_array['mon'], $date_array['mday'], $date_array['year']));
            }
            if (false === $this->rpsdb->insert('competitions', stripslashes_deep($data))) {
                return new \WP_Error('db_insert_error', __('Could not insert competition into the database'), $this->rpsdb->last_error);
            }
            $competition_ID = (int) $this->rpsdb->insert_id;
        }

        return $competition_ID;
    }

    public function getScoredCompetitions($start_date, $end_date, $output = OBJECT)
    {
        $sql = $this->rpsdb->prepare('SELECT *
            FROM competitions
            WHERE Competition_Date >= %s AND
                Competition_Date <= %s AND
                Special_Event = "N" AND
                Scored = "Y"
            GROUP BY Competition_Date
            ORDER BY Competition_Date', $date_start, $date_end);
        $return = $this->rpsdb->get_results($sql, $output);

        return $return;
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
            $fields = '*, if(Classification = "Beginner",0, if(Classification = "Advanced",1,2)) as "Class_Code"';
        }

        $query = "SELECT $fields FROM competitions $join WHERE $where ORDER BY $orderby $order $limits";

        if ($count) {
            return $this->rpsdb->get_var($query);
        }

        if ($number == 1) {
            return $this->rpsdb->get_row($query, $output);
        }
        return $this->rpsdb->get_results($query, $output);
    }
}

?>
