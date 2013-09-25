<?php
namespace Rps\Frontend;
use Rps\Settings;
use Rps\Db\RpsDb;
use Rps\Db\RPSPDO;
use Rps\Common\Core;
use DI\Container;
use PDO;
use DOMDocument;

class Frontend
{

    /**
     *
     * @var Container
     */
    private $container;

    /**
     *
     * @var Core
     */
    private $_core;

    /**
     *
     * @var Settings
     */
    private $_settings;

    /**
     *
     * @var RpsDb
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
    private $_errmsg;
    private $url_params;

    /**
     * PHP5 Constructor
     */
    public function __construct ($container)
    {
        // Get The Registry
        $this->container = $container;
        $this->_settings = $this->container->get('Rps\\Settings');

        $this->_errmsg = '';
        // Initialize the plugin
        $this->_core = $this->container->get('Rps\\Common\\Core');
        $this->_rpsdb = $this->container->get('Rps\\Db\\RpsDb');
        $this->_core_options = $this->_core->getOptions();

        $this->_rpsdb->setCompetitionClose();

        add_action('wp_loaded', array($this,'actionInit_InitRunTime'));
        // Public actions and filters
        add_action('template_redirect', array($this,'actionTemplate_Redirect_RPSWindowsClient'));

        add_shortcode('rps_monthly_winners', array($this,'shortcodeRpsMonthlyWinners'));
        add_shortcode('rps_scores_current_user', array($this,'shortcodeRpsScoresCurrentUser'));
        add_shortcode('rps_all_scores', array($this,'shortcodeRpsAllScores'));

        // add_action('pre-header-my-print-entries', array($this,'actionPreHeader_RpsMyEntries'));
        // add_action('pre-header-my-digital-entries', array($this,'actionPreHeader_RpsMyEntries'));
        // add_action('wp', array($this, 'actionPreHeader_RpsMyEntries'));
        add_action('wp', array($this,'actionPreHeader_RpsMyEntries'));

        add_shortcode('rps_my_entries', array($this,'shortcodeRpsMyEntries'));

        add_action('wp', array($this,'actionPreHeader_RpsEditTitle'));
        add_shortcode('rps_edit_title', array($this,'shortcodeRpsEditTitle'));

        add_action('wp', array($this,'actionPreHeader_RpsUploadEntry'));
        add_shortcode('rps_upload_image', array($this,'shortcodeRpsUploadEntry'));

        add_action("after_setup_theme", array($this,'actionAfterThemeSetup'), 14);
    }

    public function actionAfterThemeSetup ()
    {
        add_action('rps_showcase', array($this,'actionShowcase_competition_thumbnails'));
    }

    public function actionInit_InitRunTime ()
    {
        $this->_rpsdb->setUser_id(get_current_user_id());
    }

    function actionTemplate_Redirect_RPSWindowsClient ()
    {
        if ( array_key_exists('rpswinclient', $_REQUEST) ) {

            define('DONOTCACHEPAGE', true);
            global $hyper_cache_stop;
            $hyper_cache_stop = true;
            add_filter('w3tc_can_print_comment', '__return_false' );

            // Properties of the logged in user
            status_header(200);
            switch ( $_REQUEST['rpswinclient'] )
            {
                case 'getcompdate':
                    $this->_sendXmlCompetitionDates();
                    break;
                case 'download':
                    $this->_sendCompetitions();
                    break;
                case 'uploadscore':
                    $this->_doUploadScore();
                default:
                    break;
            }
        }
    }

