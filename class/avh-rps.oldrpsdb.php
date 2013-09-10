<?php
if ( !defined('AVH_FRAMEWORK') )
    die('You are not allowed to call this page directly.');

class AVH_RPS_OldRpsDb
{

    /**
     *
     * @var AVH_RPS_Core
     */
    private $_core;

    /**
     *
     * @var AVH_Settings_Registry
     */
    private $_settings;

    /**
     *
     * @var AVH_Class_registry
     */
    private $_classes;
    private $_rpsdb;
    private $_user_id;

    /**
     * PHP5 constructor
     */
    public function __construct()
    {
        // Get The Registry
        $this->_settings = AVH_RPS_Settings::getInstance();
        $this->_classes = AVH_RPS_Classes::getInstance();

        $this->_core = $this->_classes->load_class('Core', 'plugin', true);
        $this->_rpsdb = new wpdb(RPS_DB_USER, RPS_DB_PASSWORD, RPS_DB_NAME, DB_HOST);
        $this->_rpsdb->show_errors();
    }

    public function getSeasonList($order = "ASC")
    {
        $sql = $this->_rpsdb->prepare('SELECT DISTINCT if(month(Competition_Date) >= %s and month(Competition_Date) <= %s,
            concat_WS("-",year(Competition_Date),substr(year(Competition_Date)+1,3,2)),
            concat_WS("-",year(Competition_Date)-1,substr(year(Competition_Date),3,2))) as "Season"
            FROM competitions
            ORDER BY Season ' . $order, $this->_settings->club_season_start_month_num, $this->_settings->club_season_end_month_num);

        $_result = $this->_rpsdb->get_results($sql, ARRAY_A);
        foreach ( $_result as $key => $value ) {
            $_seasons[$key] = $value['Season'];
        }
        return $_seasons;
    }

    public function getSeasonListOneEntry()
    {
        $sql = $this->_rpsdb->prepare('SELECT if(month(c.Competition_Date) >= %s and month(c.Competition_Date) <= %s,
            concat_WS(" - ",year(c.Competition_Date),substr(year(c.Competition_Date)+1,3,2)),
            concat_WS(" - ",year(c.Competition_Date)-1,substr(year(c.Competition_Date),3,2))) as "Season",
            count(e.ID)
            FROM competitions c, entries e
            WHERE c.ID = e.Competition_ID
            GROUP BY Season
            HAVING count(e.ID) > 0
            ORDER BY Season', $this->_settings->club_season_start_month_num, $this->_settings->club_season_end_month_num);

        $_result = $this->_rpsdb->get_results($sql, ARRAY_A);
        foreach ( $_result as $key => $value ) {
            $_seasons[$key] = $value['Season'];
        }
        return $_seasons;
    }

