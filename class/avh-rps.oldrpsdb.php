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
        
        $sql = $this->_rpsdb->prepare( 'SELECT DISTINCT if(month(Competition_Date) >= %s and month(Competition_Date) <= %s,
        	concat_WS("-",year(Competition_Date),substr(year(Competition_Date)+1,3,2)),
            concat_WS("-",year(Competition_Date)-1,substr(year(Competition_Date),3,2))) as "Season"
            FROM competitions
            ORDER BY Season', $this->_settings->club_season_start_month_num, $this->_settings->club_season_end_month_num );
        
        $_result = $this->_rpsdb->get_results( $sql, ARRAY_A );
        foreach ( $_result as $key => $value ) {
            $_seasons[$key] = $value['Season'];
        }
        return $_seasons;
    }

    public function getSeasonListOneEntry()
    {
        $sql = $this->_rpsdb->prepare( 'SELECT if(month(c.Competition_Date) >= %s and month(c.Competition_Date) <= %s, 
			concat_WS(" - ",year(c.Competition_Date),substr(year(c.Competition_Date)+1,3,2)),
			concat_WS(" - ",year(c.Competition_Date)-1,substr(year(c.Competition_Date),3,2))) as "Season",
			count(e.ID)
			FROM competitions c, entries e
			WHERE c.ID = e.Competition_ID
			GROUP BY Season
			HAVING count(e.ID) > 0
			ORDER BY Season', $this->_settings->club_season_start_month_num, $this->_settings->club_season_end_month_num );
        
        $_result = $this->_rpsdb->get_results( $sql, ARRAY_A );
        foreach ( $_result as $key => $value ) {
            $_seasons[$key] = $value['Season'];
        }
        return $_seasons;
    
    }

    public function getClubCompetitionDates()
    {
        $sql = $this->_rpsdb->prepare( 'SELECT Competition_Date, max(Max_Entries) as Max_Entries,
			max(Num_Judges) as Num_Judges
			FROM competitions
			WHERE Competition_Date >= %s AND
				Competition_Date < %s AND
				Special_Event = "N"
			GROUP BY Competition_Date
			ORDER BY Competition_Date', $this->_settings->season_start_date, $this->_settings->season_end_date );
        $_return = $this->_rpsdb->get_results( $sql, ARRAY_A );
        return $_return;
    }

    public function getClubCompetitionResults()
    {
        $sql = $this->_rpsdb->prepare( 'SELECT c.Competition_Date, c.Medium, c.Classification, c.Special_Event,
			if(c.Classification = "Beginner",0,
			if(c.Classification = "Advanced",1,2)) as "Class_Code",
			e.Score, e.Award, e.Member_ID
			FROM competitions as c, entries as e
			WHERE c.ID = e.Competition_ID AND 
				Competition_Date >= %s AND
				Competition_Date < %s AND
				Special_Event = "N"
			ORDER BY c.Medium DESC, Class_Code, c.Competition_Date', $this->_settings->season_start_date, $this->_settings->season_end_date );
        
        $_x = $this->_rpsdb->get_results( $sql, ARRAY_A );
        foreach ( $_x as $key => $_rec ) {
            $user_info = get_userdata( $_rec['Member_ID'] );
            $_rec['FirstName'] = $user_info->user_firstname;
            $_rec['LastName'] = $user_info->user_lastname;
            $_rec['Username'] = $user_info->user_login;
            $_return[] = $_rec;
        }
        return $_return;
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
				Scored="Y" ORDER BY Competition_Date', $this->_settings->season_start_date, $this->_settings->season_end_date );
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
        foreach ( $_x as $_rec ) {
            $user_info = get_userdata( $_rec['Member_ID'] );
            $_rec['FirstName'] = $user_info->user_firstname;
            $_rec['LastName'] = $user_info->user_lastname;
            $_rec['Username'] = $user_info->user_login;
            $_return[] = $_rec;
        }
        
        return $_return;
    }

    public function getScoresCurrentUser()
    {
        $sql = $this->_rpsdb->prepare( "SELECT c.Competition_Date, c.Medium, c.Theme, e.Title, e.Server_File_Name,
		e.Score, e.Award
		FROM competitions as c, members as m, entries as e
		WHERE c.ID = e.Competition_ID AND 
		m.ID = e.Member_ID AND
		c.Competition_Date >= %s AND
		c.Competition_Date < %s AND
		e.Member_ID = %s
		ORDER BY c.Competition_Date, c.Medium", $this->_settings->season_start_date, $this->_settings->season_end_date, get_current_user_id() );
        $_return = $this->_rpsdb->get_results( $sql, ARRAY_A );
        
        return $_return;
    }

    public function getOpenCompetitions( $subset )
    {
        
        // Select the list of open competitions that match this member's classification(s)
        if ( $subset ) {
            $and_medium_subset = " AND c.Medium like %s";
        } else {
            $and_medium_subset = '';
        }
        // Select the list of open competitions that match this member's classification(s)
        $_sql = "SELECT c.Competition_Date, c.Classification, c.Medium, c.Theme, c.Closed
			FROM competitions c, member_classifications mc 
			WHERE c.Classification = mc.Classification and
			      c.Medium = mc.Medium and
				  mc.Member_ID = %s and
				  c.Closed = 'N'";
        $_sql .= $and_medium_subset;
        $_sql .= " ORDER BY c.Competition_Date, c.Medium";
        $user_id = get_current_user_id();
        $sql = $this->_rpsdb->prepare( $_sql, $user_id, '%' . $subset . '%' );
        $_return = $this->_rpsdb->get_results( $sql, ARRAY_A );
        return $_return;
    
    }

    public function getCompetitionCloseDate()
    {
        $sql = $this->_rpsdb->prepare( "SELECT Close_Date 
        	FROM competitions 
        	WHERE Competition_Date = DATE(%s) 
        		AND Classification = %s 
        		AND Medium = %s", $this->_settings->comp_date, $this->_settings->classification, $this->_settings->medium );
        $_return = $this->_rpsdb->get_var( $sql );
        return $_return;
    }

    public function getCompetionClosed()
    {
        $sql = $this->_rpsdb->prepare( "SELECT Closed 
            FROM competitions 
            WHERE Competition_Date = DATE(%s) 
                AND Classification = %s 
                AND Medium = %s", $this->_settings->comp_date, $this->_settings->classification, $this->_settings->medium );
        $_closed = $this->_rpsdb->get_var( $sql );
        if ( $_closed == "Y" ) {
            $_return = true;
        } else {
            $return = false;
        }
        return $_return;
    }

    public function getCompetitionMaxEntries()
    {
        $sql = $this->_rpsdb->prepare( "SELECT Max_Entries FROM competitions
				WHERE Competition_Date = DATE %s AND 
				Classification = %s AND
				Medium = %s", $this->_settings->comp_date, $this->_settings->classification, $this->_settings->medium );
        $_return = $this->_rpsdb->get_var( $sql );
        return $_return;
    }

    public function getCompetitionEntriesUser()
    {
        $sql = $this->_rpsdb->prepare( "SELECT COUNT(entries.ID) as Total_Submitted
			FROM competitions, entries
			WHERE competitions.ID = entries.Competition_ID
				AND	entries.Member_ID=%s 
				AND competitions.Competition_Date = DATE %s ", get_current_user_id(), $this->_settings->comp_date );
        $_return = $this->_rpsdb->get_var( $sql );
        return $_return;
    }

    public function getCompetitionSubmittedEntriesUser()
    {
        $sql = $this->_rpsdb->prepare( "SELECT entries.ID, entries.Title, entries.Client_File_Name, entries.Server_File_Name, competitions.Max_Entries  
			FROM competitions, entries
			WHERE competitions.ID = entries.Competition_ID
				AND entries.Member_ID = %s
				AND competitions.Competition_Date = DATE %s
				AND competitions.Classification = %s
				AND competitions.Medium = %s", get_current_user_id(), $this->_settings->comp_date, $this->_settings->classification, $this->_settings->medium );
        $_return = $this->_rpsdb->get_results( $sql, ARRAY_A );
        return $_return;
    }

    public function getEntryInfo( $id )
    {
        $sql = $this->_rpsdb->prepare( "SELECT * 
        	FROM entries 
        	WHERE ID = %s", $id );
        $_return = $this->_rpsdb->get_row( $sql, ARRAY_A );
        return $_return;
    
    }

    public function getCompetitionByID( $id )
    {
        $sql = $this->_rpsdb->prepare( "SELECT Competition_Date, Classification, Medium 
        	FROM competitions c, entries e
        	WHERE c.ID =  e.Competition_ID
        		AND e.ID = %s", $id );
        $_return = $this->_rpsdb->get_row( $sql, ARRAY_A );
        return $_return;
    
    }

    public function updateEntriesTitle( $new_title, $new_file_name, $id )
    {
        
        $data = array( 'Title'=>$new_title, 'Server_File_Name'=>$new_file_name, 'Date_Modified'=>current_time( 'mysql' ) );
        $_where = array( 'ID'=>$id );
        $_return = $this->_rpsdb->update( 'entries', $data, $_where );
        return $_return;
    
    }
} //End Class AVH_RPS_OldRpsDb