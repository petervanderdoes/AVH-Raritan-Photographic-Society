<?php
namespace RpsCompetition\Frontend;

use RpsCompetition\Settings;
use RpsCompetition\Common\Core;
use RpsCompetition\Db\RpsDb;
use Avh\Html\HtmlBuilder;
use Avh\Html\FormBuilder;
use Illuminate\Http\Request;

final class Shortcodes extends \Avh\Utility\ShortcodesAbstract
{

    /**
     *
     * @var Core
     */
    private $core;

    /**
     *
     * @var Settings
     */
    private $settings;

    /**
     *
     * @var RpsDb
     */
    private $rpsdb;

    /**
     *
     * @var HtmlBuilder
     */
    private $html;

    /**
     *
     * @var Request
     */
    private $request;

    public function __construct(Settings $settings, RpsDb $rpsdb, Core $core, Request $request)
    {
        $this->core = $core;
        $this->settings = $settings;
        $this->rpsdb = $rpsdb;
        $this->rpsdb->setUserId(get_current_user_id());
        $this->html = new \Avh\Html\HtmlBuilder();
        $this->formBuilder = new FormBuilder($this->html);
        $this->request = $request;
    }

    /**
     * Display the given awards for the given classification.
     *
     * @param array $atts
     * @param string $content
     * @param string $tag
     */
    public function displayCategoryWinners($atts, $content, $tag)
    {
        global $wpdb;

        $class = 'Beginner';
        $award = '1';
        $date = '';
        extract($atts, EXTR_OVERWRITE);

        $competiton_date = date('Y-m-d H:i:s', strtotime($date));
        $award_map = array('1' => '1st', '2' => '2nd', '3' => '3rd', 'H' => 'HM');

        $entries = $this->rpsdb->getWinner($competiton_date, $award_map[$award], $class);

        echo '<section class="rps-showcase-category-winner">';
        echo '<div class="rps-sc-tile suf-tile-1c entry-content bottom">';

        echo '<div class="suf-gradient suf-tile-topmost">';
        echo '<h3>' . $class . '</h3>';
        echo '</div>';

        echo '<div class="gallery gallery-size-250">';
        echo '<ul class="gallery-row gallery-row-equal">';
        foreach ($entries as $entry) {
            $dateParts = explode(" ", $entry->Competition_Date);
            $comp_date = $dateParts[0];
            $medium = $entry->Medium;
            $classification = $entry->Classification;
            $comp = "$classification<br>$medium";
            $title = $entry->Title;
            $last_name = $entry->LastName;
            $first_name = $entry->FirstName;
            $award = $entry->Award;

            echo '<li class="gallery-item">';
            echo '	<div class="gallery-item-content">';
            echo '<div class="gallery-item-content-image">';
            echo '	<a href="' . $this->core->rpsGetThumbnailUrl($entry, 800) . '" rel="rps-showcase' . tag_escape($classification) . '" title="' . $title . ' by ' . $first_name . ' ' . $last_name . '">';
            echo '	<img class="thumb_img" src="' . $this->core->rpsGetThumbnailUrl($entry, 250) . '" /></a>' . "\n";

            $caption = "$title<br /><span class='wp-caption-credit'>Credit: $first_name $last_name";
            echo "<p class='wp-caption-text showcase-caption'>" . wptexturize($caption) . "</p>\n";
            echo '	</div></div>';
            echo '</li>' . "\n";
        }
        echo '</ul>';
        echo '</div>';
        echo '</section>';
    }

