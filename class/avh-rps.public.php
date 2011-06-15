<?php
if ( !defined( 'AVH_FRAMEWORK' ) ) die( 'You are not allowed to call this page directly.' );
class AVH_RPS_Public
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
    
    /**
     * @var AVH_RPS_OldRpsDb
     */
    private $_rpsdb;
    
    private $_core_options;
    
    // Properties of the logged in user
    private $member_id;
    private $username;
    private $first_name;
    private $last_name;
    private $email;
    private $active_user;
    private $digital_admin;
    private $club_officer;
    // Other commonly used globals
    private $digital_chair_email;
    private $errMsg;
    private $url_params;

    /**
     * PHP5 Constructor
     *
     */
    public function __construct()
    {
        // Get The Registry
        $this->_settings = AVH_RPS_Settings::getInstance();
        $this->_classes = AVH_RPS_Classes::getInstance();
        
        // Initialize the plugin
        $this->_core = $this->_classes->load_class( 'Core', 'plugin', true );
        $this->_rpsdb = $this->_classes->load_class( 'OldRpsDb', 'plugin', true );
        $this->_core_options = $this->_core->getOptions();
        
        // Public actions and filters
        add_action( 'template_redirect', array( &$this, 'actionTemplate_Redirect_RPSWindowsClient' ) );
        
        add_shortcode( 'rps_monthly_winners', array( &$this, 'shortcodeRpsMonthlyWinners' ) );
    }

    function actionTemplate_Redirect_RPSWindowsClient()
    {
        if ( array_key_exists( 'rpswinclient', $_REQUEST ) ) {
            
            $this->errMsg = "";
            // Properties of the logged in user
            status_header( 200 );
            switch ( $_REQUEST['rpswinclient'] ) {
                case 'getcompdates':
                    $this->getCompetitionDates();
                    break;
                case 'download':
                    $this->getDownload();
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Enter description here ...
     */
    private function getCompetitionDates()
    {
        // Connect to the Database
        try {
            if ( !$db = @mysql_connect( $this->_settings->host, $this->_settings->uname, $this->_settings->pw ) ) throw new Exception( mysql_error() );
            if ( !mysql_select_db( $this->_settings->dbname ) ) throw new Exception( mysql_error() );
        } catch ( Exception $e ) {
            $this->doRESTError( "Failed to obtain database handle " . $e->getMessage() );
            die( $e->getMessage() );
        }
        try {
            $select = "SELECT DISTINCT(Competition_Date) FROM competitions ";
            $where = "WHERE ";
            if ( $_GET['closed'] || $_GET['scored'] ) {
                if ( $_GET['closed'] ) {
                    $where .= "Closed='" . $_GET['closed'] . "'";
                }
                if ( $_GET['scored'] ) {
                    $where .= " AND Scored='" . $_GET['scored'] . "'";
                }
            } else {
                $where .= "Competition_Date >= CURDATE()";
            }
            if ( !$rs = mysql_query( $select . $where ) ) throw new Exception( mysql_error() );
        } catch ( Exception $e ) {
            $this->doRESTError( "Failed to SELECT list of competitions from database - " . $e->getMessage() );
            die( $e->getMessage() );
        }
        $dom = new DOMDocument( '1.0', 'utf-8' );
        $dom->formatOutput = true;
        $root = $dom->createElement( 'rsp' );
        $dom->appendChild( $root );
        $stat = $dom->createAttribute( "stat" );
        $root->appendChild( $stat );
        $value = $dom->CreateTextNode( "ok" );
        $stat->appendChild( $value );
        while ( $recs = mysql_fetch_assoc( $rs ) ) {
            $dateParts = split( " ", $recs['Competition_Date'] );
            $comp_date = $dom->createElement( 'Competition_Date' );
            $comp_date->appendChild( $dom->createTextNode( $dateParts[0] ) );
            $root->appendChild( $comp_date );
        }
        echo $dom->saveXML();
        die();
    }

    private function getDownload()
    {
        // TODO the RPS Client needs to be changed so the password is send encrypted instead of md5.
        // After this this function needs to be tested.
        $username = $_REQUEST['username'];
        $password = $_REQUEST['password'];
        $this->comp_date = $_REQUEST['comp_date'];
        $this->requested_medium = $_REQUEST['medium'];
        try {
            if ( !$db = @mysql_connect( $this->host, $this->uname, $this->pw ) ) throw new Exception( mysql_error() );
            if ( !mysql_select_db( $this->dbname ) ) throw new Exception( mysql_error() );
        } catch ( Exception $e ) {
            $this->doRESTError( "Failed to obtain database handle " . $e->getMessage() );
            die();
        }
        if ( $db ) {
            $user = wp_authenticate( $username, $password );
            if ( is_wp_error( $user ) ) {
                $a = strip_tags( $user->get_error_message() );
                $this->doRESTError( $a );
                die();
            }
            // @todo Check if the user has the role needed.
            $this->Send_Competitions( $db );
        }
    }

    private function Send_Competitions( $comp_date, $requested_medium, $db )
    {
        // Start building the XML response
        $dom = New DOMDocument( '1.0' );
        // Create the root node
        $rsp = $dom->CreateElement( 'rsp' );
        $rsp = $dom->AppendChild( $rsp );
        $rsp->SetAttribute( 'stat', 'ok' );
        // Get all the competitions that match the requested date
        if ( $this->requested_medium > "" ) {
            $medium_clause = ( $requested_medium == "prints" ) ? " AND Medium like '%Prints' " : " AND Medium like '%Digital' ";
        }
        try {
            $sql = "SELECT ID, Competition_Date, Theme, Medium, Classification 
				FROM competitions 
				WHERE Competition_Date = DATE('$this->comp_date') 
			    $this->medium_clause
				ORDER BY Medium, Classification";
            if ( !$rs = mysql_query( $sql ) ) throw new Exception( mysql_error() );
        } catch ( Exception $e ) {
            $this->doRESTError( "Failed to SELECT competition records with date = " . $this->comp_date . " from database - " . $e->getMessage() );
            die();
        }
        //  Create a Competitions node
        $comps = $rsp->AppendChild( $dom->CreateElement( 'Competitions' ) );
        // Iterate through all the matching Competitions and create corresponding Competition nodes
        while ( $recs = mysql_fetch_assoc( $rs ) ) {
            $comp_id = $recs['ID'];
            $dateParts = split( " ", $recs['Competition_Date'] );
            $date = $dateParts[0];
            $theme = $recs['Theme'];
            $medium = $recs['Medium'];
            $classification = $recs['Classification'];
            // Create the competition node in the XML response
            $comp_node = $comps->AppendChild( $dom->CreateElement( 'Competition' ) );
            $date_node = $comp_node->AppendChild( $dom->CreateElement( 'Date' ) );
            $date_node->AppendChild( $dom->CreateTextNode( $date ) );
            $theme_node = $comp_node->AppendChild( $dom->CreateElement( 'Theme' ) );
            $theme_node->AppendChild( $dom->CreateTextNode( $theme ) );
            $medium_node = $comp_node->AppendChild( $dom->CreateElement( 'Medium' ) );
            $medium_node->AppendChild( $dom->CreateTextNode( $medium ) );
            $class_node = $comp_node->AppendChild( $dom->CreateElement( 'Classification' ) );
            $class_node->AppendChild( $dom->CreateTextNode( $classification ) );
            // Get all the entries for this competition
            try {
                $sql = "SELECT members.FirstName, members.LastName, entries.ID, entries.Title,
						entries.Server_File_Name, entries.Score, entries.Award
						FROM members, entries 
						WHERE entries.Competition_ID = " . $comp_id . " AND 
					      entries.Member_ID = members.ID AND
						  members.Active = 'Y'
						ORDER BY members.LastName, members.FirstName, entries.Title";
                if ( !$rs2 = mysql_query( $sql ) ) throw new Exception( mysql_error() );
            } catch ( Exception $e ) {
                $this->doRESTError( "Failed to SELECT competition entries from database - " . $e->getMessage() );
                die();
            }
            // Create an Entries node
            $entries = $comp_node->AppendChild( $dom->CreateElement( 'Entries' ) );
            // Iterate through all the entries for this competition
            while ( $recs2 = mysql_fetch_assoc( $rs2 ) ) {
                $entry_id = $recs2['ID'];
                $first_name = $recs2['FirstName'];
                $last_name = $recs2['LastName'];
                $title = $recs2['Title'];
                $score = $recs2['Score'];
                $award = $recs2['Award'];
                $server_file_name = $recs2['Server_File_Name'];
                // Create an Entry node
                $entry = $entries->AppendChild( $dom->CreateElement( 'Entry' ) );
                $id = $entry->AppendChild( $dom->CreateElement( 'ID' ) );
                $id->AppendChild( $dom->CreateTextNode( $entry_id ) );
                $fname = $entry->AppendChild( $dom->CreateElement( 'First_Name' ) );
                $fname->AppendChild( $dom->CreateTextNode( $first_name ) );
                $lname = $entry->AppendChild( $dom->CreateElement( 'Last_Name' ) );
                $lname->AppendChild( $dom->CreateTextNode( $last_name ) );
                $title_node = $entry->AppendChild( $dom->CreateElement( 'Title' ) );
                $title_node->AppendChild( $dom->CreateTextNode( $title ) );
                $score_node = $entry->AppendChild( $dom->CreateElement( 'Score' ) );
                $score_node->AppendChild( $dom->CreateTextNode( $score ) );
                $award_node = $entry->AppendChild( $dom->CreateElement( 'Award' ) );
                $award_node->AppendChild( $dom->CreateTextNode( $award ) );
                // Convert the absolute server file name into a URL
                $limit = 1;
                $relative_path = str_replace( array( '\\', '#' ), array( '/', '%23' ), str_replace( $_SERVER['DOCUMENT_ROOT'], '', $recs2['Server_File_Name'], $limit ) );
                $url_node = $entry->AppendChild( $dom->CreateElement( 'Image_URL' ) );
                $url_node->AppendChild( $dom->CreateTextNode( "http://" . $_SERVER['SERVER_NAME'] . $relative_path ) );
            }
        }
        // Send the completed XML response back to the client
        //    	header('Content-Type: text/xml');
        echo $dom->saveXML();
    }

    private function doRESTError( $errMsg )
    {
        echo '<?xml version="1.0" encoding="utf-8" ?>' . "\n";
        echo '<rsp stat="fail">' . "\n";
        echo '	<err msg="' . $errMsg . '" >' . "</err>\n";
        echo "</rsp>\n";
    }

    public function shortcodeRpsMonthlyWinners( $atts, $content = '' )
    {
        $this->_settings->storeSetting( selected_season, '' );
        $this->_settings->storeSetting( season_start_date, "" );
        $this->_settings->storeSetting( season_end_date, "" );
        $this->_settings->storeSetting( season_start_year, "" );
        $this->_settings->storeSetting( selected_year, "" );
        $this->_settings->storeSetting( selected_month, "" );
        $seasons = $this->_rpsdb->getSeasonList();
        if ( $this->_settings->selected_season == "" ) {
            $this->_settings->selected_season = $seasons[count( $seasons ) - 1];
        }
        $this->_settings->season_start_year = substr( $this->_settings->selected_season, 0, 4 );
        $this->_settings->season_start_date = sprintf( "%d-%02s-%02s", $this->_settings->season_start_year, $this->_settings->club_season_start_month_num, 1 );
        $this->_settings->season_end_date = sprintf( "%d-%02s-%02s", $this->_settings->season_start_year + 1, $this->_settings->club_season_start_month_num, 1 );

        $scores = $this->_rpsdb->getMonthlyScores();
        
        foreach ( $scores as $recs ) {
            $key = sprintf( "%d-%02s", $recs['Year'], $recs['Month_Num'] );
            $months[$key] = $recs['Month'];
            $themes[$key] = $recs['Theme'];
        }
        
        if ( $this->_settings->selected_month == "" ) {
            end( $months );
            $this->_settings->selected_year = substr( key( $months ), 0, 4 );
            $this->_settings->selected_month = substr( key( $months ), 5, 2 );
        }
        // Count the maximum number of awards in the selected competitions
        $this->_settings->min_date = sprintf( "%d-%02s-%02s", $this->_settings->selected_year, $this->_settings->selected_month, 1 );
        if ( $this->_settings->selected_month == 12 ) {
            $this->_settings->max_date = sprintf( "%d-%02s-%02s", $this->_settings->selected_year + 1, 1, 1 );
        } else {
            $this->_settings->max_date = sprintf( "%d-%02s-%02s", $this->_settings->selected_year, $this->_settings->selected_month + 1, 1 );
        }
        
        $max_num_awards = $this->_rpsdb->getMaxAwards();
        
        // Start displaying the form
        echo "<center>\n";
        echo "<form name=\"winners_form\" action=\"" . $_SERVER['PHP_SELF'] . "\" method=\"post\">\n";
        echo "<input name=\"submit_control\" type=\"hidden\">\n";
        echo '<input name="selected_season" type="hidden" value="'.$this->_settings->selected_season/'">'."\n";
        echo '<input name="selected_year" type="hidden" value="'.$this->_settings->selected_year.'">'."\n";
        echo '<input name="selected_month" type="hidden" value="'.$this->_settings->selected_month.'">'."\n";
        //echo "<div id=\"errmsg\">$err</div>\n";
        echo "<table class=\"thumb_grid\">\n";
        echo "<tr><td align=\"left\" class=\"form_title\" align=\"center\" colspan=\"" . ( $max_num_awards + 1 ) . "\">Monthly Award Winners for \n";
        //echo "<tr><td class=\"thumb_grid_select\" align=\"center\" colspan=\"" . ($max_num_awards + 1) . "\">\n";
        

        // Drop down list for months
        echo "<SELECT name=\"new_month\" onchange=\"submit_form('new_month')\">\n";
        reset( $months );
        while ( $mth = current( $months ) ) {
            $selected = ( substr( key( $months ), 5, 2 ) == $this->_settings->selected_month ) ? " SELECTED" : "";
            echo "<OPTION value=\"" . key( $this->_settings->months ) . "\"$selected>$mth</OPTION>\n";
            next( $months );
        }
        echo "</SELECT>\n";
        
        // Drop down list for season
        echo "<SELECT name=\"new_season\" onChange=\"submit_form('new_season')\">\n";
        reset( $seasons );
        while ( $season = current( $seasons ) ) {
            $selected = ( $season == $this->_settings->selected_season ) ? " SELECTED" : "";
            echo "<OPTION value=\"$season\"$selected>$season</OPTION>\n";
            next( $seasons );
        }
        echo "</SELECT>&nbsp;</td></tr>\n";
        
        // Display the Theme
        $this_month = sprintf( "%d-%02s", $this->_settings->selected_year, $this->_settings->selected_month );
        echo "<tr><th class='thumb_grid_title'  align='center' colspan='" . ( $max_num_awards + 1 ) . "'>Theme is $themes[$this_month]</th></tr>";
        
        // Output the column headings
        echo "<tr><th class='thumb_col_header' align='center'>Competition</th>\n";
        for ( $i = 0; $i < $max_num_awards; $i++ ) {
            switch ( $i ) {
                case 0:
                    $award_title = "1st";
                    break;
                case 1:
                    $award_title = "2nd";
                    break;
                case 2:
                    $award_title = "3rd";
                    break;
                default:
                    $award_title = "HM";
            }
            echo "<th class=\"thumb_col_header\" align=\"center\">$award_title</th>\n";
        }
        $award_winners = $this->_rpsdb->getWinners();
        // Iterate through all the award winners and display each thumbnail in a grid
        $row = 0;
        $column = 0;
        $comp = "";
        foreach ( $award_winners as $recs ) {
            
            // Remember the important values from the previous record
            $prev_comp = $comp;
            
            // Grab a new record from the database
            $dateParts = split( " ", $recs['Competition_Date'] );
            $comp_date = $dateParts[0];
            $medium = $recs['Medium'];
            $classification = $recs['Classification'];
            $comp = "$classification<br>$medium";
            $title = $recs['Title'];
            $last_name = $recs['LastName'];
            $first_name = $recs['FirstName'];
            $award = $recs['Award'];
            
            // If we're at the end of a row, finish off the row and get ready for the next one
            if ( $prev_comp != $comp ) {
                // As necessary, pad the row out with empty cells
                if ( $row > 0 && $column < $max_num_awards ) {
                    for ( $i = $column; $i < $max_num_awards; $i++ ) {
                        echo "<td align=\"center\" class=\"thumb_cell\">";
                        echo "<div class=\"thumb_canvas\"></div></td>\n";
                    }
                }
                // Terminate this row
                echo "</tr>\n";
                
                // Initialize the new row
                $row += 1;
                $column = 0;
                echo "<tr><td class=\"comp_cell\" align=\"center\">$comp</td>\n";
            }
            // Display this thumbnail in the the next available column
            $this->_core->rpsCreateThumbnail( $recs, 75 );
            $this->_core->rpsCreateThumbnail( $recs, 400 );
            echo "<td align=\"center\" class=\"thumb_cell\">\n";
            echo "  <div class=\"thumb_canvas\">\n";
            echo "    <a href=\"" . $this->_core->rpsGetThumbnailUrl( $recs, 400 ) . "\" rel=\"lightbox[$classification $medium]\" title=\"($award) $title\">\n";
            echo "    <img class=\"thumb_img\" src=\"" . $this->_core->rpsGetThumbnailUrl( $recs, 75 ) . "\" /></a>\n";
            echo "  </div>\n</td>\n";
            $prev_comp = $comp;
            $column += 1;
        }
        // As necessary, pad the last row out with empty cells
        if ( $row > 0 && $column < $max_num_awards ) {
            for ( $i = $column; $i < $max_num_awards; $i++ ) {
                echo "<td align=\"center\" class=\"thumb_cell\">";
                echo "<div class=\"thumb_canvas\"></div></td>\n";
            }
        }
        // Close out the table
        echo "</tr>\n</table>\n";
        echo "</form>\n";
        echo "<br />\n";
    }
}