    public function actionShowcase_competition_thumbnails ($ctr)
    {
        if ( is_front_page() ) {
            $image = array();
            $seasons = $this->_rpsdb->getSeasonList();
            $from_season = $seasons[count($seasons) - 3];

            $season_start_year = substr($from_season, 0, 4);
            $season = sprintf("%d-%02s-%02s", $season_start_year, $this->_settings->club_season_start_month_num, 1);

            echo '<div class="rps-sc-tile suf-tile-1c entry-content bottom">';

            echo '<div class="suf-gradient suf-tile-topmost">';
            echo '<h3>Showcase</h3>';
            echo '</div>';

            echo '<div class="rps-sc-text entry-content">';
            echo '<ul>';
            $entries = $this->_rpsdb->getEightsAndHigher('', $season);
            $images = array_rand($entries, 5);

            foreach ( $images as $key ) {
                $recs = $entries[$key];
                $user_info = get_userdata($recs['Member_ID']);
                $recs['FirstName'] = $user_info->user_firstname;
                $recs['LastName'] = $user_info->user_lastname;
                $recs['Username'] = $user_info->user_login;

                // Grab a new record from the database
                $dateParts = explode(" ", $recs['Competition_Date']);
                $comp_date = $dateParts[0];
                $medium = $recs['Medium'];
                $classification = $recs['Classification'];
                $comp = "$classification<br>$medium";
                $title = $recs['Title'];
                $last_name = $recs['LastName'];
                $first_name = $recs['FirstName'];
                $award = $recs['Award'];
                // Display this thumbnail in the the next available column
                echo '<li class="suf-widget">';
                echo '<div class="dbx-box">';
                echo '	<div class="image">';
                echo '	<a href="' . $this->_core->rpsGetThumbnailUrl($recs, 800) . '" rel="rps-showcase" title="' . $title . ' by ' . $first_name . ' ' . $last_name . '">';
                echo '	<img class="thumb_img" src="' . $this->_core->rpsGetThumbnailUrl($recs, 150) . '" /></a>';
                echo '	</div>';
                // echo " <div class='rps_showcase_title'>$title</div>";
                echo "</div>\n";

                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
            echo '</div>';
        }
    }

    public function shortcodeRpsMonthlyWinners ($atts, $content = '')
    {
        global $post;
        $months = array();
        $themes = array();

        $this->_settings->selected_season = '';
        $this->_settings->season_start_date = "";
        $this->_settings->season_end_date = "";
        $this->_settings->season_start_year = "";
        $this->_settings->selected_year = "";
        $this->_settings->selected_month = "";

        if ( isset($_POST['submit_control']) ) {
            $this->_settings->selected_season = esc_attr($_POST['selected_season']);
            $this->_settings->season_start_year = substr($this->_settings->selected_season, 0, 4);
            $this->_settings->selected_year = esc_attr($_POST['selected_year']);
            $this->_settings->selected_month = esc_attr($_POST['selected_month']);

            switch ( $_POST['submit_control'] )
            {
                case 'new_season':
                    $this->_settings->selected_season = esc_attr($_POST['new_season']);
                    $this->_settings->season_start_year = substr($this->_settings->selected_season, 0, 4);
                    $this->_settings->selected_month = "";
                    break;
                case 'new_month':
                    $this->_settings->selected_year = substr(esc_attr($_POST['new_month']), 0, 4);
                    $this->_settings->selected_month = substr(esc_attr($_POST['new_month']), 5, 2);
            }
        }
        $seasons = $this->_rpsdb->getSeasonList();
        if ( empty($this->_settings->selected_season) ) {
            $this->_settings->selected_season = $seasons[count($seasons) - 1];
        }
        $this->_settings->season_start_year = substr($this->_settings->selected_season, 0, 4);
        $this->_settings->season_start_date = sprintf("%d-%02s-%02s", $this->_settings->season_start_year, $this->_settings->club_season_start_month_num, 1);
        $this->_settings->season_end_date = sprintf("%d-%02s-%02s", $this->_settings->season_start_year + 1, $this->_settings->club_season_start_month_num, 1);

        $scores = $this->_rpsdb->getMonthlyScores();

        if ( is_array($scores) && ( !empty($scores) ) ) {
            $scored_competitions = true;
        } else {
            $scored_competitions = false;
        }

        if ( $scored_competitions ) {
            foreach ( $scores as $recs ) {
                $key = sprintf("%d-%02s", $recs['Year'], $recs['Month_Num']);
                $months[$key] = $recs['Month'];
                $themes[$key] = $recs['Theme'];
            }

            if ( empty($this->_settings->selected_month) ) {
                end($months);
                $this->_settings->selected_year = substr(key($months), 0, 4);
                $this->_settings->selected_month = substr(key($months), 5, 2);
            }
        }

        // Count the maximum number of awards in the selected competitions
        $this->_settings->min_date = sprintf("%d-%02s-%02s", $this->_settings->selected_year, $this->_settings->selected_month, 1);
        if ( $this->_settings->selected_month == 12 ) {
            $this->_settings->max_date = sprintf("%d-%02s-%02s", $this->_settings->selected_year + 1, 1, 1);
        } else {
            $this->_settings->max_date = sprintf("%d-%02s-%02s", $this->_settings->selected_year, $this->_settings->selected_month + 1, 1);
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
        $action = home_url('/' . get_page_uri($post->ID));
        $form = '';
        $form .= '<form name="winners_form" action="' . $action . '" method="post">' . "\n";
        $form .= '<input name="submit_control" type="hidden">' . "\n";
        $form .= '<input name="selected_season" type="hidden" value="' . $this->_settings->selected_season . '">' . "\n";
        $form .= '<input name="selected_year" type="hidden" value="' . $this->_settings->selected_year . '">' . "\n";
        $form .= '<input name="selected_month" type="hidden" value="' . $this->_settings->selected_month . '">' . "\n";

        if ( $scored_competitions ) {
            // Drop down list for months
            $form .= '<select name="new_month" onchange="submit_form(\'new_month\')">' . "\n";
            foreach ( $months as $key => $month ) {
                $selected = ( substr($key, 5, 2) == $this->_settings->selected_month ) ? " selected" : "";
                $form .= '<option value="' . $key . '"' . $selected . '>' . $month . '</option>' . "\n";
            }
            $form .= "</select>\n";
        }

        // Drop down list for season
        $form .= '<select name="new_season" onChange="submit_form(\'new_season\')">' . "\n";
        foreach ( $seasons as $season ) {
            $selected = ( $season == $this->_settings->selected_season ) ? " selected" : "";
            $form .= '<option value="' . $season . '"' . $selected . '>' . $season . '</option>' . "\n";
        }
        $form .= '</select>' . "\n";
        $form .= '</form>';
        echo $form;
        unset($form);
        echo '</span>';

        if ( $scored_competitions ) {
            $this_month = sprintf("%d-%02s", $this->_settings->selected_year, $this->_settings->selected_month);
            echo '<h4 class="competition-theme">Theme is ' . $themes[$this_month] . '</h4>';

            echo "<table class=\"thumb_grid\">\n";
            // Output the column headings
            echo "<tr><th class='thumb_col_header' align='center'>Competition</th>\n";
            for ( $i = 0; $i < $max_num_awards; $i++ ) {
                switch ( $i )
                {
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
                $dateParts = split(" ", $recs['Competition_Date']);
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
                $this->_core->rpsCreateThumbnail($recs, 75);
                $this->_core->rpsCreateThumbnail($recs, 400);
                echo "<td align=\"center\" class=\"thumb_cell\">\n";
                echo "  <div class=\"thumb_canvas\">\n";
                echo "<div id='rps_colorbox_title'>$title<br />Award: $award</div>";
                echo "    <a href=\"" . $this->_core->rpsGetThumbnailUrl($recs, 400) . "\" rel=\"" . tag_escape($classification) . tag_escape($medium) . "\" title=\"($award) $title\">\n";
                echo "    <img class=\"thumb_img\" src=\"" . $this->_core->rpsGetThumbnailUrl($recs, 75) . "\" /></a>\n";
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
        } else {
            echo 'There are no scored competitions for the selected season.';
        }
        echo "<br />\n";
    }

    public function shortcodeRpsScoresCurrentUser ($atts, $content = '')
    {
        global $post;

        if ( isset($_POST['selected_season_list']) ) {
            $this->_settings->selected_season = $_POST['selected_season_list'];
        }
        // Get the list of seasons
        $seasons = $this->_rpsdb->getSeasonList();
        if ( empty($this->_settings->selected_season) ) {
            $this->_settings->selected_season = $seasons[count($seasons) - 1];
        }
        $this->_settings->season_start_year = substr($this->_settings->selected_season, 0, 4);
        $this->_settings->season_start_date = sprintf("%d-%02s-%02s", $this->_settings->season_start_year, $this->_settings->club_season_start_month_num, 1);
        $this->_settings->season_end_date = sprintf("%d-%02s-%02s", $this->_settings->season_start_year + 1, $this->_settings->club_season_start_month_num, 1);

        // Start building the form
        $action = home_url('/' . get_page_uri($post->ID));
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
        if ( empty($scores) ) {
            echo "<tr><td colspan=\"6\">No entries submitted</td></tr>\n";
            echo "</table>\n";
        } else {

            // Build the list of submitted images
            $compCount = 0;
            $prev_date = "";
            $prev_medium = "";
            foreach ( $scores as $recs ) {
                $dateParts = explode(" ", $recs['Competition_Date']);
                $dateParts[0] = strftime('%d-%b-%Y', strtotime($dateParts[0]));
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

                $a = realpath($recs['Server_File_Name']);
                $image_url = home_url(str_replace('/home/rarit0/public_html', '', $recs['Server_File_Name']));

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
                // echo "<td align=\"left\" valign=\"top\" class=\"$rowStyle\"><a href=\"$image_url\" target=\"_blank\">$title</a></td>\n";
                $score_award = "";
                if ( $score > "" ) {
                    $score_award = " / {$score}pts";
                }
                if ( $award > "" ) {
                    $score_award .= " / $award";
                }
                echo "<td align=\"left\" valign=\"top\" class=\"$rowStyle\"><a href=\"$image_url\" rel=\"lightbox[{$comp_date}]\" title=\"" . htmlentities($title) . " / $comp_date / $medium{$score_award}\">" . htmlentities($title) . "</a></td>\n";
                echo "<td class=\"$rowStyle\" valign=\"top\" align=\"center\" width=\"8%\">$score</td>\n";
                echo "<td class=\"$rowStyle\" valign=\"top\" align=\"center\" width=\"8%\">$award</td></tr>\n";
            }
            echo "</table>";
        }
    }

    public function shortcodeRpsAllScores ($atts, $content = '')
    {
        global $post;
        if ( isset($_POST['selected_season_list']) ) {
            $this->_settings->selected_season = $_POST['selected_season_list'];
        }
        $award_map = array('1st' => '1','2nd' => '2','3rd' => '3','HM' => 'H');

        $seasons = $this->_rpsdb->getSeasonListOneEntry();
        arsort($seasons);
        if ( !isset($this->_settings->selected_season) ) {
            $this->_settings->selected_season = $seasons[count($seasons) - 1];
        }

        $this->_settings->season_start_year = substr($this->_settings->selected_season, 0, 4);
        $this->_settings->season_start_date = sprintf("%d-%02s-%02s", $this->_settings->season_start_year, 9, 1);
        $this->_settings->season_end_date = sprintf("%d-%02s-%02s", $this->_settings->season_start_year + 1, 9, 1);

        $competition_dates = $this->_rpsdb->getClubCompetitionDates();
        // Build an array of competition dates in "MM/DD" format for column titles.
        // Also remember the max entries per member for each competition and the number
        // of judges for each competition.
        $total_max_entries = 0;
        foreach ( $competition_dates as $key => $recs ) {
            $comp_date = $recs['Competition_Date'];
            $date_parts = explode(" ", $comp_date);
            list ($comp_year, $comp_month, $comp_day) = explode("-", $date_parts[0]);
            $comp_dates[$date_parts[0]] = sprintf("%d/%d", $comp_month, $comp_day);
            $comp_max_entries[$date_parts[0]] = $recs['Max_Entries'];
            $total_max_entries += $recs['Max_Entries'];
            $comp_num_judges[$date_parts[0]] = $recs['Num_Judges'];
        }

        $club_competition_results_unsorted = $this->_rpsdb->getClubCompetitionResults();
        $club_competition_results = $this->_core->avh_array_msort($club_competition_results_unsorted, array('Medium' => array(SORT_DESC),'Class_Code' => array(SORT_ASC),'LastName' => array(SORT_ASC),'FirstName' => array(SORT_ASC),'Competition_Date' => array(SORT_ASC)));
        // Bail out if no entries found
        if ( empty($club_competition_results) ) {
            echo 'No entries submitted';
        } else {

            // Start the big table

            $action = home_url('/' . get_page_uri($post->ID));
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
                $date_parts = explode(" ", $recs['Competition_Date']);
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
                        echo "<td align=\"center\" class=\"$rowStyle\">" . substr($prev_class, 0, 1) . "</td>\n";

                        // Iterate through all the accumulated scores for this member
                        foreach ( $member_scores as $key => $score_array ) {
                            // Print the scores for the submitted entries for this month
                            for ( $i = 0; $i < count($score_array); $i++ ) {
                                echo "<td align=\"center\" class=\"$rowStyle\">$score_array[$i]</td>\n";
                            }
                            // Pad the unused entries for this member for this month
                            for ( $i = 0; $i < $comp_max_entries[$key] - count($score_array); $i++ ) {
                                echo "<td align=\"center\" class=\"$rowStyle\">&nbsp;</td>\n";
                            }
                        }

                        // Display the members annual average score
                        if ( $total_score > 0 && $num_scores > 0 ) {
                            echo "<td align=\"center\" class=\"$rowStyle\">" . sprintf("%3.1f", $total_score / $num_scores) . "</td>\n";
                        } else {
                            echo "<td align=\"center\" class=\"$rowStyle\">&nbsp;</td>\n";
                        }
                        echo "</tr>";
                    }

                    // Now that we've just output the scores for the previous member, are we at the
                    // beginning of a new classification, but not at the end of the current medium?
                    // If so, draw a horizonal line to mark the beginning of a new classification
                    if ( $classification != $prev_class && $medium == $prev_medium ) {
                        // echo "<tr class=\"horizontal_separator\">";
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
                            // echo "<td colspan=\"" . (count($comp_dates) * 2 + 3) .
                            // "\" class=\"horizontal_separator\"></td>";
                            echo "<td colspan=\"" . ( $total_max_entries + 3 ) . "\" class=\"horizontal_separator\"></td>";
                            echo "</tr>\n";
                        }

                        // Display the category title
                        echo '<tr><td align="left" class="form_title" colspan="' . ( $total_max_entries + 3 ) . '">';
                        echo $medium . ' scores for ' . $this->_settings->selected_season . ' season';
                        echo '</td></tr>' . "\n";

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
                    if ( $score - floor($score) > 0 ) {
                        $score = round($score, 1);
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
            echo "<td align=\"center\" class=\"$rowStyle\">" . substr($classification, 0, 1) . "</td>\n";
            // Iterate through all the accumulated scores for this member
            foreach ( $member_scores as $key => $score_array ) {
                // Print the scores for the submitted entries for this month
                for ( $i = 0; $i < count($score_array); $i++ ) {
                    echo "<td align=\"center\" class=\"$rowStyle\">$score_array[$i]</td>\n";
                }
                // Pad the unused entries for this member for this month
                for ( $i = 0; $i < $comp_max_entries[$key] - count($score_array); $i++ ) {
                    echo "<td align=\"center\" class=\"$rowStyle\">&nbsp;</td>\n";
                }
            }

            // Display the members annual average score
            if ( $total_score > 0 && $num_scores > 0 ) {
                echo "<td align=\"center\" class=\"$rowStyle\">" . sprintf("%3.1f", $total_score / $num_scores) . "</td>\n";
            } else {
                echo "<td align=\"center\" class=\"$rowStyle\">&nbsp;</td>\n";
            }
            echo "</tr>";

            // We're all done
            echo "</table>";
        }
    }

    public function actionPreHeader_RpsMyEntries ()
    {
        global $post;

        if ( is_object($post) && ( $post->ID == 56 || $post->ID == 58 ) ) {
            $this->_settings->comp_date = "";
            $this->_settings->classification = "";
            $this->_settings->medium = "";
            $this->_errmsg = '';

            $page = explode('-', $post->post_name);
            $this->_settings->medium_subset = $page[1];
            if ( isset($_POST['submit_control']) ) {
                // @TODO Nonce check

                $this->_settings->comp_date = $_POST['comp_date'];
                $this->_settings->classification = $_POST['classification'];
                $this->_settings->medium = $_POST['medium'];
                $t = time() + ( 2 * 24 * 3600 );
                $url = parse_url(get_bloginfo('url'));
                setcookie("RPS_MyEntries", $this->_settings->comp_date . "|" . $this->_settings->classification . "|" . $this->_settings->medium, $t, '/', $url['host']);

                if ( isset($_POST['EntryID']) ) {
                    $entry_array = $_POST['EntryID'];
                }
                $medium_subset = $_POST['medium_subset'];
                $medium_param = "?medium=" . strtolower($medium_subset);

                switch ( $_POST['submit_control'] )
                {

                    case 'select_comp':
                        $this->_settings->comp_date = $_POST['select_comp'];
                        break;

                    case 'select_medium':
                        $this->_settings->medium = $_POST['select_medium'];
                        break;

                    case 'add':
                        if ( !$this->_rpsdb->getCompetionClosed() ) {
                            $_query = array('m' => $this->_settings->medium_subset);
                            $_query = build_query($_query);
                            $loc = '/upload-image/?' . $_query;
                            wp_redirect($loc);
                        }
                        break;

                    case 'edit':
                        if ( !$this->_rpsdb->getCompetionClosed() ) {
                            if ( is_array($entry_array) ) {
                                foreach ( $entry_array as $id ) {
                                    // @TODO Add Nonce
                                    $_query = array('id' => $id,'m' => $this->_settings->medium_subset);
                                    $_query = build_query($_query);
                                    $loc = '/edit-title/?' . $_query;
                                    wp_redirect($loc);
                                }
                            }
                        }
                        break;

                    case 'delete':
                        if ( !$this->_rpsdb->getCompetionClosed() ) {
                            $this->_deleteCompetitionEntries($entry_array);
                        }
                        break;
                }
            }

            // Get the currently selected competition
            if ( !$_POST ) {
                if ( isset($_COOKIE['RPS_MyEntries']) ) {
                    list ($comp_date, $classification, $medium) = explode("|", $_COOKIE['RPS_MyEntries']);
                    $this->_settings->comp_date = $comp_date;
                    $this->_settings->classification = $classification;
                    $this->_settings->medium = $medium;
                }
            }
            $this->_settings->validComp = $this->_validateSelectedComp($this->_settings->comp_date, $this->_settings->medium);
            if ( $this->_settings->validComp === false ) {
                $this->_settings->comp_date = "";
                $this->_settings->classification = "";
                $this->_settings->medium = "";
                $this->_errmsg = 'There are no competitions available to enter';
                // Invalidate any existing cookie
                $past = time() - ( 24 * 3600 );
                $url = parse_url(get_bloginfo(url));
                setcookie("RPS_MyEntries", $this->_settings->comp_date . "|" . $this->_settings->classification . "|" . $this->_settings->medium, $past, '/', $url['host']);
            }
        }
    }

    public function shortcodeRpsMyEntries ($atts, $content = '')
    {
        global $post;

        echo '<script language="javascript">' . "\n";
        echo '	function confirmSubmit() {' . "\n";
        echo '		var agree=confirm("You are about to delete one or more entries.  Are you sure?");' . "\n";
        echo '		if (agree) {' . "\n";
        echo '			submit_form(\'delete\');' . "\n";
        echo '			return true ;' . "\n";
        echo '		} else {' . "\n";
        echo '			return false ;' . "\n";
        echo '		}' . "\n";
        echo ' }' . "\n";
        echo 'function submit_form(control_name) {' . "\n";
        echo '	document.MyEntries.submit_control.value = control_name;' . "\n";
        echo '	document.MyEntries.submit();' . "\n";
        echo '}' . "\n";
        echo '</script>' . "\n";

        extract(shortcode_atts(array('medium' => 'digital'), $atts));
        $this->_settings->medium_subset = $medium;

        if ( !empty($this->_errmsg) ) {
            echo '<div id="errmsg">' . $this->_errmsg . '</div>';
        }
        // Start the form
        $action = home_url('/' . get_page_uri($post->ID));
        $form = '';
        echo '<form name="MyEntries" action=' . $action . ' method="post">' . "\n";
        echo '<input type="hidden" name="submit_control">' . "\n";
        echo '<input type="hidden" name="comp_date" value="' . $this->_settings->comp_date . '">' . "\n";
        echo '<input type="hidden" name="classification" value="' . $this->_settings->classification . '">' . "\n";
        echo '<input type="hidden" name="medium" value="' . $this->_settings->medium . '">' . "\n";
        echo '<input type="hidden" name="medium_subset" value="' . $this->_settings->medium_subset . '">' . "\n";
        echo '<input type="hidden" name="_wpnonce" value="' . wp_create_nonce('avh-rps-myentries') . '" />' . "\n";
        echo '<table class="form_frame" width="90%">' . "\n";

        // Form Heading
        if ( $this->_settings->validComp ) {
            echo "<tr><th colspan=\"6\" align=\"center\" class=\"form_frame_header\">My Entries for " . $this->_settings->medium . " on " . strftime('%d-%b-%Y', strtotime($this->_settings->comp_date)) . "</th></tr>\n";
        } else {
            echo "<tr><th colspan=\"6\" align=\"center\" class=\"form_frame_header\">Make a selection</th></tr>\n";
        }
        echo "<tr><td align=\"center\" colspan=\"6\">\n";
        echo "<table width=\"100%\">\n";
        $theme_uri_images = get_stylesheet_directory_uri() . '/images';
        echo '<tr>';
        echo '<td width="25%">';
        // echo '<span class="rps-comp-medium">' . $this->_settings->medium . '</span>';
        switch ( $this->_settings->medium )
        {
            case "Color Digital":
                $img = '/thumb-comp-digital-color.jpg';
                break;
            case "Color Prints":
                $img = '/thumb-comp-print-color.jpg';
                break;
            case "B&W Digital":
                $img = '/thumb-comp-digital-bw.jpg';
                break;
            case "B&W Prints":
                $img = '/thumb-comp-print-bw.jpg';
                break;
            default:
                $img = '';
        }

        echo '<img src="' . $this->_settings->plugin_url . '/images' . $img . '">';
        echo '</td>';
        echo "<td width=\"75%\">\n";
        echo "<table width=\"100%\">\n";

        // The competition date dropdown list
        echo "<tr>\n";
        echo "<td width=\"33%\" align=\"right\"><b>Competition Date:&nbsp;&nbsp;</b></td>\n";
        echo "<td width=\"64%\" align=\"left\">\n";

        echo "<SELECT name=\"select_comp\" onchange=\"submit_form('select_comp')\">\n";
        // Load the values into the dropdown list
        $prev_date = "";
        for ( $i = 0; $i < count($this->_open_comp_date); $i++ ) {
            if ( $this->_open_comp_date[$i] != $prev_date ) {
                if ( $this->_settings->comp_date == $this->_open_comp_date[$i] ) {
                    $selected = " SELECTED";
                    $theme = $this->_open_comp_theme[$i];
                } else {
                    $selected = "";
                }
                echo "<OPTION value=\"" . $this->_open_comp_date[$i] . "\"$selected>" . strftime('%d-%b-%Y', strtotime($this->_open_comp_date[$i])) . " " . $this->_open_comp_theme[$i] . "</OPTION>\n";
            }
            $prev_date = $this->_open_comp_date[$i];
        }
        echo "</SELECT>\n";
        echo "</td></tr>\n";

        // Competition medium dropdown list
        echo "<tr>\n<td width=\"33%\" align=\"right\"><b>Competition:&nbsp;&nbsp;</b></td>\n";
        echo "<td width=\"64%\" align=\"left\">\n";
        echo "<SELECT name=\"select_medium\" onchange=\"submit_form('select_medium')\">\n";
        // Load the values into the dropdown list
        for ( $i = 0; $i < count($this->_open_comp_date); $i++ ) {
            if ( $this->_open_comp_date[$i] == $this->_settings->comp_date ) {
                if ( $this->_settings->medium == $this->_open_comp_medium[$i] ) {
                    $selected = " SELECTED";
                } else {
                    $selected = "";
                }
                echo "<OPTION value=\"" . $this->_open_comp_medium[$i] . "\"$selected>" . $this->_open_comp_medium[$i] . "</OPTION>\n";
            }
        }
        echo "</SELECT>\n";
        echo "</td></tr>\n";

        // Display the Classification and Theme for the selected competition
        echo "<tr><td width=\"33%\" align=\"right\"><b>Classification:&nbsp;&nbsp;<b></td>\n";
        echo "<td width=\"64%\" align=\"left\">" . $this->_settings->classification . "</td></tr>\n";
        echo "<tr><td width=\"33%\" align=\"right\"><b>Theme:&nbsp;&nbsp;<b></td>\n";
        echo "<td width=\"64%\" align=\"left\">$theme</td></tr>\n";

        echo "</table>\n";
        echo "</td></tr></table>\n";

        // Display a warning message if the competition is within one week aka 604800 secs (60*60*24*7) of closing
        if ( $this->_settings->comp_date != "" ) {
            $close_date = $this->_rpsdb->getCompetitionCloseDate();
            if ( !empty($close_date) ) {
                $close_epoch = strtotime($close_date);
                $time_to_close = $close_epoch - current_time('timestamp');
                if ( $time_to_close >= 0 && $time_to_close <= 604800 ) {
                    echo "<tr><td colspan=\"6\" align=\"center\" style=\"color:red\"><b>Note:</b> This competition will close on " . mysql2date("F j, Y", $close_date) . " at " . mysql2date('h:i a', $close_date) . "</td></tr>\n";
                }
            }
        }

        // Display the column headers for the competition entries
        echo '<tr>';
        echo '<th class="form_frame_header" width="5%">&nbsp;</th>';
        echo '<th class="form_frame_header" width="10%">Image</th>';
        echo '<th class="form_frame_header" width="40%">Title</th>';
        echo '<th class="form_frame_header" width="25%">File Name</th>';
        echo '<th class="form_frame_header" width="10%">Width</th>';
        echo '<th class="form_frame_header" width="10%">Height</th>';
        echo '</tr>';

        // Retrieve the maximum number of entries per member for this competition
        $max_entries_per_member_per_comp = $this->_rpsdb->getCompetitionMaxEntries();

        // Retrive the total number of entries submitted by this member for this competition date
        $total_entries_submitted = $this->_rpsdb->getCompetitionEntriesUser();

        $entries = $this->_rpsdb->getCompetitionSubmittedEntriesUser();
        // Build the rows of submitted images
        $numRows = 0;
        $numOversize = 0;
        foreach ( $entries as $recs ) {
            $numRows += 1;
            $rowStyle = $numRows % 2 == 1 ? "odd_row" : "even_row";

            // Checkbox column
            echo '<tr class="' . $rowStyle . '"><td align="center" width="5%"><input type="checkbox" name="EntryID[]" value="' . $recs['ID'] . '">' . "\n";

            // Thumbnail column
            $user = wp_get_current_user();
            $a = realpath($recs['Server_File_Name']);
            $image_url = home_url(str_replace('/home/rarit0/public_html', '', $recs['Server_File_Name']));
            echo "<td align=\"center\" width=\"10%\">\n";
            // echo "<div id='rps_colorbox_title'>" . htmlentities($recs['Title']) . "<br />" . $this->_settings->classification . " " . $this->_settings->medium . "</div>";
            echo '<a href="' . $image_url . '" rel="' . $this->_settings->comp_date . '" title="' . $recs['Title'] . ' ' . $this->_settings->classification . ' ' . $this->_settings->medium . '">' . "\n";
            echo "<img src=\"" . $this->_core->rpsGetThumbnailUrl($recs, 75) . "\" />\n";
            echo "</a></td>\n";

            // Title column
            echo '<td align="left" width="40%">';
            // echo "<div id='rps_colorbox_title'>" . htmlentities($recs['Title']) . "<br />" . $this->_settings->classification . " " . $this->_settings->medium . "</div>";
            echo htmlentities($recs['Title']) . "</td>\n";
            // File Name
            echo '<td align="left" width="25%">' . $recs['Client_File_Name'] . "</td>\n";

            // Image width and height columns. The height and width values are suppressed if the Client_File_Name is
            // empty i.e. no image uploaded for a print competition.
            if ( file_exists($_SERVER['DOCUMENT_ROOT'] . str_replace('/home/rarit0/public_html', '', $recs['Server_File_Name'])) ) {
                $size = getimagesize($_SERVER['DOCUMENT_ROOT'] . str_replace('/home/rarit0/public_html', '', $recs['Server_File_Name']));
            } else {
                $size[0] = 0;
                $size[1] = 0;
            }
            if ( $recs['Client_File_Name'] > "" ) {
                if ( $size[0] > 1024 ) {
                    echo '<td align="center" style="color:red; font-weight:bold" width="10%">' . $size[0] . "</td>\n";
                } else {
                    echo '<td align="center" style="text-align:center" width="10%">' . $size[0] . "</td>\n";
                }
                if ( $size[1] > 768 ) {
                    echo '<td align="center" style="color:red; font-weight:bold" width="10%">' . $size[1] . "</td>\n";
                } else {
                    echo '<td align="center" width="10%">' . $size[1] . "</td>\n";
                }
            } else {
                echo "<td align=\"center\" width=\"10%\">&nbsp;</td>\n";
                echo "<td align=\"center\" width=\"10%\">&nbsp;</td>\n";
            }
            if ( $size[0] > 1024 || $size[1] > 768 ) {
                $numOversize += 1;
            }
        }

        // Add some instructional bullet points above the buttons
        echo "<tr><td align=\"left\" style=\"padding-top: 5px;\" colspan=\"6\">";
        echo "<ul style=\"margin:0;margin-left:15px;padding:0\">\n";
        if ( $numRows > 0 ) {
            echo "<li>Click the thumbnail or title to view the full size image</li>\n";
        }
        echo "<ul></td></tr>\n";

        // Warn the user about oversized images.
        if ( $numOversize > 0 ) {
            echo "<tr><td align=\"left\" style=\"padding-top: 5px;\" colspan=\"6\" class=\"warning_cell\">";
            echo "<ul style=\"margin:0;margin-left:15px;padding:0;color:red\"><li>When the Width or Height value is red, the image is too large to display on the projector. &nbsp;Here's what you need to do:\n";
            echo "<ul style=\"margin:0;margin-left:15px;padding:0\"><li>Remove the image from the competition. (check the corresponding checkbox and click Remove)</li>\n";
            echo "<li>Resize the image. &nbsp;Click <a href=\"/digital/Resize Digital Images.shtml\">here</a> for instructions.</li>\n";
            echo "<li>Upload the resized image.</li></ul></ul>\n";
        }
        if ( isset($_GET['resized']) && ( '1' == $_GET['resized'] ) ) {
            echo "<tr><td align=\"left\" colspan=\"6\" class=\"warning_cell\">";
            echo "<ul><li><b>Note</b>: The web site automatically resized your image to match the digital projector.\n";
            echo "</li></ul>\n";
        }

        // Buttons at the bottom of the list of submitted images
        echo "<tr><td align=\"center\" style=\"padding-top: 10px; text-align:center\" colspan=\"6\">\n";
        // Don't show the Add button if the max number of images per member reached
        if ( $numRows < $max_entries_per_member_per_comp && $total_entries_submitted < $this->_settings->club_max_entries_per_member_per_date ) {
            echo "<input type=\"submit\" name=\"submit[add]\" value=\"Add\" onclick=\"submit_form('add')\">&nbsp;\n";
        }
        if ( $numRows > 0 && $max_entries_per_member_per_comp > 0 ) {
            echo "<input type=\"submit\" name=\"submit[edit_title]\" value=\"Change Title\"  onclick=\"submit_form('edit')\">" . "&nbsp;\n";
        }
        if ( $numRows > 0 ) {
            echo '<input type="submit" name="submit[delete]" value="Remove" onclick="return  confirmSubmit()"></td></tr>' . "\n";
        }

        // All done, close out the table and the form
        echo "</table>\n</form>\n<br />\n";
    }

    public function actionPreHeader_RpsEditTitle ()
    {
        global $post;

        if ( is_object($post) && $post->ID == 75 ) {
            if ( !empty($_POST) ) {
                $redirect_to = $_POST['wp_get_referer'];
                $this->_medium_subset = $_POST['m'];
                $this->_entry_id = $_POST['id'];

                // Just return to the My Images page is the user clicked Cancel
                if ( isset($_POST['cancel']) ) {

                    wp_redirect($redirect_to);
                    exit();
                }

                if ( isset($_POST['m']) ) {

                    if ( get_magic_quotes_gpc() ) {
                        $server_file_name = stripslashes($_POST['server_file_name']);
                        $new_title = stripslashes(trim($_POST['new_title']));
                    } else {
                        $server_file_name = $_POST['server_file_name'];
                        $new_title = trim($_POST['new_title']);
                    }
                }
                // makes sure they filled in the title field
                if ( !$_POST['new_title'] || trim($_POST['new_title']) == "" ) {
                    $this->_errmsg = 'You must provide an image title.<br><br>';
                } else {
                    $recs = $this->_rpsdb->getCompetitionByID($this->_entry_id);
                    if ( $recs == null ) {
                        wp_die("Failed to SELECT competition for entry ID: " . $this->_entry_id);
                    }

                    $dateParts = explode(" ", $recs['Competition_Date']);
                    $comp_date = $dateParts[0];
                    $classification = $recs['Classification'];
                    $medium = $recs['Medium'];

                    // Rename the image file on the server file system
                    $ext = ".jpg";
                    $path = '/Digital_Competitions/' . $comp_date . '_' . $classification . '_' . $medium;
                    $old_file_parts = pathinfo($server_file_name);
                    $old_file_name = $old_file_parts['filename'];
                    $current_user = wp_get_current_user();
                    $new_file_name_noext = sanitize_file_name($_POST['new_title']) . '+' . $current_user->user_login;
                    $new_file_name = sanitize_file_name($new_title) . '+' . $current_user->user_login . $ext;
                    if ( !$this->_core->rps_rename_image_file($path, $old_file_name, $new_file_name_noext, $ext) ) {
                        die("<b>Failed to rename image file</b><br>" . "Path: $path<br>Old Name: $old_file_name<br>" . "New Name: $new_file_name_noext");
                    }

                    // Update the Title and File Name in the database
                    $_result = $this->_rpsdb->updateEntriesTitle($new_title, $path . '/' . $new_file_name, $this->_entry_id);
                    if ( $_result === false ) {
                        wp_die("Failed to UPDATE entry record from database");
                    }

                    $redirect_to = $_POST['wp_get_referer'];
                    wp_redirect($redirect_to);
                    exit();
                }
            }
        }
    }

    public function shortcodeRpsEditTitle ()
    {
        global $post;
        if ( isset($_GET['m']) ) {
            if ( $_GET['m'] == "prints" ) {
                $medium_subset = "Prints";
                $medium_param = "?m=prints";
            } else {
                $medium_subset = "Digital";
                $medium_param = "?m=digital";
            }
        }
        $entry_id = $_GET['id'];

        $recs = $this->_rpsdb->getEntryInfo($entry_id);
        $title = $recs['Title'];
        $server_file_name = $recs['Server_File_Name'];

        $relative_path = str_replace('/home/rarit0/public_html', '', $server_file_name);

        if ( isset($this->_errmsg) ) {
            echo '<div id="errmsg">';
            echo $this->_errmsg;
            echo '</div>';
        }
        $action = home_url('/' . get_page_uri($post->ID));
        echo '<form action="' . $action . $medium_param . '" method="post">';

        echo '<table class="form_frame" width="80%">';
        echo '<tr><th class="form_frame_header" colspan=2>Update Image Title</th></tr>';
        echo '<tr><td align="center">';
        echo '<table>';
        echo '<tr><td align="center" colspan="2">';

        echo "<img src=\"" . $this->_core->rpsGetThumbnailUrl($recs, 200) . "\" />\n";
        echo '</td></tr>';
        echo '<tr><td align="center" class="form_field_label">Title:</td><td class="form_field">';
        echo '<input style="width:300px" type="text" name="new_title" maxlength="128" value="' . htmlentities($title) . '">';
        echo '</td></tr>';
        echo '<tr><td style="padding-top:20px" align="center" colspan="2">';
        echo '<input type="submit" name="submit" value="Update">';
        echo '<input type="submit" name="cancel" value="Cancel">';
        echo '<input type="hidden" name="id" value="' . $entry_id . '" />';
        echo '<input type="hidden" name="title" value="' . $title . '" />';
        echo '<input type="hidden" name="server_file_name" value="' . $server_file_name . '" />';
        echo '<input type="hidden" name="m" value="' . strtolower($medium_subset) . '" />';
        echo '<input type="hidden" name="wp_get_referer" value="' . remove_query_arg(array('m','id'), wp_get_referer()) . '" />';
        echo '</td></tr>';
        echo '</table>';
        echo '</td></tr>';
        echo '</table>';
        echo '</form>';
    }

    public function actionPreHeader_RpsUploadEntry ()
    {
        global $post;

        if ( is_object($post) && $post->ID == 89 ) {
            if ( isset($_GET['post']) ) {
                $redirect_to = $_POST['wp_get_referer'];

                // Just return if user clicked Cancel
                if ( isset($_POST['cancel']) ) {
                    wp_redirect($redirect_to);
                    exit();
                }

                // First we have to dispose of a "bug?". If a file is uploaded and the size of the file exceeds
                // the value of 'post_max_size' in php.ini, the $_POST and $_FILES arrays will be cleared.
                // Detect this situation by comparing the length of the http content received with post_max_size
                if ( isset($_SERVER['CONTENT_LENGTH']) ) {
                    if ( $_SERVER['CONTENT_LENGTH'] > $this->_core->avh_ShortHandToBytes(ini_get('post_max_size')) ) {
                        $this->_errmsg = "Your submitted file failed to transfer successfully.<br>The submitted file is " . sprintf("%dMB", $_SERVER['CONTENT_LENGTH'] / 1024 / 1024) . " which exceeds the maximum file size of " . ini_get('post_max_size') . "B<br>" . "Click <a href=\"/competitions/resize_digital_images.html#Set_File_Size\">here</a> for instructions on setting the overall size of your file on disk.";
                    } else {
                        if ( !$this->_checkUploadEntryTitle() ) {
                            return;
                        }

                        // Verify that the uploaded image is a JPEG
                        $uploaded_file_name = $_FILES['file_name']['tmp_name'];
                        $size_info = getimagesize($uploaded_file_name);
                        if ( $size_info[2] != IMAGETYPE_JPEG ) {
                            $this->_errmsg = "Submitted file is not a JPEG image.  Please try again.<br>Click the Browse button to select a .jpg image file before clicking Submit";
                            return;
                        }

                        // Retrieve and parse the selected competition cookie
                        if ( isset($_COOKIE['RPS_MyEntries']) ) {
                            list ($this->_settings->comp_date, $this->_settings->classification, $this->_settings->medium) = explode("|", $_COOKIE['RPS_MyEntries']);
                        } else {
                            $this->_errmsg = "Upload Form Error<br>The Selected_Competition cookie is not set.";
                            return;
                        }

                        $recs = $this->_rpsdb->getIdmaxEntries();
                        if ( $recs ) {
                            $comp_id = $recs['ID'];
                            $max_entries = $recs['Max_Entries'];
                        } else {
                            $d = $this->comp_date;
                            $c = $this->classification;
                            $m = $this->medium;
                            $this->_errmsg = "Upload Form Error<br>Competition $d/$c/$m not found in database<br>";
                            return;
                        }

                        // Prepare the title and client file name for storing in the database
                        if ( !get_magic_quotes_gpc() ) {
                            $title = addslashes(trim($_POST['title']));
                            $client_file_name = addslashes(basename($_FILES['file_name']['name']));
                        } else {
                            $title = trim($_POST['title']);
                            $client_file_name = basename($_FILES['file_name']['name']);
                        }

                        // Before we go any further, make sure the title is not a duplicate of
                        // an entry already submitted to this competition. Dupliacte title result in duplicate
                        // file names on the server
                        if ( $this->_rpsdb->checkDuplicateTitle($comp_id, $title) ) {
                            $this->_errmsg = "You have already submitted an entry with a title of \"" . stripslashes($title) . "\" in this competition<br>Please submit your entry again with a different title.";
                            return;
                        }

                        // Do a final check that the user hasn't exceeded the maximum images per competition.
                        // If we don't check this at the last minute it may be possible to exceed the
                        // maximum images per competition by having two upload windows open simultaneously.
                        $max_per_id = $this->_rpsdb->checkMaxEntriesOnId($comp_id);
                        if ( $max_per_id >= $max_entries ) {
                            $this->_errmsg = "You have already submitted the maximum of $max_entries entries into this competition<br>You must Remove an image before you can submit another";
                            return;
                        }

                        $max_per_date = $this->_rpsdb->checkMaxEntriesOnDate();
                        if ( $max_per_date >= $this->_settings->club_max_entries_per_member_per_date ) {
                            $x = $this->_settings->club_max_entries_per_member_per_date;
                            $this->_errmsg = "You have already submitted the maximum of $x entries for this competition date<br>You must Remove an image before you can submit another";
                            return;
                        }

                        // Move the file to its final location
                        $comp_date = $this->_settings->comp_date;
                        $classification = $this->_settings->classification;
                        $medium = $this->_settings->medium;
                        $path = $_SERVER['DOCUMENT_ROOT'] . '/Digital_Competitions/' . $comp_date . '_' . $classification . '_' . $medium;

                        $title2 = stripslashes(trim($_POST['title']));
                        $user = wp_get_current_user();
                        $dest_name = sanitize_file_name($title2) . '+' . $user->user_login;
                        $full_path = $path . '/' . $dest_name;
                        // Need to create the destination folder?
                        if ( !is_dir($path) )
                            mkdir($path, 0755);

                            // If the .jpg file is too big resize it
                        if ( $size_info[0] > $this->_settings->max_width_entry || $size_info[1] > $this->_settings->max_height_entry ) {
                            // If this is a landscape image and the aspect ratio is less than the aspect ratio of the projector
                            if ( $size_info[0] > $size_info[1] && $size_info[0] / $size_info[1] < $this->_settings->max_width_entry / $this->_settings->max_height_entry ) {
                                // Set the maximum width to ensure the height does not exceed the maximum height
                                $size = $this->_settings->max_height_entry * $size_info[0] / $size_info[1];
                            } else {
                                // if its landscape and the aspect ratio is greater than the projector
                                if ( $size_info[0] > $size_info[1] ) {
                                    // Set the maximum width to the width of the projector
                                    $size = $this->_settings->max_width_entry;

                                    // If its a portrait image
                                } else {
                                    // Set the maximum height to the height of the projector
                                    $size = $this->_settings->max_height_entry;
                                }
                            }
                            // Resize the image and deposit it in the destination directory
                            $this->_core->rpsResizeImage($uploaded_file_name, $full_path . '.jpg', $size, 95, '');
                            // if (! $this->_core->rpsResizeImage($uploaded_file_name, $full_path . '.jpg', $size, 95, ''));
                            // {
                            // $this->_errmsg = "There is a problem resizing the picture for the use of the projector.";
                            // return;
                            // }
                            $resized = 1;

                            // The uploaded image does not need to be resized so just move it to the destination directory
                        } else {
                            $resized = 0;
                            if ( !move_uploaded_file($uploaded_file_name, $full_path . '.jpg') ) {
                                $this->_errmsg = "Failed to move uploaded file to destination folder";
                                return;
                            }
                        }
                        $server_file_name = str_replace($_SERVER['DOCUMENT_ROOT'], '', $full_path . '.jpg');
                        $data = array('Competition_ID' => $comp_id,'Title' => $title,'Client_File_Name' => $client_file_name,'Server_File_Name' => $server_file_name);
                        $_result = $this->_rpsdb->addEntry($data);
                        if ( $_result === false ) {
                            $this->_errmsg = "Failed to INSERT entry record into database";
                            return;
                        }
                        $query = build_query(array('resized' => $resized));
                        wp_redirect($redirect_to . '/?' . $query);
                        exit();
                    }
                }
            }
        }
    }

    public function shortcodeRpsUploadEntry ()
    {
        global $post;
        if ( isset($_GET['m']) ) {
            if ( $_GET['m'] == "prints" ) {
                $medium_subset = "Prints";
                $medium_param = "?m=prints";
            } else {
                $medium_subset = "Digital";
                $medium_param = "?m=digital";
            }
        }

        // Error messages
        if ( isset($this->_errmsg) ) {
            echo '<div id="errmsg">';
            echo $this->_errmsg;
            echo '</div>';
        }

        $action = home_url('/' . get_page_uri($post->ID));
        echo '<form action="' . $action . '/?post=1" enctype="multipart/form-data" method="post">';

        echo '<input type="hidden" name="medium_subset" value="' . $medium_subset . '" />';
        if ( isset($_POST['wp_get_referer']) ) {
            $_ref = $_POST['wp_get_referer'];
        } else {
            $_ref = wp_get_referer();
        }
        echo '<input type="hidden" name="wp_get_referer" value="' . remove_query_arg(array('m'), $_ref) . '" />';

        echo '<table class="form_frame" width="80%">';
        echo '<tr><th class="form_frame_header" colspan=2>';
        echo 'Submit Your Image';
        echo '</th></tr>';
        echo '<tr><td align="center">';
        echo '<table>';
        echo '<tr><td class="form_field_label"><span style="color:red"><sup>*</sup> </span>Title <i>(required)</i>:</td>';
        echo '<td class="form_field">';
        echo '<input style="width:300px" type="text" name="title" maxlength="128">';
        echo '</td></tr>';
        echo '<tr><td class="form_field_label"><span style="color:red"><sup>*</sup> </span>File Name <i>(required)</i>:</td>';
        echo '<td class="form_field">';
        echo '<input style="width:300px" type="file" name="file_name" maxlength="128">';
        echo '</td></tr>';
        echo '<tr><td align="center" style="padding-top:20px" colspan="2">';
        echo '<input type="submit" name="submit" value="Submit">';
        echo '<input type="submit" name="cancel" value="Cancel">';
        echo '</td></tr>';
        echo '</table>';
        echo '</td></tr>';
        echo '</table>';
        echo '</form>';
    }

    // ----- Private Functions --------

    /**
     * Create a XML File with the competition dates
     */
    private function _sendXmlCompetitionDates ()
    {
        // Connect to the Database
        try {
            $db = new RPSPDO();
        } catch (\PDOException $e) {
            $this->_doRESTError("Failed to obtain database handle " . $e->getMessage());
            die($e->getMessage());
        }

        try {
            $select = "SELECT DISTINCT(Competition_Date) FROM competitions ";
            if ( $_GET['closed'] || $_GET['scored'] ) {
                $where = "WHERE";
                if ( $_GET['closed'] ) {
                    $where .= " Closed=:closed";
                }
                if ( $_GET['scored'] ) {
                    $where .= " AND Scored=:scored";
                }
            } else {
                $where .= " Competition_Date >= CURDATE()";
            }

            $sth = $db->prepare($select . $where);
            if ( $_GET['closed'] ) {
                $_closed = $_GET['closed'];
                $sth->bindParam(':closed', $_closed, \PDO::PARAM_STR, 1);
            }
            if ( $_GET['scored'] ) {
                $_scored = $_GET['scored'];
                $sth->bindParam(':scored', $_scored, \PDO::PARAM_STR, 1);
            }
            $sth->execute();
        } catch (\PDOException $e) {
            $this->_doRESTError("Failed to SELECT list of competitions from database - " . $e->getMessage());
            die($e->getMessage());
        }

        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('rsp');
        $dom->appendChild($root);
        $stat = $dom->createAttribute("stat");
        $root->appendChild($stat);
        $value = $dom->CreateTextNode("ok");
        $stat->appendChild($value);
        $recs = $sth->fetch(\PDO::FETCH_ASSOC);
        while ( $recs != false ) {
            $dateParts = split(" ", $recs['Competition_Date']);
            $comp_date = $root->appendChild($dom->createElement('Competition_Date'));
            $comp_date->appendChild($dom->createTextNode($dateParts[0]));
            $recs = $sth->fetch(\PDO::FETCH_ASSOC);
        }
        echo $dom->saveXML();
        $db = null;
        die();
    }

    /**
     * Handles request by client to download images for a particular date,
     */
    private function _sendCompetitions ()
    {
        $username = $_REQUEST['username'];
        $password = $_REQUEST['password'];
        try {
            $db = new RPSPDO();
        } catch (\PDOException $e) {
            $this->_doRESTError("Failed to obtain database handle " . $e->getMessage());
            die($e->getMessage());
        }
        if ( $db !== false ) {
            $user = wp_authenticate($username, $password);
            if ( is_wp_error($user) ) {
                $a = strip_tags($user->get_error_message());
                $this->_doRESTError($a);
                die();
            }
            // @todo Check if the user has the role needed.
            $this->_sendXmlCompetitions($db, $_REQUEST['medium'], $_REQUEST['comp_date']);
        }
        die();
    }

    /**
     * Create a XML file for the client with information about images for a particular date
     *
     * @param object $db
     *        Connection to the RPS Database
     * @param string $requested_medium
     *        Which competition medium to use, either digital or print
     * @param string $comp_date
     *        The competition date
     */
    private function _sendXmlCompetitions ($db, $requested_medium, $comp_date)
    {

        /* @var $db RPSPDO */
        // Start building the XML response
        $dom = new \DOMDocument('1.0');
        // Create the root node
        $rsp = $dom->CreateElement('rsp');
        $rsp = $dom->AppendChild($rsp);
        $rsp->SetAttribute('stat', 'ok');

        $medium_clause = '';
        if ( !( empty($requested_medium) ) ) {
            $medium_clause = ( $requested_medium == "prints" ) ? " AND Medium like '%Prints' " : " AND Medium like '%Digital' ";
        }
        $sql = "SELECT ID, Competition_Date, Theme, Medium, Classification
        FROM competitions
        WHERE Competition_Date = DATE(:compdate) AND Closed = 'Y' $medium_clause
        ORDER BY Medium, Classification";
        try {
            $sth_competitions = $db->prepare($sql);
            $sth_competitions->bindParam(':compdate', $comp_date);
            $sth_competitions->execute();
        } catch (\Exception $e) {
            $this->_doRESTError("Failed to SELECT competition records with date = " . $comp_date . " from database - " . $e->getMessage());
            die();
        }
        // Create a Competitions node
        $xml_competions = $rsp->AppendChild($dom->CreateElement('Competitions'));
        // Iterate through all the matching Competitions and create corresponding Competition nodes
        $record_competitions = $sth_competitions->fetch(\PDO::FETCH_ASSOC);
        while ( $record_competitions !== false ) {
            $comp_id = $record_competitions['ID'];
            $dateParts = split(" ", $record_competitions['Competition_Date']);
            $date = $dateParts[0];
            $theme = $record_competitions['Theme'];
            $medium = $record_competitions['Medium'];
            $classification = $record_competitions['Classification'];
            // Create the competition node in the XML response
            $competition_element = $xml_competions->AppendChild($dom->CreateElement('Competition'));

            $date_element = $competition_element->AppendChild($dom->CreateElement('Date'));
            $date_element->AppendChild($dom->CreateTextNode($date));

            $theme_element = $competition_element->AppendChild($dom->CreateElement('Theme'));
            $theme_element->AppendChild($dom->CreateTextNode($theme));

            $medium_element = $competition_element->AppendChild($dom->CreateElement('Medium'));
            $medium_element->AppendChild($dom->CreateTextNode($medium));

            $xml_classification_node = $competition_element->AppendChild($dom->CreateElement('Classification'));
            $xml_classification_node->AppendChild($dom->CreateTextNode($classification));

            // Get all the entries for this competition
            try {
                $sql = "SELECT entries.ID, entries.Title, entries.Member_ID,
                        entries.Server_File_Name, entries.Score, entries.Award
                        FROM entries
                        WHERE entries.Competition_ID = :comp_id
                        ORDER BY entries.Title";
                $sth_entries = $db->prepare($sql);
                $sth_entries->bindParam(':comp_id', $comp_id, \PDO::PARAM_INT, 11);
                $sth_entries->execute();
            } catch (\Exception $e) {
                $this->_doRESTError("Failed to SELECT competition entries from database - " . $e->getMessage());
                die();
            }
            $all_records_entries = $sth_entries->fetchAll();
            // Create an Entries node

            $entries = $competition_element->AppendChild($dom->CreateElement('Entries'));
            // Iterate through all the entries for this competition
            foreach ( $all_records_entries as $record_entries ) {
                $user = get_user_by('id', $record_entries['Member_ID']);
                if ( $this->_core->isPaidMember($user->ID) ) {
                    $entry_id = $record_entries['ID'];
                    $first_name = $user->first_name;
                    $last_name = $user->last_name;
                    $title = $record_entries['Title'];
                    $score = $record_entries['Score'];
                    $award = $record_entries['Award'];
                    $server_file_name = $record_entries['Server_File_Name'];
                    // Create an Entry node
                    $entry_element = $entries->AppendChild($dom->CreateElement('Entry'));
                    $id = $entry_element->AppendChild($dom->CreateElement('ID'));
                    $id->AppendChild($dom->CreateTextNode($entry_id));
                    $fname = $entry_element->AppendChild($dom->CreateElement('First_Name'));
                    $fname->AppendChild($dom->CreateTextNode($first_name));
                    $lname = $entry_element->AppendChild($dom->CreateElement('Last_Name'));
                    $lname->AppendChild($dom->CreateTextNode($last_name));
                    $title_node = $entry_element->AppendChild($dom->CreateElement('Title'));
                    $title_node->AppendChild($dom->CreateTextNode($title));
                    $score_node = $entry_element->AppendChild($dom->CreateElement('Score'));
                    $score_node->AppendChild($dom->CreateTextNode($score));
                    $award_node = $entry_element->AppendChild($dom->CreateElement('Award'));
                    $award_node->AppendChild($dom->CreateTextNode($award));
                    // Convert the absolute server file name into a URL
                    $image_url = home_url(str_replace('/home/rarit0/public_html', '', $record_entries['Server_File_Name']));
                    $url_node = $entry_element->AppendChild($dom->CreateElement('Image_URL'));
                    $url_node->AppendChild($dom->CreateTextNode($image_url));
                }
            }
            $record_competitions = $sth_competitions->fetch(\PDO::FETCH_ASSOC);
        }
        // Send the completed XML response back to the client
        // header('Content-Type: text/xml');
        echo $dom->saveXML();
    }

    /**
     * Handle the uploaded score from the RPS Client.
     */
    private function _doUploadScore ()
    {
        $username = $_REQUEST['username'];
        $password = $_REQUEST['password'];
        $comp_date = $_REQUEST['date'];
        try {
            $db = new RPSPDO();
        } catch (\PDOException $e) {
            $this->_doRESTError("Failed to obtain database handle " . $e->getMessage());
            die();
        }
        if ( $db !== false ) {
            $user = wp_authenticate($username, $password);
            if ( is_wp_error($user) ) {
                $a = strip_tags($user->get_error_message());
                $this->_doRESTError("Unable to authenticate: $a");
                die();
            }
        }
        // Check to see if there were any file upload errors
        switch ( $_FILES['file']['error'] )
        {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
                $this->_doRESTError("The uploaded file exceeds the upload_max_filesize directive (" . ini_get("upload_max_filesize") . ") in php.ini.");
                die();
            case UPLOAD_ERR_FORM_SIZE:
                $this->_doRESTError("The uploaded file exceeds the maximum file size of " . $_POST[MAX_FILE_SIZE] / 1000 . "KB allowed by this form.");
                die();
            case UPLOAD_ERR_PARTIAL:
                $this->_doRESTError("The uploaded file was only partially uploaded.");
                die();
            case UPLOAD_ERR_NO_FILE:
                $this->_doRESTError("No file was uploaded.");
                die();
            case UPLOAD_ERR_NO_TMP_DIR:
                $this->_doRESTError("Missing a temporary folder.");
                die();
            case UPLOAD_ERR_CANT_WRITE:
                $this->_doRESTError("Failed to write file to disk");
                die();
            default:
                $this->_doRESTError("Unknown File Upload Error");
                die();
        }

        // Move the file to its final location
        $path = $_SERVER['DOCUMENT_ROOT'] . '/Digital_Competitions';
        $dest_name = "scores_" . $comp_date . ".xml";
        $file_name = $path . '/' . $dest_name;
        if ( !move_uploaded_file($_FILES['file']['tmp_name'], $file_name) ) {
            $this->_doRESTError("Failed to store scores XML file in destination folder.");
            die();
        }

        $warning = $this->_handleUploadScoresFile($db, $file_name);

        // Remove the uploaded .xml file
        unlink($file_name);

        // Return success to the client
        $warning = "  <info>Scores successfully uploaded</info>\n" . $warning;
        $this->_doRESTSuccess($warning);
        die();
    }

    /**
     * Handle the XML file containing the scores and add them to the database
     *
     * @param object $db
     *        Database handle.
     */
    private function _handleUploadScoresFile ($db, $file_name)
    {
        $warning = '';
        $score = '';
        $award = '';
        $entry_id = '';

        if ( !$xml = simplexml_load_file($file_name) ) {
            $this->_doRESTError("Failed to open scores XML file");
            die();
        }
        try {
            $sql = "UPDATE `entries` SET `Score` = :score, `Date_Modified` = NOW(), `Award` = :award WHERE `ID` = :entryid";
            $sth = $db->prepare($sql);
            $sth->bindParam(':score', $score, PDO::PARAM_STR);
            $sth->bindParam(':award', $award, PDO::PARAM_STR);
            $sth->bindParam(':entryid', $entry_id, PDO::PARAM_INT);
        } catch (\PDOException $e) {
            $this->_doRESTError("Error - " . $e->getMessage() . " - $sql");
            die();
        }

        foreach ( $xml->Competition as $comp ) {
            $comp_date = $comp->Date;
            $classification = $comp->Classification;
            $medium = $comp->Medium;

            foreach ( $comp->Entries as $entries ) {
                foreach ( $entries->Entry as $entry ) {
                    $entry_id = $entry->ID;
                    $first_name = html_entity_decode($entry->First_Name);
                    $last_name = html_entity_decode($entry->Last_Name);
                    $title = html_entity_decode($entry->Title);
                    $score = html_entity_decode($entry->Score);
                    $award = html_entity_decode($entry->Award);

                    if ( $entry_id != "" ) {
                        if ( $score != "" ) {
                            try {
                                $sth->execute();
                            } catch (\PDOException $e) {
                                $this->_doRESTError("Failed to UPDATE scores in database - " . $e->getMessage() . " - $sql");
                                die();
                            }
                            if ( $sth->rowCount() < 1 ) {
                                $warning .= "  <info>$comp_date, $first_name $last_name, $title -- Row failed to update</info>\n";
                            }
                        }
                    } else {
                        $warning .= "  <info>$comp_date, $first_name $last_name, $title -- ID is Null -- skipped</info>\n";
                    }
                }
            }

            // Mark this competition as scored
            try {
                $sql = "UPDATE competitions SET Scored='Y', Date_Modified=NOW()
                WHERE Competition_Date='$comp_date' AND
                Classification='$classification' AND
                Medium = '$medium'";
                if ( !$rs = mysql_query($sql) )
                    throw new \Exception(mysql_error());
            } catch (\Exception $e) {
                $this->_doRESTError("Failed to execute UPDATE to set Scored flag to Y in database for $comp_date / $classification");
                die();
            }
            if ( mysql_affected_rows() < 1 ) {
                $this->_doRESTError("No rows updated when setting Scored flag to Y in database for $comp_date / $classification");
                die();
            }
        }
        return $warning;
    }

    /**
     * Create a REST error
     *
     * @param string $errMsg
     *        The actual error message
     */
    private function _doRESTError ($errMsg)
    {
        $this->_doRESTResponse('fail', '<err msg="' . $errMsg . '" ></err>');
    }

    /**
     * Create a REST success message
     *
     * @param string $message
     *        The actual messsage
     */
    private function _doRESTSuccess ($message)
    {
        $this->_doRESTResponse("ok", $message);
    }

    /**
     * Create the REST respone
     *
     * @param string $status
     * @param string $message
     */
    private function _doRESTResponse ($status, $message)
    {
        echo '<?xml version="1.0" encoding="utf-8" ?>' . "\n";
        echo '<rsp stat="' . $status . '">' . "\n";
        echo '	' . $message . "\n";
        echo "</rsp>\n";
    }

    /**
     * Check the upload entry for errors.
     */
    private function _checkUploadEntryTitle ()
    {
        $_upload_ok = false;
        if ( !isset($_POST['title']) || trim($_POST['title']) == "" ) {
            $this->_errmsg = 'Please enter your image title in the Title field.';
        } else {
            switch ( $_FILES['file_name']['error'] )
            {
                case UPLOAD_ERR_OK:
                    $_upload_ok = true;
                    break;
                case UPLOAD_ERR_INI_SIZE:
                    $this->_errmsg = "The submitted file exceeds the upload_max_filesize directive (" . ini_get("upload_max_filesize") . "B) in php.ini.<br>Please report the exact text of this error message to the Digital Chair.<br>Try downsizing your image to 1024x788 pixels and submit again.";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $this->_errmsg = "The submitted file exceeds the maximum file size of " . $_POST[MAX_FILE_SIZE] / 1000 . "KB.<br />Click <a href=\"/digital/Resize Digital Images.shtml#Set_File_Size\">here</a> for instructions on setting the overall size of your file on disk.<br>Please report the exact text of this error message to the Digital Chair.</p>";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $this->_errmsg = "The submitted file was only partially uploaded.<br>Please report the exact text of this error message to the Digital Chair.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $this->_errmsg = "No file was submitted.&nbsp; Please try again.<br>Click the Browse button to select a .jpg image file before clicking Submit";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $this->_errmsg = "Missing a temporary folder.<br>Please report the exact text of this error message to the Digital Chair.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $this->_errmsg = "Failed to write file to disk on server.<br>Please report the exact text of this error message to the Digital Chair.";
                    break;
                default:
                    $this->_errmsg = "Unknown File Upload Error<br>Please report the exact text of this error message to the Digital Chair.";
            }
        }
        return $_upload_ok;
    }

    /**
     * Delete competition entries
     *
     * @param array $entries
     *        Array of entries ID to delete.
     */
    private function _deleteCompetitionEntries ($entries)
    {
        if ( is_array($entries) ) {
            foreach ( $entries as $id ) {

                $recs = $this->_rpsdb->getEntryInfo($id);
                if ( $recs == false ) {
                    $this->_errmsg = sprintf("<b>Failed to SELECT competition entry with ID %s from database</b><br>", $id);
                } else {

                    $server_file_name = $_SERVER['DOCUMENT_ROOT'] . str_replace('/home/rarit0/public_html/', '', $recs['Server_File_Name']);
                    // Delete the record from the database
                    $result = $this->_rpsdb->deleteEntry($id);
                    if ( $result === false ) {
                        $this->_errmsg = sprintf("<b>Failed to DELETE competition entry %s from database</b><br>");
                    } else {

                        // Delete the file from the server file system
                        if ( file_exists($server_file_name) ) {
                            unlink($server_file_name);
                        }
                        // Delete any thumbnails of this image
                        $ext = ".jpg";
                        $comp_date = $this->_settings->comp_date;
                        $classification = $this->_settings->classification;
                        $medium = $this->_settings->medium;
                        $path = $_SERVER['DOCUMENT_ROOT'] . '/Digital_Competitions/' . $comp_date . '_' . $classification . '_' . $medium;

                        $old_file_parts = pathinfo($server_file_name);
                        $old_file_name = $old_file_parts['filename'];

                        if ( is_dir($path . "/thumbnails") ) {
                            $thumb_base_name = $path . "/thumbnails/" . $old_file_name;
                            // Get all the matching thumbnail files
                            $thumbnails = glob("$thumb_base_name*");
                            // Iterate through the list of matching thumbnails and delete each one
                            if ( is_array($thumbnails) && count($thumbnails) > 0 ) {
                                foreach ( $thumbnails as $thumb ) {
                                    unlink($thumb);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Select the list of open competitions for this member's classification and validate the currently selected competition against that list.
     *
     * @param string $date
     * @param unknown $med
     * @return boolean
     */
    private function _validateSelectedComp ($date, $med)
    {
        $open_competitions = $this->_rpsdb->getOpenCompetitions($this->_settings->medium_subset);

        if ( empty($open_competitions) ) {
            return false;
        }

        // Read the competition attributes into a series of arrays
        $index = 0;
        $date_index = -1;
        $medium_index = -1;
        foreach ( $open_competitions as $recs ) {
            // Append this competition to the arrays
            $dateParts = explode(" ", $recs['Competition_Date']);
            $this->_open_comp_date[$index] = $dateParts[0];
            $this->_open_comp_medium[$index] = $recs['Medium'];
            $this->_open_comp_class[$index] = $recs['Classification'];
            $this->_open_comp_theme[$index] = $recs['Theme'];
            // If this is the first competition whose date matches the currently selected
            // competition date, save its array index
            if ( $this->_open_comp_date[$index] == $date ) {
                if ( $date_index < 0 ) {
                    $date_index = $index;
                }
                // If this competition matches the date AND the medium of the currently selected
                // competition, save its array index
                if ( $this->_open_comp_medium[$index] == $med ) {
                    if ( $medium_index < 0 ) {
                        $medium_index = $index;
                    }
                }
            }
            $index += 1;
        }

        // If date and medium both matched, then the currently selected competition is in the
        // list of open competitions for this member
        if ( $medium_index >= 0 ) {
            $index = $medium_index;

            // If the date matched but the medium did not, then there are valid open competitions on
            // the selected date for this member, but not in the currently selected medium. In this
            // case set the medium to the first one in the list for the selected date.
        } elseif ( $medium_index < 0 && $date_index >= 0 ) {
            $index = $date_index;

            // If neither the date or medium matched, simply select the first open competition in the
            // list.
        } else {
            $index = 0;
        }
        // Establish the (possibly adjusted) selected competition
        $this->_settings->comp_date = $this->_open_comp_date[$index];
        $this->_settings->classification = $this->_open_comp_class[$index];
        $this->_settings->medium = $this->_open_comp_medium[$index];
        // Save the currently selected competition in a cookie
        $hour = time() + ( 2 * 3600 );
        $url = parse_url(get_bloginfo('url'));
        setcookie("RPS_MyEntries", $this->_settings->comp_date . "|" . $this->_settings->classification . "|" . $this->_settings->medium, $hour, '/', $url['host']);
        return true;
    }
}
