<?php
if ( !defined( 'AVH_FRAMEWORK' ) ) die( 'You are not allowed to call this page directly.' );
class AVH_RPS_OldRpsDb
{
    
    /**
     *
     * @var AVH_RPS_Core
     */
    private $_core;
    /**
     * @var AVH_Settings_Registry
     */
    private $_settings;
    /**
     * @var AVH_Class_registry
     */
    private $_classes;
    private $_rpsdb;

    /**
     * PHP5 constructor
     *
     */
    public function __construct()
    {
        // Get The Registry
        $this->_settings = AVH_RPS_Settings::getInstance();
        $this->_classes = AVH_RPS_Classes::getInstance();
        
        $this->_core = $this->_classes->load_class( 'Core', 'plugin', true );
        $this->_rpsdb = new wpdb( 'rarit0_data', 'rps', 'rarit0_data', 'localhost' );
        $this->_rpsdb->show_errors();
    }

    public function getSeasonList()
    {
        
        $sql = $this->_rpsdb->prepare( 'SELECT DISTINCT if(month(Competition_Date) >= %s and month(Competition_Date) <= 12,
        	concat_WS("-",year(Competition_Date),substr(year(Competition_Date)+1,3,2)),
            concat_WS("-",year(Competition_Date)-1,substr(year(Competition_Date),3,2))) as "Season"
            FROM competitions
            ORDER BY Season',
            $this->_settings->club_season_start_month_num );
        
        $_result = $this->_rpsdb->get_results( $sql, ARRAY_A );
        foreach ($_result as $key => $value){
            $_seasons[$key]=$value['Season'];
        }
        return $_seasons;
    }

    public function getMonthlyScores()
    {
        $sql = $this->_rpsdb->prepare( 'SELECT DISTINCT YEAR(Competition_Date) as "Year",
			MONTH(Competition_Date) as "Month_Num",
			MONTHNAME(Competition_Date) AS "Month",
			Theme
			FROM competitions 
			WHERE Competition_Date >= %s AND
				Competition_date < %s AND
				Scored="Y" ORDER BY Competition_Date',
            $this->_settings->season_start_date, $this->_settings->season_end_date );
        $_return = $this->_rpsdb->get_results( $sql, ARRAY_A );
        return $_return;
    }

    public function getMaxAwards()
    {
        $sql = $this->_rpsdb->prepare( "SELECT MAX(z.Num_Awards) AS Max_Num_Awards FROM
        		(SELECT c.Competition_Date, c.Classification, c.Medium, COUNT(e.Award) AS Num_Awards
        			FROM competitions c, entries e
        				WHERE c.ID = e.Competition_ID AND
        					c.Competition_Date >= %s AND
        					c.Competition_Date < %s AND
        					Scored = 'Y' AND
        					e.Award IS NOT NULL
        				GROUP BY c.Competition_Date, c.Classification, c.Medium) z", $this->_settings->min_date, $this->_settings->max_date );
        $_return = $this->_rpsdb->get_var( $sql );
        return $_return;
    }

    public function getWinners()
    {
        $sql = $this->_rpsdb->prepare( "SELECT c.Competition_Date, c.Classification, 
        		if(c.Classification = 'Beginner',1,
        		if(c.Classification = 'Advanced',2,
        		if(c.Classification = 'Salon',3,0))) as \"Class_Code\",
				c.Medium, e.Title, e.Server_File_Name, e.Award, e.Member_ID
			FROM competitions c, entries e 
				WHERE c.ID = e.Competition_ID and
					c.Competition_Date >= %s AND
					c.Competition_Date < %s AND
					e.Award Is Not Null
				ORDER BY c.Competition_Date, Class_Code, c.Medium, e.Award", $this->_settings->min_date, $this->_settings->max_date );
        $_x = $this->_rpsdb->get_results( $sql, ARRAY_A );
        foreach ($_x as $_rec) {
            $user_info = get_userdata( $_rec['Member_ID']);
            $_rec['FirstName']=$user_info->user_firstname;
            $_rec['LastName']=$user_info->user_lastname;
            $_rec['Username']=$user_info->user_login;
            $_return[]=$_rec;
        }
        
        return $_return;
    }
    
    public function getScoresCurrentUser() {
        $sql = $this->_rpsdb->prepare("SELECT c.Competition_Date, c.Medium, c.Theme, e.Title, e.Server_File_Name,
		e.Score, e.Award
		FROM competitions as c, members as m, entries as e
		WHERE c.ID = e.Competition_ID AND 
		m.ID = e.Member_ID AND
		c.Competition_Date >= %s AND
		c.Competition_Date < %s AND
		e.Member_ID = %s
		ORDER BY c.Competition_Date, c.Medium", $this->_settings->season_start_date, $this->_settings->season_end_date, get_current_user_id());
        $_return = $this->_rpsdb->get_results( $sql, ARRAY_A );
        
        return $_return;
    }
} //End Class AVH_RPS_OldRpsDb