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
        add_shortcode( 'rps_scores_current_user', array( &$this, 'shortcodeRpsScoresCurrentUser' ) );
        add_shortcode( 'rps_all_scores', array( &$this, 'shortcodeRpsAllScores' ) );
    
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
        
        if ( isset( $_POST['submit_control'] ) ) {
            $this->_settings->selected_season = esc_attr( $_POST['selected_season'] );
            $this->_settings->season_start_year = substr( $this->_settings->selected_season, 0, 4 );
            $this->_settings->selected_year = esc_attr( $_POST['selected_year'] );
            $this->_settings->selected_month = esc_attr( $_POST['selected_month'] );
            
            switch ( $_POST['submit_control'] ) {
                case 'new_season':
                    $this->_settings->selected_season = esc_attr( $_POST['new_season'] );
                    $this->_settings->season_start_year = substr( $this->_settings->selected_season, 0, 4 );
                    $this->_settings->selected_month = "";
                    break;
                case 'new_month':
                    $this->_settings->selected_year = substr( esc_attr( $_POST['new_month'] ), 0, 4 );
                    $this->_settings->selected_month = substr( esc_attr( $_POST['new_month'] ), 5, 2 );
            }
        }
        $seasons = $this->_rpsdb->getSeasonList();
        if ( empty( $this->_settings->selected_season ) ) {
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
        
        if ( empty( $this->_settings->selected_month ) ) {
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
        echo '<script type="text/javascript">';
        echo 'function submit_form(control_name) {' . "\n";
        echo '	document.winners_form.submit_control.value = control_name;' . "\n";
        echo '	document.winners_form.submit();' . "\n";
        echo '}' . "\n";
        echo '</script>';
        
        echo '<span class="competion-monthly-winners-form"> Monthly Award Winners for ';
        $action = site_url( '/' . get_page_uri() );
        $form = '';
        $form .= '<form name="winners_form" action="' . $action . '" method="post">' . "\n";
        $form .= '<input name="submit_control" type="hidden">' . "\n";
        $form .= '<input name="selected_season" type="hidden" value="' . $this->_settings->selected_season . '">' . "\n";
        $form .= '<input name="selected_year" type="hidden" value="' . $this->_settings->selected_year . '">' . "\n";
        $form .= '<input name="selected_month" type="hidden" value="' . $this->_settings->selected_month . '">' . "\n";
        
        // Drop down list for months
        $form .= '<select name="new_month" onchange="submit_form(\'new_month\')">' . "\n";
        foreach ( $months as $key => $month ) {
            $selected = ( substr( $key, 5, 2 ) == $this->_settings->selected_month ) ? " selected" : "";
            $form .= '<option value="' . $key . '"' . $selected . '>' . $month . '</option>' . "\n";
        }
        $form .= "</select>\n";
        
        // Drop down list for season
        $form .= '<select name="new_season" onChange="submit_form(\'new_season\')">' . "\n";
        foreach ( $seasons as $season ) {
            $selected = ( $season == $this->_settings->selected_season ) ? " selected" : "";
            $form .= '<option value="' . $season . '"' . $selected . '>' . $season . '</option>' . "\n";
        }
        $form .= '</select>' . "\n";
        $form .= '</form>';
        echo $form;
        unset( $form );
        echo '</span>';
        
        $this_month = sprintf( "%d-%02s", $this->_settings->selected_year, $this->_settings->selected_month );
        echo '<h4 class="competition-theme">Theme is ' . $themes[$this_month] . '</h4>';
        
        echo "<table class=\"thumb_grid\">\n";
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

    public function shortcodeRpsScoresCurrentUser( $atts, $content = '' )
    {
        
        if ( isset( $_POST['selected_season_list'] ) ) {
            $this->_settings->selected_season = $_POST['selected_season_list'];
        }
        // Get the list of seasons
        $seasons = $this->_rpsdb->getSeasonList();
        if ( empty( $this->_settings->selected_season ) ) {
            $this->_settings->selected_season = $seasons[count( $seasons ) - 1];
        }
        $this->_settings->season_start_year = substr( $this->_settings->selected_season, 0, 4 );
        $this->_settings->season_start_date = sprintf( "%d-%02s-%02s", $this->_settings->season_start_year, $this->_settings->club_season_start_month_num, 1 );
        $this->_settings->season_end_date = sprintf( "%d-%02s-%02s", $this->_settings->season_start_year + 1, $this->_settings->club_season_start_month_num, 1 );
        
        // Start building the form
        $action = site_url( '/' . get_page_uri() );
        $form = '';
        $form .= '<form name="my_scores_form" method="post" action="' . $action . '">';
        $form .= '<input type="hidden" name="selected_season" value="' . $this->_settings->selected_season . '" />';
        $form .= "&nbsp;<select name=\"selected_season_list\" onchange=\"submit_form()\">\n";
        foreach ( $seasons as $this_season ) {
            if ( $this_season == $this->_settings->selected_season ) {
                $selected = " SELECTED";
            } else {
                $selected = "";
            }
            $form .= "<option value=\"$this_season\"$selected>$this_season</option>\n";
        }
        $form .= "</select>&nbsp;season\n";
        $form .= "</form>";
        echo '<script type="text/javascript">' . "\n";
        echo 'function submit_form() {' . "\n";
        echo '	document.my_scores_form.submit();' . "\n";
        echo '}' . "\n";
        echo '</script>' . "\n";
        echo "My scores for ";
        echo $form;
        echo '<table class="form_frame" width="99%">';
        echo '<tr>';
        echo '<th class="form_frame_header" width="12%">Date</th>';
        echo '<th class="form_frame_header">Theme</th>';
        echo '<th class="form_frame_header">Competition</th>';
        echo '<th class="form_frame_header">Title</th>';
        echo '<th class="form_frame_header" width="8%">Score</th>';
        echo '<th class="form_frame_header" width="8%">Award</th></tr>';
        $scores = $this->_rpsdb->getScoresCurrentUser();
        
        // Bail out if not entries found
        if ( empty( $scores ) ) {
            echo "<tr><td colspan=\"6\">No entries submitted</td></tr>\n";
            echo "</table>\n";
        } else {
            
            // Build the list of submitted images
            $compCount = 0;
            $prev_date = "";
            $prev_medium = "";
            foreach ( $scores as $recs ) {
                $dateParts = split( " ", $recs['Competition_Date'] );
                $dateParts[0] = strftime( '%d-%b-%Y', strtotime( $dateParts[0] ) );
                $comp_date = $dateParts[0];
                $medium = $recs['Medium'];
                $theme = $recs['Theme'];
                $title = $recs['Title'];
                $score = $recs['Score'];
                $award = $recs['Award'];
                if ( $dateParts[0] != $prev_date ) {
                    $compCount += 1;
                    $rowStyle = $compCount % 2 == 1 ? "odd_row" : "even_row";
                    $prev_medium = "";
                }
                
                $a = realpath( $recs['Server_File_Name'] );
                $image_url = site_url( str_replace( '/home/rarit0/public_html', '', $recs['Server_File_Name'] ) );
                
                if ( $prev_date == $dateParts[0] ) {
                    $dateParts[0] = "";
                    $theme = "";
                } else {
                    $prev_date = $dateParts[0];
                }
                if ( $prev_medium == $medium ) {
                    // $medium = "";
                    $theme = "";
                } else {
                    $prev_medium = $medium;
                }
                
                echo "<tr>";
                echo "<td align=\"left\" valign=\"top\" class=\"$rowStyle\" width=\"12%\">" . $dateParts[0] . "</td>\n";
                echo "<td align=\"left\" valign=\"top\" class=\"$rowStyle\">$theme</td>\n";
                echo "<td align=\"left\" valign=\"top\" class=\"$rowStyle\">$medium</td>\n";
                //echo "<td align=\"left\" valign=\"top\" class=\"$rowStyle\"><a href=\"$image_url\" target=\"_blank\">$title</a></td>\n";
                $score_award = "";
                if ( $score > "" ) {
                    $score_award = " / {$score}pts";
                }
                if ( $award > "" ) {
                    $score_award .= " / $award";
                }
                echo "<td align=\"left\" valign=\"top\" class=\"$rowStyle\"><a href=\"$image_url\" rel=\"lightbox[{$comp_date}]\" title=\"" . htmlentities( $title ) . "<br />$comp_date / $medium{$score_award}\">" . htmlentities( $title ) . "</a></td>\n";
                echo "<td class=\"$rowStyle\" valign=\"top\" align=\"center\" width=\"8%\">$score</td>\n";
                echo "<td class=\"$rowStyle\" valign=\"top\" align=\"center\" width=\"8%\">$award</td></tr>\n";
            
            }
            echo "</table>";
        }
    }

    public function shortcodeRpsAllScores( $atts, $content = '' )
    {
        
        if ( isset( $_POST['selected_season_list'] ) ) {
            $this->_settings->selected_season = $_POST['selected_season_list'];
        }
        $award_map = array( '1st'=>'1', '2nd'=>'2', '3rd'=>'3', 'HM'=>'H' );
        
        $seasons = $this->_rpsdb->getSeasonListOneEntry();
        arsort($seasons);
        if ( !isset( $this->_settings->selected_season ) ) {
            $this->_settings->selected_season = $seasons[count( $seasons ) - 1];
        }
        
        $this->_settings->season_start_year = substr( $this->_settings->selected_season, 0, 4 );
        $this->_settings->season_start_date = sprintf( "%d-%02s-%02s", $this->_settings->season_start_year, 9, 1 );
        $this->_settings->season_end_date = sprintf( "%d-%02s-%02s", $this->_settings->season_start_year + 1, 9, 1 );
        
        $competition_dates = $this->_rpsdb->getClubCompetitionDates();
        // Build an array of competition dates in "MM/DD" format for column titles.
        // Also remember the max entries per member for each competition and the number
        // of judges for each competition.
        $total_max_entries = 0;
        foreach ( $competition_dates as $key => $recs ) {
            $comp_date = $recs['Competition_Date'];
            $date_parts = explode( " ", $comp_date );
            list ($comp_year, $comp_month, $comp_day) = explode( "-", $date_parts[0] );
            $comp_dates[$date_parts[0]] = sprintf( "%d/%d", $comp_month, $comp_day );
            $comp_max_entries[$date_parts[0]] = $recs['Max_Entries'];
            $total_max_entries += $recs['Max_Entries'];
            $comp_num_judges[$date_parts[0]] = $recs['Num_Judges'];
        }
        
        $club_competition_results = $this->_rpsdb->getClubCompetitionResults();
        $this->_core->rps_array_sort( $club_competition_results, '!Medium', 'Class_Code', 'LastName', 'FirstName', 'Competition_Date' );
        
        // Bail out if no entries found
        if ( empty( $club_competition_results ) ) {
            echo 'No entries submitted';
        } else {
            
            // Start the big table
            

            $action = site_url( '/' . get_page_uri() );
            $form = '';
            $form .= '<form name="all_scores_form" method="post" action="' . $action . '">';
            $form .= '<input type="hidden" name="selected_season" value="' . $this->_settings->selected_season . '"/>';
            $form .= "&nbsp;<select name=\"selected_season_list\" onchange=\"submit_form()\">\n";
            foreach ( $seasons as $this_season ) {
                if ( $this_season == $this->_settings->selected_season ) {
                    $selected = " SELECTED";
                } else {
                    $selected = "";
                }
                $form .= "<option value=\"$this_season\"$selected>$this_season</option>\n";
            }
            $form .= "</select>";
            $form .= '</form>';
            echo 'Select the season: ';
            echo $form;
            echo '<script type="text/javascript">' . "\n";
            echo 'function submit_form() {' . "\n";
            echo '	document.all_scores_form.submit();' . "\n";
            echo '}' . "\n";
            echo '</script>' . "\n";
            echo "<table class=\"form_frame\" width=\"99%\">\n";
            
            // Build the list of submitted images
            $prev_member = "";
            $prev_medium = "";
            $prev_class = "";
            $rowCount = 0;
            // Initialize the 2D array to hold the members scores for each month
            // Each row represents a competition month and each column holds the scores
            // of the submitted images for that month
            $member_scores = array();
            foreach ( $comp_dates as $key => $d ) {
                $member_scores[$key] = array();
            }
            $total_score = 0;
            $num_scores = 0;
            
            $medium = '';
            $classification = '';
            $member = '';
            $last_name = '';
            $first_name = '';
            
            foreach ( $club_competition_results as $key => $recs ) {
                
                // Remember the important values from the previous record
                $prev_medium = $medium;
                $prev_class = $classification;
                $prev_member = $member;
                $prev_lname = $last_name;
                $prev_fname = $first_name;
                
                // Grab a new record from the database
                $medium = $recs['Medium'];
                $classification = $recs['Classification'];
                $date_parts = explode( " ", $recs['Competition_Date'] );
                $this_date = $date_parts[0];
                $member = $recs['Username'];
                $last_name = $recs['LastName'];
                $first_name = $recs['FirstName'];
                $score = $recs['Score'];
                $award = $recs['Award'];
                $special_event = $recs['Special_Event'];
                
                // Is this the beginning of the next member's scores?
                if ( $member != $prev_member || $classification != $prev_class || $medium != $prev_medium ) {
                    $rowCount += 1;
                    $rowStyle = $rowCount % 2 == 1 ? "odd_row" : "even_row";
                    
                    // Don't do anything yet if this is the very first member, otherwise, output all
                    // the accumulated scored for the member we just passed.
                    if ( $prev_member != "" ) {
                        // Display the members name and classification
                        echo "<tr>";
                        echo "<td align=\"left\" class=\"$rowStyle\">" . $prev_fname . " " . $prev_lname . "</td>\n";
                        echo "<td align=\"center\" class=\"$rowStyle\">" . substr( $prev_class, 0, 1 ) . "</td>\n";
                        
                        // Iterate through all the accumulated scores for this member
                        foreach ( $member_scores as $key => $score_array ) {
                            // Print the scores for the submitted entries for this month
                            for ( $i = 0; $i < count( $score_array ); $i++ ) {
                                echo "<td align=\"center\" class=\"$rowStyle\">$score_array[$i]</td>\n";
                            }
                            // Pad the unused entries for this member for this month
                            for ( $i = 0; $i < $comp_max_entries[$key] - count( $score_array ); $i++ ) {
                                echo "<td align=\"center\" class=\"$rowStyle\">&nbsp;</td>\n";
                            }
                        }
                        
                        // Display the members annual average score
                        if ( $total_score > 0 && $num_scores > 0 ) {
                            echo "<td align=\"center\" class=\"$rowStyle\">" . sprintf( "%3.1f", $total_score / $num_scores ) . "</td>\n";
                        } else {
                            echo "<td align=\"center\" class=\"$rowStyle\">&nbsp;</td>\n";
                        }
                        echo "</tr>";
                    
                    }
                    
                    // Now that we've just output the scores for the previous member, are we at the
                    // beginning of a new classification, but not at the end of the current medium?
                    // If so, draw a horizonal line to mark the beginning of a new classification
                    if ( $classification != $prev_class && $medium == $prev_medium ) {
                        //echo "<tr class=\"horizontal_separator\">";
                        echo "<tr>";
                        echo "<td colspan=\"" . ( $total_max_entries + 3 ) . "\" class=\"horizontal_separator\"></td>";
                        echo "</tr>\n";
                        $prev_class = $classification;
                    }
                    
                    // Are we at the beginning of a new medium?
                    // If so, output a new set of column headings
                    if ( $medium != $prev_medium ) {
                        // Draw a horizontal line to end the previous medium
                        if ( $prev_medium != "" ) {
                            echo "<tr class=\"horizontal_separator\">";
                            //echo "<td colspan=\"" . (count($comp_dates) * 2 + 3) . 
                            //	"\" class=\"horizontal_separator\"></td>";
                            echo "<td colspan=\"" . ( $total_max_entries + 3 ) . "\" class=\"horizontal_separator\"></td>";
                            echo "</tr>\n";
                        }
                        
                        // Display the category title
                        echo '<tr><td align="left" class="form_title" colspan="' . ( $total_max_entries + 3 ) . '">';
                        echo $medium.' scores for '.$this->_settings->selected_season .' season';
                        echo '</td></tr>'."\n";
                        
                        // Display the first row column headers
                        echo "<tr>\n<th class=\"form_frame_header\" colspan=\"2\">&nbsp;</th>\n";
                        foreach ( $comp_dates as $key => $d ) {
                            echo "<th class=\"form_frame_header\" colspan=\"" . $comp_max_entries[$key] . "\">$d</th>\n";
                        }
                        echo "<th class=\"form_frame_header\">&nbsp;</th>\n";
                        echo "</tr>\n";
                        // Display the second row column headers
                        echo "<tr>\n";
                        echo "<th class=\"form_frame_header\">Member</th>\n";
                        echo "<th class=\"form_frame_header\">Cl.</th>\n";
                        foreach ( $comp_dates as $key => $d ) {
                            for ( $i = 1; $i <= $comp_max_entries[$key]; $i++ ) {
                                echo "<th class=\"form_frame_header\">$i</th>\n";
                            }
                        }
                        echo "<th class=\"form_frame_header\">Avg</th>\n";
                        echo "</tr>\n";
                    }
                    
                    // Reset the score array to be ready to start accumulating the scores for this 
                    // new member we just started.
                    $member_scores = array();
                    foreach ( $comp_dates as $key => $d ) {
                        $member_scores[$key] = array();
                    }
                    $total_score = 0;
                    $num_scores = 0;
                }
                
                // We're still working on the records for the current member
                // Accumulate this member's total score to calculcate the average at the end.
                if ( $score > 0 ) {
                    $score = $score / $comp_num_judges[$this_date];
                    if ( $score - floor( $score ) > 0 ) {
                        $score = round( $score, 1 );
                    }
                    if ( $special_event == 'N' ) {
                        $total_score += $score;
                        $num_scores += 1;
                    }
                }
                // Apply the award as a superscript to the score
                if ( $award != "" ) {
                    $score = "&nbsp;&nbsp;" . $score . "<SUP>&nbsp;$award_map[$award]</SUP>";
                }
                // Store the score in the appropriate array
                $member_scores[$this_date][] = $score;
            }
            
            // Output the last remaining row of the table that hasn't been displayed yet
            $rowCount += 1;
            $rowStyle = $rowCount % 2 == 1 ? "odd_row" : "even_row";
            // Display the members name and classification
            echo "<tr>";
            echo "<td align=\"left\" class=\"$rowStyle\">" . $first_name . " " . $last_name . "</td>\n";
            echo "<td align=\"center\" class=\"$rowStyle\">" . substr( $classification, 0, 1 ) . "</td>\n";
            // Iterate through all the accumulated scores for this member
            foreach ( $member_scores as $key => $score_array ) {
                // Print the scores for the submitted entries for this month
                for ( $i = 0; $i < count( $score_array ); $i++ ) {
                    echo "<td align=\"center\" class=\"$rowStyle\">$score_array[$i]</td>\n";
                }
                // Pad the unused entries for this member for this month
                for ( $i = 0; $i < $comp_max_entries[$key] - count( $score_array ); $i++ ) {
                    echo "<td align=\"center\" class=\"$rowStyle\">&nbsp;</td>\n";
                }
            }
            
            // Display the members annual average score
            if ( $total_score > 0 && $num_scores > 0 ) {
                echo "<td align=\"center\" class=\"$rowStyle\">" . sprintf( "%3.1f", $total_score / $num_scores ) . "</td>\n";
            } else {
                echo "<td align=\"center\" class=\"$rowStyle\">&nbsp;</td>\n";
            }
            echo "</tr>";
            
            // We're all done
            echo "</table>";
        }
    }
}