    public function displayMonthlyWinners($atts, $content, $tag)
    {
        global $post;
        $months = array();
        $themes = array();

        $this->settings->selected_season = '';
        $this->settings->season_start_date = "";
        $this->settings->season_end_date = "";
        $this->settings->season_start_year = "";
        $this->settings->selected_year = "";
        $this->settings->selected_month = "";

        if ($this->request->has('submit_control')) {
            $this->settings->selected_season = esc_attr($this->request->input('selected_season'));
            $this->settings->season_start_year = substr($this->settings->selected_season, 0, 4);
            $this->settings->selected_year = esc_attr($this->request->input('selected_year'));
            $this->settings->selected_month = esc_attr($this->request->input('selected_month'));

            switch ($this->request->input('submit_control')) {
                case 'new_season':
                    $this->settings->selected_season = esc_attr($this->request->input('new_season'));
                    $this->settings->season_start_year = substr($this->settings->selected_season, 0, 4);
                    $this->settings->selected_month = "";
                    break;
                case 'new_month':
                    $this->settings->selected_year = substr(esc_attr($this->request->input('new_month')), 0, 4);
                    $this->settings->selected_month = substr(esc_attr($this->request->input('new_month')), 5, 2);
            }
        }
        $seasons = $this->getSeasons();

        $scores = $this->rpsdb->getMonthlyScores();

        if (is_array($scores) && (!empty($scores))) {
            $scored_competitions = true;
        } else {
            $scored_competitions = false;
        }

        if ($scored_competitions) {
            foreach ($scores as $recs) {
                $key = sprintf("%d-%02s", $recs['Year'], $recs['Month_Num']);
                $months[$key] = $recs['Month'];
                $themes[$key] = $recs['Theme'];
            }

            if (empty($this->settings->selected_month)) {
                end($months);
                $this->settings->selected_year = substr(key($months), 0, 4);
                $this->settings->selected_month = substr(key($months), 5, 2);
            }
        }

        // Count the maximum number of awards in the selected competitions
        $this->settings->min_date = sprintf("%d-%02s-%02s", $this->settings->selected_year, $this->settings->selected_month, 1);
        if ($this->settings->selected_month == 12) {
            $this->settings->max_date = sprintf("%d-%02s-%02s", $this->settings->selected_year + 1, 1, 1);
        } else {
            $this->settings->max_date = sprintf("%d-%02s-%02s", $this->settings->selected_year, $this->settings->selected_month + 1, 1);
        }

        $max_num_awards = $this->rpsdb->getMaxAwards();

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
        $form .= '<input name="selected_season" type="hidden" value="' . $this->settings->selected_season . '">' . "\n";
        $form .= '<input name="selected_year" type="hidden" value="' . $this->settings->selected_year . '">' . "\n";
        $form .= '<input name="selected_month" type="hidden" value="' . $this->settings->selected_month . '">' . "\n";

        if ($scored_competitions) {
            // Drop down list for months
            $form .= '<select name="new_month" onchange="submit_form(\'new_month\')">' . "\n";
            foreach ($months as $key => $month) {
                $selected = (substr($key, 5, 2) == $this->settings->selected_month) ? " selected" : "";
                $form .= '<option value="' . $key . '"' . $selected . '>' . $month . '</option>' . "\n";
            }
            $form .= "</select>\n";
        }

        // Drop down list for season
        $form .= '<select name="new_season" onChange="submit_form(\'new_season\')">' . "\n";
        foreach ($seasons as $season) {
            $selected = ($season == $this->settings->selected_season) ? " selected" : "";
            $form .= '<option value="' . $season . '"' . $selected . '>' . $season . '</option>' . "\n";
        }
        $form .= '</select>' . "\n";
        $form .= '</form>';
        echo $form;
        unset($form);
        echo '</span>';

        if ($scored_competitions) {
            $this_month = sprintf("%d-%02s", $this->settings->selected_year, $this->settings->selected_month);
            echo '<h4 class="competition-theme">Theme is ' . $themes[$this_month] . '</h4>';

            echo "<table class=\"thumb_grid\">\n";
            // Output the column headings
            echo "<tr><th class='thumb_col_header' align='center'>Competition</th>\n";
            for ($i = 0; $i < $max_num_awards; $i++) {
                switch ($i) {
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
            $award_winners = $this->rpsdb->getWinners();
            // Iterate through all the award winners and display each thumbnail in a grid
            $row = 0;
            $column = 0;
            $comp = "";
            foreach ($award_winners as $recs) {

                // Remember the important values from the previous record
                $prev_comp = $comp;

                // Grab a new record from the database
                $dateParts = explode(" ", $recs->Competition_Date);
                $comp_date = $dateParts[0];
                $medium = $recs->Medium;
                $classification = $recs->Classification;
                $comp = "$classification<br>$medium";
                $title = $recs->Title;
                $last_name = $recs->LastName;
                $first_name = $recs->FirstName;
                $award = $recs->Award;

                // If we're at the end of a row, finish off the row and get ready for the next one
                if ($prev_comp != $comp) {
                    // As necessary, pad the row out with empty cells
                    if ($row > 0 && $column < $max_num_awards) {
                        for ($i = $column; $i < $max_num_awards; $i++) {
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
                echo "<td align=\"center\" class=\"thumb_cell\">\n";
                echo "  <div class=\"thumb_canvas\">\n";
                echo "    <a href=\"" . $this->core->rpsGetThumbnailUrl($recs, 400) . "\" rel=\"" . tag_escape($classification) . tag_escape($medium) . "\" title=\"($award) $title - $first_name $last_name\">\n";
                echo "    <img class=\"thumb_img\" src=\"" . $this->core->rpsGetThumbnailUrl($recs, 75) . "\" /></a>\n";
                echo "<div id='rps_colorbox_title'>$title<br />$first_name $last_name</div>";
                echo "  </div>\n</td>\n";
                $prev_comp = $comp;
                $column += 1;
            }
            // As necessary, pad the last row out with empty cells
            if ($row > 0 && $column < $max_num_awards) {
                for ($i = $column; $i < $max_num_awards; $i++) {
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

    public function displayAllScores($atts, $content, $tag)
    {
        global $post;
        if ($this->request->has('selected_season_list')) {
            $this->settings->selected_season = $this->request->input('selected_season_list');
        }
        $award_map = array('1st' => '1', '2nd' => '2', '3rd' => '3', 'HM' => 'H');

        $seasons = $this->rpsdb->getSeasonListOneEntry();
        arsort($seasons);
        if (!isset($this->settings->selected_season)) {
            $this->settings->selected_season = $seasons[count($seasons) - 1];
        }

        $this->settings->season_start_year = substr($this->settings->selected_season, 0, 4);
        $this->settings->season_start_date = sprintf("%d-%02s-%02s", $this->settings->season_start_year, 9, 1);
        $this->settings->season_end_date = sprintf("%d-%02s-%02s", $this->settings->season_start_year + 1, 9, 1);

        $competition_dates = $this->rpsdb->getClubCompetitionDates();
        // Build an array of competition dates in "MM/DD" format for column titles.
        // Also remember the max entries per member for each competition and the number
        // of judges for each competition.
        $total_max_entries = 0;
        foreach ($competition_dates as $key => $recs) {
            $comp_date = $recs['Competition_Date'];
            $date_parts = explode(" ", $comp_date);
            list ($comp_year, $comp_month, $comp_day) = explode("-", $date_parts[0]);
            $comp_dates[$date_parts[0]] = sprintf("%d/%d", $comp_month, $comp_day);
            $comp_max_entries[$date_parts[0]] = $recs['Max_Entries'];
            $total_max_entries += $recs['Max_Entries'];
            $comp_num_judges[$date_parts[0]] = $recs['Num_Judges'];
        }

        $club_competition_results_unsorted = $this->rpsdb->getClubCompetitionResults();
        $club_competition_results = $this->core->arrayMsort($club_competition_results_unsorted, array('Medium' => array(SORT_DESC), 'Class_Code' => array(SORT_ASC), 'LastName' => array(SORT_ASC), 'FirstName' => array(SORT_ASC), 'Competition_Date' => array(SORT_ASC)));
        // Bail out if no entries found
        if (empty($club_competition_results)) {
            echo 'No entries submitted';
        } else {

            // Start the big table

            $action = home_url('/' . get_page_uri($post->ID));
            $form = '';
            $form .= '<form name="all_scores_form" method="post" action="' . $action . '">';
            $form .= '<input type="hidden" name="selected_season" value="' . $this->settings->selected_season . '"/>';
            $form .= "&nbsp;<select name=\"selected_season_list\" onchange=\"submit_form()\">\n";
            foreach ($seasons as $this_season) {
                if ($this_season == $this->settings->selected_season) {
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
            foreach ($comp_dates as $key => $d) {
                $member_scores[$key] = array();
            }
            $total_score = 0;
            $num_scores = 0;

            $medium = '';
            $classification = '';
            $member = '';
            $last_name = '';
            $first_name = '';

            foreach ($club_competition_results as $key => $recs) {

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
                if ($member != $prev_member || $classification != $prev_class || $medium != $prev_medium) {
                    $rowCount += 1;
                    $rowStyle = $rowCount % 2 == 1 ? "odd_row" : "even_row";

                    // Don't do anything yet if this is the very first member, otherwise, output all
                    // the accumulated scored for the member we just passed.
                    if ($prev_member != "") {
                        // Display the members name and classification
                        echo "<tr>";
                        echo "<td align=\"left\" class=\"$rowStyle\">" . $prev_fname . " " . $prev_lname . "</td>\n";
                        echo "<td align=\"center\" class=\"$rowStyle\">" . substr($prev_class, 0, 1) . "</td>\n";

                        // Iterate through all the accumulated scores for this member
                        foreach ($member_scores as $key => $score_array) {
                            // Print the scores for the submitted entries for this month
                            for ($i = 0; $i < count($score_array); $i++) {
                                echo "<td align=\"center\" class=\"$rowStyle\">$score_array[$i]</td>\n";
                            }
                            // Pad the unused entries for this member for this month
                            for ($i = 0; $i < $comp_max_entries[$key] - count($score_array); $i++) {
                                echo "<td align=\"center\" class=\"$rowStyle\">&nbsp;</td>\n";
                            }
                        }

                        // Display the members annual average score
                        if ($total_score > 0 && $num_scores > 0) {
                            echo "<td align=\"center\" class=\"$rowStyle\">" . sprintf("%3.1f", $total_score / $num_scores) . "</td>\n";
                        } else {
                            echo "<td align=\"center\" class=\"$rowStyle\">&nbsp;</td>\n";
                        }
                        echo "</tr>";
                    }

                    // Now that we've just output the scores for the previous member, are we at the
                    // beginning of a new classification, but not at the end of the current medium?
                    // If so, draw a horizonal line to mark the beginning of a new classification
                    if ($classification != $prev_class && $medium == $prev_medium) {
                        // echo "<tr class=\"horizontal_separator\">";
                        echo "<tr>";
                        echo "<td colspan=\"" . ($total_max_entries + 3) . "\" class=\"horizontal_separator\"></td>";
                        echo "</tr>\n";
                        $prev_class = $classification;
                    }

                    // Are we at the beginning of a new medium?
                    // If so, output a new set of column headings
                    if ($medium != $prev_medium) {
                        // Draw a horizontal line to end the previous medium
                        if ($prev_medium != "") {
                            echo "<tr class=\"horizontal_separator\">";
                            // echo "<td colspan=\"" . (count($comp_dates) * 2 + 3) .
                            // "\" class=\"horizontal_separator\"></td>";
                            echo "<td colspan=\"" . ($total_max_entries + 3) . "\" class=\"horizontal_separator\"></td>";
                            echo "</tr>\n";
                        }

                        // Display the category title
                        echo '<tr><td align="left" class="form_title" colspan="' . ($total_max_entries + 3) . '">';
                        echo $medium . ' scores for ' . $this->settings->selected_season . ' season';
                        echo '</td></tr>' . "\n";

                        // Display the first row column headers
                        echo "<tr>\n<th class=\"form_frame_header\" colspan=\"2\">&nbsp;</th>\n";
                        foreach ($comp_dates as $key => $d) {
                            echo "<th class=\"form_frame_header\" colspan=\"" . $comp_max_entries[$key] . "\">$d</th>\n";
                        }
                        echo "<th class=\"form_frame_header\">&nbsp;</th>\n";
                        echo "</tr>\n";
                        // Display the second row column headers
                        echo "<tr>\n";
                        echo "<th class=\"form_frame_header\">Member</th>\n";
                        echo "<th class=\"form_frame_header\">Cl.</th>\n";
                        foreach ($comp_dates as $key => $d) {
                            for ($i = 1; $i <= $comp_max_entries[$key]; $i++) {
                                echo "<th class=\"form_frame_header\">$i</th>\n";
                            }
                        }
                        echo "<th class=\"form_frame_header\">Avg</th>\n";
                        echo "</tr>\n";
                    }

                    // Reset the score array to be ready to start accumulating the scores for this
                    // new member we just started.
                    $member_scores = array();
                    foreach ($comp_dates as $key => $d) {
                        $member_scores[$key] = array();
                    }
                    $total_score = 0;
                    $num_scores = 0;
                }

                // We're still working on the records for the current member
                // Accumulate this member's total score to calculcate the average at the end.
                if ($score > 0) {
                    $score = $score / $comp_num_judges[$this_date];
                    if ($score - floor($score) > 0) {
                        $score = round($score, 1);
                    }
                    if ($special_event == 'N') {
                        $total_score += $score;
                        $num_scores += 1;
                    }
                }
                // Apply the award as a superscript to the score
                if ($award != "") {
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
            foreach ($member_scores as $key => $score_array) {
                // Print the scores for the submitted entries for this month
                for ($i = 0; $i < count($score_array); $i++) {
                    echo "<td align=\"center\" class=\"$rowStyle\">$score_array[$i]</td>\n";
                }
                // Pad the unused entries for this member for this month
                for ($i = 0; $i < $comp_max_entries[$key] - count($score_array); $i++) {
                    echo "<td align=\"center\" class=\"$rowStyle\">&nbsp;</td>\n";
                }
            }

            // Display the members annual average score
            if ($total_score > 0 && $num_scores > 0) {
                echo "<td align=\"center\" class=\"$rowStyle\">" . sprintf("%3.1f", $total_score / $num_scores) . "</td>\n";
            } else {
                echo "<td align=\"center\" class=\"$rowStyle\">&nbsp;</td>\n";
            }
            echo "</tr>";

            // We're all done
            echo "</table>";
        }
    }

    public function displayScoresCurrentUser($atts, $content, $tag)
    {
        global $post;

        if ($this->request->has('selected_season_list')) {
            $this->settings->selected_season = $this->request->input('selected_season_list');
        }
        $seasons = $this->getSeasons();

        // Start building the form
        $action = home_url('/' . get_page_uri($post->ID));
        $form = '';
        $form .= '<form name="my_scores_form" method="post" action="' . $action . '">';
        $form .= '<input type="hidden" name="selected_season" value="' . $this->settings->selected_season . '" />';
        $form .= "&nbsp;<select name=\"selected_season_list\" onchange=\"submit_form()\">\n";
        foreach ($seasons as $this_season) {
            if ($this_season == $this->settings->selected_season) {
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
        $scores = $this->rpsdb->getScoresCurrentUser();

        // Bail out if not entries found
        if (empty($scores)) {
            echo "<tr><td colspan=\"6\">No entries submitted</td></tr>\n";
            echo "</table>\n";
        } else {

            // Build the list of submitted images
            $compCount = 0;
            $prev_date = "";
            $prev_medium = "";
            foreach ($scores as $recs) {
                $dateParts = explode(" ", $recs['Competition_Date']);
                $dateParts[0] = strftime('%d-%b-%Y', strtotime($dateParts[0]));
                $comp_date = $dateParts[0];
                $medium = $recs['Medium'];
                $theme = $recs['Theme'];
                $title = $recs['Title'];
                $score = $recs['Score'];
                $award = $recs['Award'];
                if ($dateParts[0] != $prev_date) {
                    $compCount += 1;
                    $rowStyle = $compCount % 2 == 1 ? "odd_row" : "even_row";
                    $prev_medium = "";
                }

                $a = realpath($recs['Server_File_Name']);
                $image_url = home_url($recs['Server_File_Name']);

                if ($prev_date == $dateParts[0]) {
                    $dateParts[0] = "";
                    $theme = "";
                } else {
                    $prev_date = $dateParts[0];
                }
                if ($prev_medium == $medium) {
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
                if ($score > "") {
                    $score_award = " / {$score}pts";
                }
                if ($award > "") {
                    $score_award .= " / $award";
                }
                echo "<td align=\"left\" valign=\"top\" class=\"$rowStyle\"><a href=\"$image_url\" rel=\"lightbox[{$comp_date}]\" title=\"" . htmlentities($title) . " / $comp_date / $medium{$score_award}\">" . htmlentities($title) . "</a></td>\n";
                echo "<td class=\"$rowStyle\" valign=\"top\" align=\"center\" width=\"8%\">$score</td>\n";
                echo "<td class=\"$rowStyle\" valign=\"top\" align=\"center\" width=\"8%\">$award</td></tr>\n";
            }
            echo "</table>";
        }
    }

    public function displayEditTitle($atts, $content, $tag)
    {
        global $post;
        if ($this->request->has('m')) {
            if ($this->request->input('m') == "prints") {
                $medium_subset = "Prints";
                $medium_param = "?m=prints";
            } else {
                $medium_subset = "Digital";
                $medium_param = "?m=digital";
            }
        }
        $entry_id = $this->request->input('id');

        $recs = $this->rpsdb->getEntryInfo($entry_id);
        $title = $recs->Title;
        $server_file_name = $recs->Server_File_Name;

        $relative_path = $server_file_name;

        if (isset($this->settings->errmsg)) {
            echo '<div id="errmsg">';
            echo $this->settings->errmsg;
            echo '</div>';
        }
        $action = home_url('/' . get_page_uri($post->ID));
        echo '<form action="' . $action . $medium_param . '" method="post">';

        echo '<table class="form_frame" width="80%">';
        echo '<tr><th class="form_frame_header" colspan=2>Update Image Title</th></tr>';
        echo '<tr><td align="center">';
        echo '<table>';
        echo '<tr><td align="center" colspan="2">';

        echo "<img src=\"" . $this->core->rpsGetThumbnailUrl($recs, 200) . "\" />\n";
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
        echo '<input type="hidden" name="wp_get_referer" value="' . remove_query_arg(array('m', 'id'), wp_get_referer()) . '" />';
        echo '</td></tr>';
        echo '</table>';
        echo '</td></tr>';
        echo '</table>';
        echo '</form>';
    }

    public function displayMyEntries($atts, $content, $tag)
    {
        global $post;

        // Default values
        $medium = 'digital';

        extract($atts, EXTR_OVERWRITE);
        $this->settings->medium_subset = $medium;

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

        if (!empty($this->settings->errmsg)) {
            echo '<div id="errmsg">' . $this->settings->errmsg . '</div>';
        }
        // Start the form
        $action = home_url('/' . get_page_uri($post->ID));
        $form = '';
        echo '<form name="MyEntries" action=' . $action . ' method="post">' . "\n";
        echo '<input type="hidden" name="submit_control">' . "\n";
        echo '<input type="hidden" name="comp_date" value="' . $this->settings->comp_date . '">' . "\n";
        echo '<input type="hidden" name="classification" value="' . $this->settings->classification . '">' . "\n";
        echo '<input type="hidden" name="medium" value="' . $this->settings->medium . '">' . "\n";
        echo '<input type="hidden" name="medium_subset" value="' . $this->settings->medium_subset . '">' . "\n";
        echo '<input type="hidden" name="_wpnonce" value="' . wp_create_nonce('avh-rps-myentries') . '" />' . "\n";
        echo '<table class="form_frame" width="90%">' . "\n";

        // Form Heading
        if ($this->settings->validComp) {
            echo "<tr><th colspan=\"6\" align=\"center\" class=\"form_frame_header\">My Entries for " . $this->settings->medium . " on " . strftime('%d-%b-%Y', strtotime($this->settings->comp_date)) . "</th></tr>\n";
        } else {
            echo "<tr><th colspan=\"6\" align=\"center\" class=\"form_frame_header\">Make a selection</th></tr>\n";
        }
        echo "<tr><td align=\"center\" colspan=\"6\">\n";
        echo "<table width=\"100%\">\n";
        $theme_uri_images = get_stylesheet_directory_uri() . '/images';
        echo '<tr>';
        echo '<td width="25%">';
        // echo '<span class="rps-comp-medium">' . $this->settings->medium . '</span>';
        switch ($this->settings->medium) {
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

        echo '<img src="' . plugins_url('/images' . $img, $this->settings->plugin_basename) . '">';
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
        $compdates = array_unique($this->settings->open_comp_date);
        foreach ($compdates as $key => $comp_date) {
            $selected = '';
            if ($this->settings->comp_date == $this->settings->open_comp_date[$key]) {
                $selected = " SELECTED";
                $theme = $this->settings->open_comp_theme[$key];
            }
            echo "<OPTION value=\"" . $comp_date . "\"$selected>" . strftime('%d-%b-%Y', strtotime($comp_date)) . " " . $this->settings->open_comp_theme[$key] . "</OPTION>\n";
        }
        echo "</SELECT>\n";
        echo "</td></tr>\n";

        // Competition medium dropdown list
        echo "<tr>\n<td width=\"33%\" align=\"right\"><b>Competition:&nbsp;&nbsp;</b></td>\n";
        echo "<td width=\"64%\" align=\"left\">\n";
        echo "<SELECT name=\"select_medium\" onchange=\"submit_form('select_medium')\">\n";

        // Load the values into the dropdown list
        $medium_array = array_keys($this->settings->open_comp_date, $this->settings->comp_date);
        foreach ($medium_array as $comp_medium) {
            $selected = '';
            if ($this->settings->medium == $this->settings->open_comp_medium[$comp_medium]) {
                $selected = " SELECTED";
            }
            echo "<OPTION value=\"" . $this->settings->open_comp_medium[$comp_medium] . "\"$selected>" . $this->settings->open_comp_medium[$comp_medium] . "</OPTION>\n";
        }
        echo "</SELECT>\n";
        echo "</td></tr>\n";

        // Display the Classification and Theme for the selected competition
        echo "<tr><td width=\"33%\" align=\"right\"><b>Classification:&nbsp;&nbsp;<b></td>\n";
        echo "<td width=\"64%\" align=\"left\">" . $this->settings->classification . "</td></tr>\n";
        echo "<tr><td width=\"33%\" align=\"right\"><b>Theme:&nbsp;&nbsp;<b></td>\n";
        echo "<td width=\"64%\" align=\"left\">$theme</td></tr>\n";

        echo "</table>\n";
        echo "</td></tr></table>\n";

        // Display a warning message if the competition is within one week aka 604800 secs (60*60*24*7) of closing
        if ($this->settings->comp_date != "") {
            $close_date = $this->rpsdb->getCompetitionCloseDate();
            if (!empty($close_date)) {
                $close_epoch = strtotime($close_date);
                $time_to_close = $close_epoch - current_time('timestamp');
                if ($time_to_close >= 0 && $time_to_close <= 604800) {
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
        $max_entries_per_member_per_comp = $this->rpsdb->getCompetitionMaxEntries();

        // Retrive the total number of entries submitted by this member for this competition date
        $total_entries_submitted = $this->rpsdb->getCompetitionEntriesUser();

        $entries = $this->rpsdb->getCompetitionSubmittedEntriesUser();
        // Build the rows of submitted images
        $numRows = 0;
        $numOversize = 0;
        foreach ($entries as $recs) {
            $numRows += 1;
            $rowStyle = $numRows % 2 == 1 ? "odd_row" : "even_row";

            // Checkbox column
            echo '<tr class="' . $rowStyle . '"><td align="center" width="5%"><input type="checkbox" name="EntryID[]" value="' . $recs->ID . '">' . "\n";

            // Thumbnail column
            $user = wp_get_current_user();
            $a = realpath($recs->Server_File_Name);
            $image_url = home_url($recs->Server_File_Name);
            echo "<td align=\"center\" width=\"10%\">\n";
            // echo "<div id='rps_colorbox_title'>" . htmlentities($recs->Title) . "<br />" . $this->settings->classification . " " . $this->settings->medium . "</div>";
            echo '<a href="' . $image_url . '" rel="' . $this->settings->comp_date . '" title="' . $recs->Title . ' ' . $this->settings->classification . ' ' . $this->settings->medium . '">' . "\n";
            echo "<img src=\"" . $this->core->rpsGetThumbnailUrl($recs, 75) . "\" />\n";
            echo "</a></td>\n";

            // Title column
            echo '<td align="left" width="40%">';
            // echo "<div id='rps_colorbox_title'>" . htmlentities($recs->Title) . "<br />" . $this->settings->classification . " " . $this->settings->medium . "</div>";
            echo htmlentities($recs->Title) . "</td>\n";
            // File Name
            echo '<td align="left" width="25%">' . $recs->Client_File_Name . "</td>\n";

            // Image width and height columns. The height and width values are suppressed if the Client_File_Name is
            // empty i.e. no image uploaded for a print competition.
            if (file_exists($this->request->server('DOCUMENT_ROOT') . $recs->Server_File_Name)) {
                $size = getimagesize($this->request->server('DOCUMENT_ROOT') . $recs->Server_File_Name);
            } else {
                $size[0] = 0;
                $size[1] = 0;
            }
            if ($recs->Client_File_Name > "") {
                if ($size[0] > 1024) {
                    echo '<td align="center" style="color:red; font-weight:bold" width="10%">' . $size[0] . "</td>\n";
                } else {
                    echo '<td align="center" style="text-align:center" width="10%">' . $size[0] . "</td>\n";
                }
                if ($size[1] > 768) {
                    echo '<td align="center" style="color:red; font-weight:bold" width="10%">' . $size[1] . "</td>\n";
                } else {
                    echo '<td align="center" width="10%">' . $size[1] . "</td>\n";
                }
            } else {
                echo "<td align=\"center\" width=\"10%\">&nbsp;</td>\n";
                echo "<td align=\"center\" width=\"10%\">&nbsp;</td>\n";
            }
            if ($size[0] > 1024 || $size[1] > 768) {
                $numOversize += 1;
            }
        }

        // Add some instructional bullet points above the buttons
        echo "<tr><td align=\"left\" style=\"padding-top: 5px;\" colspan=\"6\">";
        echo "<ul style=\"margin:0;margin-left:15px;padding:0\">\n";
        if ($numRows > 0) {
            echo "<li>Click the thumbnail or title to view the full size image</li>\n";
        }
        echo "<ul></td></tr>\n";

        // Warn the user about oversized images.
        if ($numOversize > 0) {
            echo "<tr><td align=\"left\" style=\"padding-top: 5px;\" colspan=\"6\" class=\"warning_cell\">";
            echo "<ul style=\"margin:0;margin-left:15px;padding:0;color:red\"><li>When the Width or Height value is red, the image is too large to display on the projector. &nbsp;Here's what you need to do:\n";
            echo "<ul style=\"margin:0;margin-left:15px;padding:0\"><li>Remove the image from the competition. (check the corresponding checkbox and click Remove)</li>\n";
            echo "<li>Resize the image. &nbsp;Click <a href=\"/digital/Resize Digital Images.shtml\">here</a> for instructions.</li>\n";
            echo "<li>Upload the resized image.</li></ul></ul>\n";
        }
        if ($this->request->has('resized') && ('1' == $this->request->input('resized'))) {
            echo "<tr><td align=\"left\" colspan=\"6\" class=\"warning_cell\">";
            echo "<ul><li><b>Note</b>: The web site automatically resized your image to match the digital projector.\n";
            echo "</li></ul>\n";
        }

        // Buttons at the bottom of the list of submitted images
        echo "<tr><td align=\"center\" style=\"padding-top: 10px; text-align:center\" colspan=\"6\">\n";
        // Don't show the Add button if the max number of images per member reached
        if ($numRows < $max_entries_per_member_per_comp && $total_entries_submitted < $this->settings->club_max_entries_per_member_per_date) {
            echo "<input type=\"submit\" name=\"submit[add]\" value=\"Add\" onclick=\"submit_form('add')\">&nbsp;\n";
        }
        if ($numRows > 0 && $max_entries_per_member_per_comp > 0) {
            echo "<input type=\"submit\" name=\"submit[edit_title]\" value=\"Change Title\"  onclick=\"submit_form('edit')\">" . "&nbsp;\n";
        }
        if ($numRows > 0) {
            echo '<input type="submit" name="submit[delete]" value="Remove" onclick="return  confirmSubmit()"></td></tr>' . "\n";
        }

        // All done, close out the table and the form
        echo "</table>\n</form>\n<br />\n";
    }

    public function displayUploadEntry($atts, $content, $tag)
    {
        global $post;
        if ($this->request->has('m')) {
            if ($this->request->input('m') == "prints") {
                $medium_subset = "Prints";
                $medium_param = "?m=prints";
            } else {
                $medium_subset = "Digital";
                $medium_param = "?m=digital";
            }
        }

        // Error messages
        if (isset($this->settings->errmsg)) {
            echo '<div id="errmsg">';
            echo $this->settings->errmsg;
            echo '</div>';
        }

        $action = home_url('/' . get_page_uri($post->ID));
        echo '<form action="' . $action . '/?post=1" enctype="multipart/form-data" method="post">';

        if ($this->request->has('m')) {
            echo '<input type="hidden" name="medium_subset" value="' . $medium_subset . '" />';
        }
        if ($this->request->has('wp_get_referer')) {
            $_ref = $this->request->input('wp_get_referer');
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

    public function displayEmail($atts, $content, $tag)
    {
        $email = $atts['email'];
        unset($atts['email']);
        echo $this->html->mailto($email, $content, $atts);
    }

    /**
     * Display the eights and higher for a given member ID.
     *
     * @param array $atts
     * @param string $content
     * @param string $tag
     */
    public function displayPersonWinners($atts, $content, $tag)
    {
        global $wpdb;

        $id = 0;
        extract($atts, EXTR_OVERWRITE);

        echo '<section class="rps-showcases">';

        echo '<div class="rps-sc-text entry-content">';
        echo '<ul>';
        $entries = $this->rpsdb->getEightsAndHigherPerson($id);
        $images = array_rand($entries, 3);

        foreach ($images as $key) {
            $recs = $entries[$key];
            $user_info = get_userdata($recs->Member_ID);
            $recs->FirstName = $user_info->user_firstname;
            $recs->LastName = $user_info->user_lastname;
            $recs->Username = $user_info->user_login;

            // Grab a new record from the database
            $dateParts = explode(" ", $recs->Competition_Date);
            $comp_date = $dateParts[0];
            $medium = $recs->Medium;
            $classification = $recs->Classification;
            $comp = "$classification<br>$medium";
            $title = $recs->Title;
            $last_name = $recs->LastName;
            $first_name = $recs->FirstName;
            $award = $recs->Award;
            // Display this thumbnail in the the next available column
            echo '<li>';
            echo '<div>';
            echo '	<div class="image">';
            echo '	<a href="' . $this->core->rpsGetThumbnailUrl($recs, 800) . '" rel="rps-showcase" title="' . $title . ' by ' . $first_name . ' ' . $last_name . '">';
            echo '	<img class="thumb_img" src="' . $this->core->rpsGetThumbnailUrl($recs, 150) . '" /></a>';
            echo '	</div>';
            echo "</div>\n";

            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '</section>';
    }

    /**
     * Get the seasons list
     *
     * @return Ambigous <multitype:, NULL>
     */
    private function getSeasons()
    {
        $seasons = $this->rpsdb->getSeasonList();
        if (empty($this->settings->selected_season)) {
            $this->settings->selected_season = $seasons[count($seasons) - 1];
        }
        $this->settings->season_start_year = substr($this->settings->selected_season, 0, 4);
        $this->settings->season_start_date = sprintf("%d-%02s-%02s", $this->settings->season_start_year, $this->settings->club_season_start_month_num, 1);
        $this->settings->season_end_date = sprintf("%d-%02s-%02s", $this->settings->season_start_year + 1, $this->settings->club_season_start_month_num, 1);
        return $seasons;
    }
}