    public function getClubCompetitionDates()
    {
        $sql = $this->_rpsdb->prepare('SELECT Competition_Date, max(Max_Entries) as Max_Entries,
            max(Num_Judges) as Num_Judges
            FROM competitions
            WHERE Competition_Date >= %s AND
                Competition_Date < %s AND
                Special_Event = "N"
            GROUP BY Competition_Date
            ORDER BY Competition_Date', $this->_settings->season_start_date, $this->_settings->season_end_date);
        $_return = $this->_rpsdb->get_results($sql, ARRAY_A);
        return $_return;
    }

    public function getClubCompetitionResults()
    {
        $sql = $this->_rpsdb->prepare('SELECT c.Competition_Date, c.Medium, c.Classification, c.Special_Event,
            if(c.Classification = "Beginner",0,
            if(c.Classification = "Advanced",1,2)) as "Class_Code",
            e.Score, e.Award, e.Member_ID
            FROM competitions as c, entries as e
            WHERE c.ID = e.Competition_ID AND
                Competition_Date >= %s AND
                Competition_Date < %s AND
                Special_Event = "N"
            ORDER BY c.Medium DESC, Class_Code, c.Competition_Date', $this->_settings->season_start_date, $this->_settings->season_end_date);

        $_x = $this->_rpsdb->get_results($sql, ARRAY_A);
        foreach ( $_x as $key => $_rec ) {
            $user_info = get_userdata($_rec['Member_ID']);
            $_rec['FirstName'] = $user_info->user_firstname;
            $_rec['LastName'] = $user_info->user_lastname;
            $_rec['Username'] = $user_info->user_login;
            $_return[] = $_rec;
        }
        return $_return;
    }

    public function getMonthlyScores()
    {
        $sql = $this->_rpsdb->prepare('SELECT DISTINCT YEAR(Competition_Date) as "Year",
            MONTH(Competition_Date) as "Month_Num",
            MONTHNAME(Competition_Date) AS "Month",
            Theme
            FROM competitions
            WHERE Competition_Date >= %s AND
                Competition_date < %s AND
                Scored="Y" ORDER BY Competition_Date', $this->_settings->season_start_date, $this->_settings->season_end_date);
        $_return = $this->_rpsdb->get_results($sql, ARRAY_A);
        return $_return;
    }

    public function getMaxAwards()
    {
        $sql = $this->_rpsdb->prepare("SELECT MAX(z.Num_Awards) AS Max_Num_Awards FROM
                (SELECT c.Competition_Date, c.Classification, c.Medium, COUNT(e.Award) AS Num_Awards
                    FROM competitions c, entries e
                        WHERE c.ID = e.Competition_ID AND
                            c.Competition_Date >= %s AND
                            c.Competition_Date < %s AND
                            Scored = 'Y' AND
                            e.Award IS NOT null
                        GROUP BY c.Competition_Date, c.Classification, c.Medium) z", $this->_settings->min_date, $this->_settings->max_date);
        $_return = $this->_rpsdb->get_var($sql);
        return $_return;
    }

    public function getWinners()
    {
        $sql = $this->_rpsdb->prepare("SELECT c.Competition_Date, c.Classification,
                if(c.Classification = 'Beginner',1,
                if(c.Classification = 'Advanced',2,
                if(c.Classification = 'Salon',3,0))) as \"Class_Code\",
                c.Medium, e.Title, e.Server_File_Name, e.Award, e.Member_ID
            FROM competitions c, entries e
                WHERE c.ID = e.Competition_ID and
                    c.Competition_Date >= %s AND
                    c.Competition_Date < %s AND
                    e.Award Is Not Null
                ORDER BY c.Competition_Date, Class_Code, c.Medium, e.Award", $this->_settings->min_date, $this->_settings->max_date);
        $_x = $this->_rpsdb->get_results($sql, ARRAY_A);
        foreach ( $_x as $_rec ) {
            $user_info = get_userdata($_rec['Member_ID']);
            $_rec['FirstName'] = $user_info->user_firstname;
            $_rec['LastName'] = $user_info->user_lastname;
            $_rec['Username'] = $user_info->user_login;
            $_return[] = $_rec;
        }

        return $_return;
    }

    public function getEightsAndHigher($classification, $season)
    {
        $sql = $this->_rpsdb->prepare("SELECT c.Competition_Date, c.Classification,
                if(c.Classification = 'Beginner',1,
                if(c.Classification = 'Advanced',2,
                if(c.Classification = 'Salon',3,0))) as \"Class_Code\",
                c.Medium, e.Title, e.Server_File_Name, e.Award, e.Member_ID
            FROM competitions c, entries e
                WHERE c.ID = e.Competition_ID AND
                    c.Competition_Date >= %s AND
                    e.Score >= 8
                ORDER BY c.Competition_Date, Class_Code, c.Medium, e.Score", $season);
        $_x = $this->_rpsdb->get_results($sql, ARRAY_A);
        foreach ( $_x as $_rec ) {
            $user_info = get_userdata($_rec['Member_ID']);
            $_rec['FirstName'] = $user_info->user_firstname;
            $_rec['LastName'] = $user_info->user_lastname;
            $_rec['Username'] = $user_info->user_login;
            $_return[] = $_rec;
        }

        return $_return;
    }

    public function getScoresCurrentUser()
    {
        $sql = $this->_rpsdb->prepare("SELECT c.Competition_Date, c.Medium, c.Theme, e.Title, e.Server_File_Name,
        e.Score, e.Award
        FROM competitions as c, entries as e
        WHERE c.ID = e.Competition_ID AND
        c.Competition_Date >= %s AND
        c.Competition_Date < %s AND
        e.Member_ID = %s
        ORDER BY c.Competition_Date, c.Medium", $this->_settings->season_start_date, $this->_settings->season_end_date, $this->_user_id);
        $_return = $this->_rpsdb->get_results($sql, ARRAY_A);

        return $_return;
    }

    public function getOpenCompetitions($subset)
    {

        // Select the list of open competitions that match this member's classification(s)
        if ( $subset ) {
            $and_medium_subset = " AND c.Medium like %s";
        } else {
            $and_medium_subset = '';
        }
        $user = get_userdata($this->_user_id);

        $_class1 = $user->rps_class_bw;
        $_class2 = $user->rps_class_color;
        // Select the list of open competitions that match this member's classification(s)
        $_sql = "SELECT c.Competition_Date, c.Classification, c.Medium, c.Theme, c.Closed
            FROM competitions c
            WHERE c.Classification IN  (%s)
                AND c.Closed = 'N'";
        $_sql .= $and_medium_subset;
        $_sql .= " GROUP BY c.ID ORDER BY c.Competition_Date, c.Medium";

        $subset_detail = 'color ' . $subset;
        $sql = $this->_rpsdb->prepare($_sql, $_class2, '%' . $subset_detail . '%');
        $color_set = $this->_rpsdb->get_results($sql, ARRAY_A);
        $subset_detail = 'b&w ' . $subset;
        $sql = $this->_rpsdb->prepare($_sql, $_class1, '%' . $subset_detail . '%');
        $bw_set = $this->_rpsdb->get_results($sql, ARRAY_A);
        $_return = array_merge($color_set, $bw_set);
        sort($_return);
        return $_return;
    }

    public function getCompetitions($query_vars, $output = OBJECT)
    {
        $defaults = array('status' => '','search' => '','offset' => '','number' => '','orderby' => 'ID','order' => 'ASC','count' => false);
        $this->_query_vars = wp_parse_args($query_vars, $defaults);
        extract($this->_query_vars, EXTR_SKIP);

        $number = absint($number);
        $offset = absint($offset);

        if ( !empty($number) ) {
            if ( $offset ) {
                $limits = 'LIMIT ' . $offset . ',' . $number;
            } else {
                $limits = 'LIMIT ' . $number;
            }
        } else {
            $limits = '';
        }

        if ( $count ) {
            $fields = 'COUNT(*)';
            $orderby = 'ID';
            $order = '';
        } else {
            $fields = '*, if(Classification = "Beginner",0, if(Classification = "Advanced",1,2)) as "Class_Code"';
        }

        $join = '';
        switch ( $status )
        {
            case 'open':
                $where = 'Closed = "N"';
                break;
            case 'closed':
                $where = 'Closed = "Y"';
                break;
            case 'all':
                $where = '1=1';
                break;
            default:
                break;
        }
        $query = "SELECT $fields FROM competitions $join WHERE $where ORDER BY $orderby $order $limits";

        if ( $count ) {
            return $this->_rpsdb->get_var($query);
        }

        $_result = $this->_rpsdb->get_results($query);
        if ( $output == OBJECT ) {
            return $_result;
        } elseif ( $output == ARRAY_A ) {
            $_result_array = get_object_vars($_result);
            return $_result_array;
        } elseif ( $output == ARRAY_N ) {
            $_result_array = array_values(get_object_vars($_result));
            return $_result_array;
        } else {
            return $_result;
        }
    }

    public function getEntries($query_vars, $output = OBJECT)
    {
        $defaults = array('join' => '','where' => '1=1','fields' => '*','offset' => '','number' => '','orderby' => 'ID','order' => 'ASC','count' => false);
        $this->_query_vars = wp_parse_args($query_vars, $defaults);
        extract($this->_query_vars, EXTR_SKIP);

        $order = ( 'ASC' == strtoupper($order) ) ? 'ASC' : 'DESC';

        $number = absint($number);
        $offset = absint($offset);

        if ( !empty($number) ) {
            if ( $offset ) {
                $limits = 'LIMIT ' . $offset . ',' . $number;
            } else {
                $limits = 'LIMIT ' . $number;
            }
        } else {
            $limits = '';
        }

        if ( $count ) {
            $fields = 'COUNT(*)';
            $orderby = 'ID';
        }

        $query = "SELECT $fields FROM entries $join WHERE $where ORDER BY $orderby $order $limits";

        if ( $count ) {
            return $this->_rpsdb->get_var($query);
        }

        $_result = $this->_rpsdb->get_results($query);
        if ( $output == OBJECT ) {
            return $_result;
        } elseif ( $output == ARRAY_A ) {
            $_result_array = get_object_vars($_result);
            return $_result_array;
        } elseif ( $output == ARRAY_N ) {
            $_result_array = array_values(get_object_vars($_result));
            return $_result_array;
        } else {
            return $_result;
        }
    }

    /**
     * Insert a competition.
     *
     * If the $data parameter has 'ID' set to a value, then competition will be updated.
     *
     * @param array $data
     * @param bool $wp_error
     *        Optional. Allow return of WP_Error on failure.
     * @return object WP_Error on failure. The post ID on success.
     */
    public function insertCompetition($data)
    {
        // Are we updating or creating?
        $update = false;
        if ( !empty($data['ID']) ) {
            $update = true;
            $competition_ID = (int) $data['ID'];
            $where = array('ID' => $competition_ID);
            if ( !isset($data['Date_Modified']) ) {
                $data['Date_Modified'] = current_time('mysql');
            }
            $data = stripslashes_deep($data);
            if ( false === $this->_rpsdb->update('competitions', $data, $where) ) {
                return new WP_Error('db_update_error', 'Could not update competition into the database', $this->_rpsdb->last_error);
            }
        } else {
            $competition_ID = 0;
            if ( !isset($data['Competition_Date']) ) {
                $data['Competition_Date'] = current_time('mysql');
            }
            if ( !isset($data['Medium']) ) {
                $data['Medium'] = '';
            }
            if ( !isset($data['Classification']) ) {
                $data['Classification'] = '';
            }
            if ( !isset($data['Theme']) ) {
                $data['Theme'] = '';
            }
            if ( !isset($data['Date_Created']) ) {
                $data['Date_Created'] = current_time('mysql');
            }
            if ( !isset($data['Date_Modified']) ) {
                $data['Date_Modified'] = current_time('mysql');
            }
            if ( !isset($data['Closed']) ) {
                $data['Closed'] = 'N';
            }
            if ( !isset($data['Scored']) ) {
                $data['Scored'] = 'N';
            }
            if ( !isset($data['Close_Date']) ) {
                $data['Close_Date'] = strtotime('-2 day', strtotime($data['Competition_Date']));
                $date_array = getdate($data['Close_Date']);
                $data['Close_Date'] = date('Y-m-d H:i:s', mktime(18, 00, 00, $date_array['mon'], $date_array['mday'], $date_array['year']));
            }
            if ( !isset($data['Max_Entries']) ) {
                $data['Max_Entries'] = 2;
            }
            if ( !isset($data['Num_Judges']) ) {
                $data['Num_Judges'] = 1;
            }
            if ( !isset($data['Special_Event']) ) {
                $data['Special_Event'] = 'N';
            }
            $data = stripslashes_deep($data);
            if ( false === $this->_rpsdb->insert('competitions', $data) ) {
                return new WP_Error('db_insert_error', __('Could not insert competition into the database'), $this->_rpsdb->last_error);
            }
            $competition_ID = (int) $this->_rpsdb->insert_id;
        }

        return $competition_ID;
    }

    public function getCompetitionCloseDate()
    {
        $sql = $this->_rpsdb->prepare("SELECT Close_Date
            FROM competitions
            WHERE Competition_Date = DATE(%s)
                AND Classification = %s
                AND Medium = %s", $this->_settings->comp_date, $this->_settings->classification, $this->_settings->medium);
        $_return = $this->_rpsdb->get_var($sql);
        return $_return;
    }

    public function getCompetionClosed()
    {
        $sql = $this->_rpsdb->prepare("SELECT Closed
            FROM competitions
            WHERE Competition_Date = DATE(%s)
                AND Classification = %s
                AND Medium = %s", $this->_settings->comp_date, $this->_settings->classification, $this->_settings->medium);
        $_closed = $this->_rpsdb->get_var($sql);
        if ( $_closed == "Y" ) {
            $_return = true;
        } else {
            $_return = false;
        }
        return $_return;
    }

    public function setCompetitionClose()
    {
        $_current_time = current_time('mysql');
        $sql = $this->_rpsdb->prepare("UPDATE competitions
            SET Closed='Y',  Date_Modified = %s
            WHERE Closed='N'
                AND Close_Date < %s", $_current_time, $_current_time);
        $result = $this->_rpsdb->query($sql);
    }

    public function getCompetitionMaxEntries()
    {
        $sql = $this->_rpsdb->prepare("SELECT Max_Entries FROM competitions
                WHERE Competition_Date = DATE %s AND
                Classification = %s AND
                Medium = %s", $this->_settings->comp_date, $this->_settings->classification, $this->_settings->medium);
        $_return = $this->_rpsdb->get_var($sql);
        return $_return;
    }

    public function checkMaxEntriesOnId($id)
    {
        $sql = $this->_rpsdb->prepare("SELECT count(ID) FROM entries
            WHERE Competition_ID = %s
                AND Member_ID = %s", $id, $this->_user_id);
        $_return = $this->_rpsdb->get_var($sql);
        return $_return;
    }

    public function checkMaxEntriesOnDate()
    {
        $sql = $this->_rpsdb->prepare("SELECT count(e.ID) as Total_Entries_Submitted
                    FROM entries e, competitions c
                    WHERE e.Competition_ID = c.ID AND
                    c.Competition_Date = DATE(%s) AND
                    e.Member_ID = %s", $this->_settings->comp_date, $this->_user_id);
        $_return = $this->_rpsdb->get_var($sql);
        return $_return;
    }

    public function getCompetitionEntriesUser()
    {
        $sql = $this->_rpsdb->prepare("SELECT COUNT(entries.ID) as Total_Submitted
            FROM competitions, entries
            WHERE competitions.ID = entries.Competition_ID
                AND	entries.Member_ID=%s
                AND competitions.Competition_Date = DATE %s ", $this->_user_id, $this->_settings->comp_date);
        $_return = $this->_rpsdb->get_var($sql);
        return $_return;
    }

    public function getCompetitionSubmittedEntriesUser()
    {
        $sql = $this->_rpsdb->prepare("SELECT entries.ID, entries.Title, entries.Client_File_Name, entries.Server_File_Name, competitions.Max_Entries
            FROM competitions, entries
            WHERE competitions.ID = entries.Competition_ID
                AND entries.Member_ID = %s
                AND competitions.Competition_Date = DATE %s
                AND competitions.Classification = %s
                AND competitions.Medium = %s", $this->_user_id, $this->_settings->comp_date, $this->_settings->classification, $this->_settings->medium);
        $_return = $this->_rpsdb->get_results($sql, ARRAY_A);
        return $_return;
    }

    public function getEntryInfo($id, $output = ARRAY_A)
    {
        $sql = $this->_rpsdb->prepare("SELECT *
            FROM entries
            WHERE ID = %s", $id);
        $result = $this->_rpsdb->get_row($sql);
        if ( $output == OBJECT ) {
            return $result;
        } elseif ( $output == ARRAY_A ) {
            $resultArray = get_object_vars($result);
            return $resultArray;
        } elseif ( $output == ARRAY_N ) {
            $resultArray = array_values(get_object_vars($result));
            return $resultArray;
        } else {
            return $result;
        }
        return $result;
    }

    public function getCompetitionByID($id, $output = ARRAY_A)
    {
        $sql = $this->_rpsdb->prepare("SELECT Competition_Date, Classification, Medium
            FROM competitions c, entries e
            WHERE c.ID =  e.Competition_ID
                AND e.ID = %s", $id);
        $result = $this->_rpsdb->get_row($sql);

        if ( $output == OBJECT ) {
            return $result;
        } elseif ( $output == ARRAY_A ) {
            $resultArray = get_object_vars($result);
            return $resultArray;
        } elseif ( $output == ARRAY_N ) {
            $resultArray = array_values(get_object_vars($result));
            return $resultArray;
        } else {
            return $result;
        }
        return $result;
    }

    public function getCompetitionByID2($id, $output = OBJECT)
    {
        $where = $this->_rpsdb->prepare('ID=%d', $id);
        $result = $this->getCompetitions(array('where' => $where));
        return $result[0];
    }

    public function getIdmaxEntries()
    {
        $sql = $this->_rpsdb->prepare("SELECT ID, Max_Entries
            FROM competitions
            WHERE Competition_Date = DATE %s
                AND Classification = %s
                AND Medium = %s", $this->_settings->comp_date, $this->_settings->classification, $this->_settings->medium);
        $_return = $this->_rpsdb->get_row($sql, ARRAY_A);
        return $_return;
    }

    public function checkDuplicateTitle($id, $title)
    {
        $sql = $this->_rpsdb->prepare("SELECT ID
            FROM entries
            WHERE Competition_ID = %s
                AND Member_ID = %s
                AND Title = %s", $id, $this->_user_id, $title);
        $_return = $this->_rpsdb->get_var($sql);
        if ( $_return > 0 ) {
            $_return = true;
        } else {
            $_return = false;
        }
        return $_return;
    }

    public function addEntry($data)
    {
        $data['Member_ID'] = $this->_user_id;
        $data['Date_Created'] = current_time('mysql');
        $_return = $this->_rpsdb->insert('entries', $data);
        return $_return;
    }

    /**
     * Update an entry.
     *
     * If the $data parameter has 'ID' set to a value, then entry will be updated.
     *
     * @param array $data
     * @param bool $wp_error
     *        Optional. Allow return of WP_Error on failure.
     * @return object WP_Error on failure. The post ID on success.
     */
    public function updateEntry($data)
    {
        if ( !empty($data['ID']) ) {
            $entry_ID = (int) $data['ID'];
            $where = array('ID' => $entry_ID);
            if ( !isset($data['Date_Modified']) ) {
                $data['Date_Modified'] = current_time('mysql');
            }
            $data = stripslashes_deep($data);
            if ( false === $this->_rpsdb->update('entries', $data, $where) ) {
                return new WP_Error('db_update_error', 'Could not update entry in the database', $this->_rpsdb->last_error);
            }
        }

        return true;
    }

    public function updateEntriesTitle($new_title, $new_file_name, $id)
    {
        $data = array('Title' => $new_title,'Server_File_Name' => $new_file_name,'Date_Modified' => current_time('mysql'));
        $_where = array('ID' => $id);
        $_return = $this->_rpsdb->update('entries', $data, $_where);
        return $_return;
    }

    public function deleteEntry($id)
    {
        $sql = $this->_rpsdb->prepare("DELETE
            FROM entries
            WHERE ID = %s", $id);
        $_result = $this->_rpsdb->query($sql);

        return $_result;
    }

    public function deleteCompetition($id)
    {
        $this->_rpsdb->delete('competitions', array('ID' => $id));
        return true;
    }

    public function countCompetitions()
    {
        $where = '';

        $count = $this->_rpsdb->get_results("SELECT Closed, COUNT( * ) AS num_competitions FROM competitions GROUP BY Closed", ARRAY_A);

        $total = 0;
        $status = array('N' => 'open','Y' => 'closed');
        $known_types = array_keys($status);
        foreach ( (array) $count as $row ) {
            // Don't count post-trashed toward totals
            $total += $row['num_competitions'];
            if ( in_array($row['Closed'], $known_types) )
                $stats[$status[$row['Closed']]] = (int) $row['num_competitions'];
        }

        $stats['all'] = $total;
        foreach ( $status as $key ) {
            if ( empty($stats[$key]) )
                $stats[$key] = 0;
        }

        $stats = (object) $stats;

        return $stats;
    }

    /**
     *
     * @param field_type $_user_id
     */
    public function setUser_id($_user_id)
    {
        $this->_user_id = $_user_id;
    }
} // End Class AVH_RPS_OldRpsDb
class RPSPDO extends PDO
{
    private $engine;
    private $host;
    private $database;
    private $user;
    private $pass;

    public function __construct()
    {
        $this->engine = 'mysql';
        $this->host = DB_HOST;
        $this->database = RPS_DB_NAME;
        $this->user = RPS_DB_USER;
        $this->pass = RPS_DB_PASSWORD;
        $dns = $this->engine . ':dbname=' . $this->database . ";host=" . $this->host;
        parent::__construct($dns, $this->user, $this->pass);
    }
}