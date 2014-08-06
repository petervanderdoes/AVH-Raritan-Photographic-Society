<?php
namespace RpsCompetition\Db;

/**
 * Class QueryCompetitions
 *
 * @package RpsCompetition\Db
 * @property  int     ID
 * @property  string  Competition_Date
 * @property  string  Medium
 * @property  string  Classification
 * @property  string  Theme
 * @property  string  Date_Created
 * @property  string  Date_Modified
 * @property  string  Closed
 * @property  string  Scored
 * @property  string  Close_Date
 * @property  int     Max_Entries
 * @property  int     Num_Judges
 * @property  string  Special_Event
 */
class QueryCompetitions
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
     * Check if a competition is closed.
     * Check if the competition for the given date, classification and medium is closed.
     *
     * @param string $competition_date
     * @param string $classification
     * @param string $medium
     *
     * @return bool
     */
    public function checkCompetitionClosed($competition_date, $classification, $medium)
    {
        $sql = $this->rpsdb->prepare("SELECT Closed
            FROM competitions
            WHERE Competition_Date = DATE(%s)
                AND Classification = %s
                AND Medium = %s",
                                     $competition_date,
                                     $classification,
                                     $medium);
        $closed = $this->rpsdb->get_var($sql);

        if ($closed == "Y") {
            $return = true;
        } else {
            $return = false;
        }

        return $return;
    }

    /**
     * Count competitions.
     * Return an object with the count of open, closed and total competitions.
     *
     * @property int open
     * @property int closed
     * @property int all
     * @return QueryCompetitions|array
     */
    public function countCompetitions()
    {
        $count = $this->rpsdb->get_results("SELECT Closed, COUNT( * ) AS num_competitions FROM competitions GROUP BY Closed", ARRAY_A);

        $total = 0;
        $status = array('N' => 'open', 'Y' => 'closed');
        $known_types = array_keys($status);
        $stats = array();
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
     * Delete a competition.
     *
     * @param integer $id
     *
     * @return boolean
     */
    public function deleteCompetition($id)
    {
        $result = $this->rpsdb->delete('competitions', array('ID' => $id));

        return $result;
    }

    /**
     * Get competition by date, classification, medium.
     *
     * @param string $competition_date
     * @param string $classification
     * @param string $medium
     * @param string $output
     *
     * @return QueryCompetitions|array
     */
    public function getCompetitionByDateClassMedium($competition_date, $classification, $medium, $output = OBJECT)
    {
        $competition_date = $this->rpsdb->getMysqldate($competition_date);

        $sql = $this->rpsdb->prepare("SELECT *
            FROM competitions
            WHERE Competition_Date = %s
                AND Classification = %s
                AND Medium = %s",
                                     $competition_date,
                                     $classification,
                                     $medium);
        $return = $this->rpsdb->get_row($sql, $output);

        return $return;
    }

    /**
     * Get all competitions by date.
     *
     * @param string $date_start
     * @param string $date_end
     * @param string $output
     *
     * @return array
     */
    public function getCompetitionByDates($date_start, $date_end, $output = OBJECT)
    {
        $date_start = $this->rpsdb->getMysqldate($date_start);
        $date_end = $this->rpsdb->getMysqldate($date_end);

        $sql = $this->rpsdb->prepare("SELECT *
            FROM competitions
            WHERE Competition_Date >= %s AND
                Competition_Date <= %s",
                                     $date_start,
                                     $date_end);
        $result = $this->rpsdb->get_results($sql, $output);

        return $result;
    }

    /**
     * Get competition by entry ID
     *
     * @param int    $entry_id
     * @param string $output
     *
     * @return QueryCompetitions|array
     */
    public function getCompetitionByEntryId($entry_id, $output = ARRAY_A)
    {
        $sql = $this->rpsdb->prepare("SELECT c.*
            FROM competitions c, entries e
            WHERE c.ID =  e.Competition_ID
                AND e.ID = %s",
                                     $entry_id);
        $result = $this->rpsdb->get_row($sql, $output);

        return $result;
    }

    /**
     * Get the whole competition record
     *
     * @param integer $id
     * @param string  $output
     *            default is OBJECT
     *
     * @return QueryCompetitions|array
     */
    public function getCompetitionById($id, $output = OBJECT)
    {
        $where = $this->rpsdb->prepare('ID=%d', $id);
        $result = $this->query(array('where' => $where, 'number' => 1), $output);

        return $result;
    }

    /**
     * Get closing date for specific competition
     *
     * @param string $competition_date
     * @param string $classification
     * @param string $medium
     *
     * @return string
     */
    public function getCompetitionCloseDate($competition_date, $classification, $medium)
    {
        $sql = $this->rpsdb->prepare("SELECT Close_Date
            FROM competitions
            WHERE Competition_Date = DATE(%s)
                AND Classification = %s
                AND Medium = %s",
                                     $competition_date,
                                     $classification,
                                     $medium);
        $return = $this->rpsdb->get_var($sql);

        return $return;
    }

    /**
     * Get competitions between given dates
     *
     * @param string $date_start
     * @param string $date_end
     *
     * @return array
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
            ORDER BY Competition_Date',
                                     $date_start,
                                     $date_end);
        $return = $this->rpsdb->get_results($sql, ARRAY_A);

        return $return;
    }

    /**
     * Get max entries for given competition.
     *
     * @param string $competition_date
     * @param string $classification
     * @param string $medium
     *
     * @return int
     */
    public function getCompetitionMaxEntries($competition_date, $classification, $medium)
    {
        $competition_date = $this->rpsdb->getMysqldate($competition_date);

        $sql = $this->rpsdb->prepare("SELECT Max_Entries FROM competitions
                WHERE Competition_Date = %s AND
                Classification = %s AND
                Medium = %s",
                                     $competition_date,
                                     $classification,
                                     $medium);
        $return = $this->rpsdb->get_var($sql);

        return $return;
    }

    /**
     * Get open competitions
     *
     * @param int    $user_id
     * @param string $subset
     * @param string $output
     *
     * @return QueryCompetitions|array
     */
    public function getOpenCompetitions($user_id, $subset = '', $output = OBJECT)
    {

        // Select the list of open competitions that match this member's classification(s)
        if ($subset) {
            $and_medium_subset = " AND c.Medium like %s";
        } else {
            $and_medium_subset = '';
        }

        if (strtolower($subset) == 'digital') {
            $class1 = get_user_meta($user_id, 'rps_class_bw', true);
            $class2 = get_user_meta($user_id, 'rps_class_color', true);
        } else {
            $class1 = get_user_meta($user_id, 'rps_class_print_bw', true);
            $class2 = get_user_meta($user_id, 'rps_class_print_color', true);
        }
        // Select the list of open competitions that match this member's classification(s)
        $sql_pre_prepare = "SELECT *
            FROM competitions c
            WHERE c.Classification IN  (%s)
                AND c.Closed = 'N'";
        $sql_pre_prepare .= $and_medium_subset;
        $sql_pre_prepare .= " GROUP BY c.ID ORDER BY c.Competition_Date, c.Medium";

        $subset_detail = 'color ' . $subset;
        $sql = $this->rpsdb->prepare($sql_pre_prepare, $class2, '%' . $subset_detail . '%');
        $color_set = $this->rpsdb->get_results($sql, $output);
        $subset_detail = 'b&w ' . $subset;
        $sql = $this->rpsdb->prepare($sql_pre_prepare, $class1, '%' . $subset_detail . '%');
        $bw_set = $this->rpsdb->get_results($sql, $output);
        $return = array_merge($color_set, $bw_set);

        return $return;
    }

    /**
     * Get all regular (non-special-event) scored competitions.
     *
     * @param string $date_start
     * @param string $date_end
     * @param string $output
     *
     * @return QueryCompetitions|array
     */
    public function getScoredCompetitions($date_start, $date_end, $output = OBJECT)
    {
        $sql = $this->rpsdb->prepare('SELECT *
            FROM competitions
            WHERE Competition_Date >= %s AND
                Competition_Date <= %s AND
                Special_Event = "N" AND
                Scored = "Y"
            GROUP BY Competition_Date
            ORDER BY Competition_Date',
                                     $date_start,
                                     $date_end);
        $return = $this->rpsdb->get_results($sql, $output);

        return $return;
    }

    /**
     * Insert a competition.
     * If the $data parameter has 'ID' set to a value, then competition will be updated.
     *
     * @param array $data
     *
     * @return object WP_Error on failure. The post ID on success.
     */
    public function insertCompetition(array $data)
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

    /**
     * General query method.
     *
     * @param array  $query_vars
     * @param string $output
     *
     * @return object|array
     */
    public function query(array $query_vars, $output = OBJECT)
    {
        /**
         * Define used variables after the extract function.
         *
         * @var string  $order
         * @var string  $join
         * @var string  $where
         * @var string  $offset
         * @var int     $number
         * @var string  $orderby
         * @var string  $order
         * @var boolean $count
         */
        $defaults = array('join' => '', 'where' => '1=1', 'offset' => '', 'number' => '', 'orderby' => 'ID', 'order' => 'ASC', 'count' => false);
        $query_vars = array_merge($defaults, $query_vars);

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

        if (isset($status)) {
            switch ($status) {
                case 'open':
                    $where = 'Closed="N"';
                    break;
                case 'closed':
                    $where = 'Closed="Y"';
                    break;
                case 'all':
                default:
                    $where = '1=1';
                    break;
            }
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

    /**
     * Close all competitions with a closing date in the past.
     *
     * @return boolean
     */
    public function setAllPastCompetitionsClose()
    {
        $current_time = current_time('mysql');
        $sql = $this->rpsdb->prepare("UPDATE competitions
            SET Closed='Y',  Date_Modified = %s
            WHERE Closed='N'
                AND Close_Date < %s",
                                     $current_time,
                                     $current_time);
        $result = $this->rpsdb->query($sql);

        return $result;
    }
}
