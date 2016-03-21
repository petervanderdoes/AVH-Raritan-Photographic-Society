<?php
namespace RpsCompetition\Db;

use Illuminate\Config\Repository as Settings;
use RpsCompetition\Helpers\SeasonHelper;

/**
 * Class QueryCompetitions
 *
 * @package   RpsCompetition\Db
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 * @property  int    ID
 * @property  string Competition_Date
 * @property  string Medium
 * @property  string Classification
 * @property  string Theme
 * @property  string Date_Created
 * @property  string Date_Modified
 * @property  string Closed
 * @property  string Scored
 * @property  string Close_Date
 * @property  int    Max_Entries
 * @property  int    Num_Judges
 * @property  string Image_Size
 * @property  string Special_Event
 */
class QueryCompetitions
{
    private $rpsdb;
    private $settings;

    /**
     * Constructor
     *
     * @param Settings $settings
     * @param RpsDb    $rpsdb
     */
    public function __construct(Settings $settings, RpsDb $rpsdb)
    {
        $this->rpsdb = $rpsdb;
        $this->settings = $settings;
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
        $sql = $this->rpsdb->prepare('SELECT Closed
            FROM competitions
            WHERE Competition_Date = DATE(%s)
                AND Classification = %s
                AND Medium = %s',
                                     $competition_date,
                                     $classification,
                                     $medium);
        $closed = $this->rpsdb->get_var($sql);

        if ($closed == 'Y') {
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
        $count = $this->rpsdb->get_results('SELECT Closed, COUNT( * ) AS num_competitions FROM competitions GROUP BY Closed',
                                           ARRAY_A);

        $total = 0;
        $status = ['N' => 'open', 'Y' => 'closed'];
        $known_types = array_keys($status);
        $stats = [];
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
     * @param int $id
     *
     * @return bool
     */
    public function deleteCompetition($id)
    {
        $result = $this->rpsdb->delete('competitions', ['ID' => $id]);

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

        $sql = $this->rpsdb->prepare('SELECT *
            FROM competitions
            WHERE Competition_Date = %s
                AND Classification = %s
                AND Medium = %s',
                                     $competition_date,
                                     $classification,
                                     $medium);
        $return = $this->rpsdb->get_row($sql, $output);

        return $return;
    }

    /**
     * Get all competitions by date.
     *
     * @param string $competition_date_start
     * @param null   $competition_date_end
     * @param string $output
     *
     * @return array
     */
    public function getCompetitionByDates($competition_date_start, $competition_date_end = null, $output = OBJECT)
    {
        $competition_date_end = ($competition_date_end === null) ? $competition_date_start : $competition_date_end;

        $competition_date_start = $this->rpsdb->getMysqldate($competition_date_start);
        $competition_date_end = $this->rpsdb->getMysqldate($competition_date_end);

        $sql = $this->rpsdb->prepare('SELECT *
            FROM competitions
            WHERE Competition_Date >= %s AND
                Competition_Date <= %s',
                                     $competition_date_start,
                                     $competition_date_end);
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
    public function getCompetitionByEntryId($entry_id, $output = OBJECT)
    {
        $sql = $this->rpsdb->prepare('SELECT c.*
            FROM competitions c, entries e
            WHERE c.ID =  e.Competition_ID
                AND e.ID = %s',
                                     $entry_id);
        $result = $this->rpsdb->get_row($sql, $output);

        return $result;
    }

    /**
     * Get the whole competition record
     *
     * @param int    $id
     * @param string $output default is OBJECT
     *
     * @return QueryCompetitions|array
     */
    public function getCompetitionById($id, $output = OBJECT)
    {
        $where = $this->rpsdb->prepare('ID=%d', $id);
        $result = $this->query(['where' => $where, 'number' => 1], $output);

        return $result;
    }

    /**
     * Get season per season id.
     *
     * @param string     $season_id
     * @param array|null $filter
     * @param string     $output
     *
     * @return mixed
     */
    public function getCompetitionBySeasonId($season_id, $filter = null, $output = OBJECT)
    {
        $season_helper = new SeasonHelper($this->rpsdb);

        $sql_filter_array = ['1=1'];

        if (is_array($filter) && !empty($filter)) {
            $sql_filter_array = [];
            foreach ($filter as $field => $value) {
                $sql_filter_array[] = $field . ' = "' . $value . '"';
            }
        }

        $sql_filter = implode(' AND ', $sql_filter_array);

        list ($season_start_date, $season_end_date) = $season_helper->getSeasonStartEnd($season_id);

        $sql = $this->rpsdb->prepare('SELECT *
            FROM competitions
            WHERE Competition_Date >= %s AND
                Competition_Date <= %s AND
                ' . $sql_filter . '
            GROUP BY Competition_Date
            ORDER BY Competition_Date',
                                     $season_start_date,
                                     $season_end_date);
        $return = $this->rpsdb->get_results($sql, $output);

        return $return;
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
        $sql = $this->rpsdb->prepare('SELECT Close_Date
            FROM competitions
            WHERE Competition_Date = DATE(%s)
                AND Classification = %s
                AND Medium = %s',
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

        $sql = $this->rpsdb->prepare('SELECT Max_Entries FROM competitions
                WHERE Competition_Date = %s AND
                Classification = %s AND
                Medium = %s',
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
            $and_medium_subset = ' AND c.Medium like %s';
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
        $sql_pre_prepare = 'SELECT *
            FROM competitions c
            WHERE c.Classification IN  (%s)
                AND c.Closed = "N"
                AND c.Special_Event = "N"';
        $sql_pre_prepare .= $and_medium_subset;
        $sql_pre_prepare .= ' GROUP BY c.ID ORDER BY c.Competition_Date, c.Medium';

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
     * Get all scored competitions.
     * With the filter you can set extra filters for the select like Special_Event = "N"
     * The format of the filter array is "field"=>"value"
     *
     * @param string      $competition_date_start
     * @param string|null $competition_date_end
     * @param array       $filter
     * @param string      $output
     *
     * @return array
     */
    public function getScoredCompetitions($competition_date_start,
                                          $competition_date_end = null,
                                          $filter = [],
                                          $output = OBJECT)
    {
        $competition_date_end = ($competition_date_end === null) ? $competition_date_start : $competition_date_end;

        $competition_date_start = $this->rpsdb->getMysqldate($competition_date_start);
        $competition_date_end = $this->rpsdb->getMysqldate($competition_date_end);

        $sql_filter_array = ['1=1'];

        if (is_array($filter) && !empty($filter)) {
            $sql_filter_array = [];
            foreach ($filter as $field => $value) {
                $sql_filter_array[] = $field . ' = "' . $value . '" AND ';
            }
        }

        $sql_filter = implode(' AND ', $sql_filter_array);
        $sql = $this->rpsdb->prepare('SELECT *
            FROM competitions
            WHERE Competition_Date >= %s AND
                Competition_Date <= %s AND
                ' . $sql_filter . ' AND
                Scored = "Y"
            ORDER BY Competition_Date',
                                     $competition_date_start,
                                     $competition_date_end);
        $return = $this->rpsdb->get_results($sql, $output);

        return $return;
    }

    /**
     * Insert a competition.
     * If the $data parameter has 'ID' set to a value, then competition will be updated.
     *
     * @param array $data
     *
     * @return \WP_Error|int
     */
    public function insertCompetition(array $data)
    {
        $options = get_option('avh-rps');
        // Are we updating or creating?
        if (!empty($data['ID'])) {
            $competition_ID = (int) $data['ID'];
            $where = ['ID' => $competition_ID];
            if (!isset($data['Date_Modified'])) {
                $data['Date_Modified'] = current_time('mysql');
            }
            if (false === $this->rpsdb->update('competitions', stripslashes_deep($data), $where)) {
                return new \WP_Error('db_update_error',
                                     'Could not update competition into the database',
                                     $this->rpsdb->last_error);
            }
        } else {
            $current_time = current_time('mysql');
            $default_options = [
                'Competition_Date' => $current_time,
                'Medium'           => '',
                'Classification'   => '',
                'Theme'            => '',
                'Date_Created'     => $current_time,
                'Date_Modified'    => $current_time,
                'Closed'           => 'N',
                'Scored'           => 'N',
                'Max_Entries'      => 2,
                'Num_Judges'       => 1,
                'Image_Size'       => $options['default_image_size'],
                'Special_Event'    => 'N'
            ];
            $data = $data + $default_options;

            if (!isset($data['Close_Date'])) {
                $data['Close_Date'] = strtotime('-2 day', strtotime($data['Competition_Date']));
                $date_array = getdate($data['Close_Date']);
                $data['Close_Date'] = date('Y-m-d H:i:s',
                                           mktime(21,
                                                  00,
                                                  00,
                                                  $date_array['mon'],
                                                  $date_array['mday'],
                                                  $date_array['year']));
            }
            if (false === $this->rpsdb->insert('competitions', stripslashes_deep($data))) {
                return new \WP_Error('db_insert_error',
                                     __('Could not insert competition into the database'),
                                     $this->rpsdb->last_error);
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
     * @return QueryCompetitions|array
     */
    public function query(array $query_vars, $output = OBJECT)
    {
        /**
         * Define used variables after the extract function.
         *
         * @var string $order
         * @var string $join
         * @var string $where
         * @var string $offset
         * @var int    $number
         * @var string $orderby
         * @var string $order
         * @var bool   $count
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

        $query = sprintf('SELECT %s FROM competitions %s WHERE %s ORDER BY %s %s %s',
                         $fields,
                         $join,
                         $where,
                         $orderby,
                         $order,
                         $limits);

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
     * @return bool
     */
    public function setAllPastCompetitionsClose()
    {
        $current_time = current_time('mysql');
        $sql = $this->rpsdb->prepare('UPDATE competitions
            SET Closed="Y",  Date_Modified = %s
            WHERE Closed="N"
                AND Close_Date < %s',
                                     $current_time,
                                     $current_time);
        $result = $this->rpsdb->query($sql);

        return $result;
    }
}
