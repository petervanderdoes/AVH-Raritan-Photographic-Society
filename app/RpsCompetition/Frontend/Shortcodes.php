<?php
namespace RpsCompetition\Frontend;

use Avh\Html\FormBuilder;
use Avh\Html\HtmlBuilder;
use Avh\Network\Session;
use Avh\Utility\ShortcodesAbstract;
use Illuminate\Http\Request;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Competition\Helper as CompetitionHelper;
use RpsCompetition\Db\QueryBanquet;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Photo\Helper as PhotoHelper;
use RpsCompetition\Season\Helper as SeasonHelper;
use RpsCompetition\Settings;

final class Shortcodes extends ShortcodesAbstract
{
    private $html;
    private $request;
    private $rpsdb;
    private $settings;

    /**
     * @param Settings $settings
     * @param RpsDb    $rpsdb
     * @param Request  $request
     */
    public function __construct(Settings $settings, RpsDb $rpsdb, Request $request)
    {
        $this->settings = $settings;
        $this->rpsdb = $rpsdb;
        $this->html = new HtmlBuilder();
        $this->formBuilder = new FormBuilder($this->html);
        $this->request = $request;
    }

    /**
     * Display all scores.
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     */
    public function displayAllScores($attr, $content, $tag)
    {
        global $post;

        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
        $query_miscellaneous = new QueryMiscellaneous($this->rpsdb);
        $season_helper = new SeasonHelper($this->settings, $this->rpsdb);
        $seasons = $season_helper->getSeasons();
        $selected_season = esc_attr($this->request->input('new_season', end($seasons)));

        $award_map = array('1st' => '1', '2nd' => '2', '3rd' => '3', 'HM' => 'H');

        list ($season_start_date, $season_end_date) = $season_helper->getSeasonStartEnd($selected_season);

        $competition_dates = $query_competitions->getCompetitionDates($season_start_date, $season_end_date);
        // Build an array of competition dates in "MM/DD" format for column titles.
        // Also remember the max entries per member for each competition and the number
        // of judges for each competition.
        $total_max_entries = 0;
        $comp_dates = array();
        $comp_max_entries = array();
        $comp_num_judges = array();
        foreach ($competition_dates as $key => $recs) {
            $date_parts = explode(" ", $recs['Competition_Date']);
            list ($comp_year, $comp_month, $comp_day) = explode("-", $date_parts[0]);
            $comp_dates[$date_parts[0]] = sprintf("%d/%d", $comp_month, $comp_day);
            $comp_max_entries[$date_parts[0]] = $recs['Max_Entries'];
            $total_max_entries += $recs['Max_Entries'];
            $comp_num_judges[$date_parts[0]] = $recs['Num_Judges'];
        }

        $club_competition_results_unsorted = $query_miscellaneous->getCompetitionResultByDate($season_start_date, $season_end_date);
        $club_competition_results = CommonHelper::arrayMsort(
            $club_competition_results_unsorted,
            array(
                'Medium'           => array(SORT_DESC),
                'Class_Code'       => array(SORT_ASC),
                'LastName'         => array(SORT_ASC),
                'FirstName'        => array(SORT_ASC),
                'Competition_Date' => array(SORT_ASC)
            )
        );
        // Bail out if no entries found
        if (empty($club_competition_results)) {
            echo 'No entries submitted';
        } else {

            // Start the big table

            $action = home_url('/' . get_page_uri($post->ID));
            $form = '';
            $form .= '<form name="all_scores_form" method="post" action="' . $action . '">';
            $form .= '<input type="hidden" name="selected_season" value="' . $selected_season . '"/>';
            // Drop down list for season
            $form .= $season_helper->getSeasonDropdown($selected_season);
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
            $row_count = 0;
            // Initialize the 2D array to hold the members scores for each month
            // Each row represents a competition month and each column holds the scores
            // of the submitted images for that month
            $member_scores = array();
            $comp_dates_keys = array_keys($comp_dates);
            foreach ($comp_dates_keys as $key) {
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
                    $row_count += 1;
                    $row_style = $row_count % 2 == 1 ? "odd_row" : "even_row";

                    // Don't do anything yet if this is the very first member, otherwise, output all
                    // the accumulated scored for the member we just passed.
                    if ($prev_member != "") {
                        // Display the members name and classification
                        echo "<tr>";
                        echo "<td align=\"left\" class=\"$row_style\">" . $prev_fname . " " . $prev_lname . "</td>\n";
                        echo "<td align=\"center\" class=\"$row_style\">" . substr($prev_class, 0, 1) . "</td>\n";

                        // Iterate through all the accumulated scores for this member
                        foreach ($member_scores as $score_key => $score_array) {
                            // Print the scores for the submitted entries for this month
                            $total_score_array = count($score_array);
                            for ($i = 0; $i < $total_score_array; $i++) {
                                echo "<td align=\"center\" class=\"$row_style\">$score_array[$i]</td>\n";
                            }
                            // Pad the unused entries for this member for this month
                            for ($i = 0; $i < $comp_max_entries[$score_key] - $total_score_array; $i++) {
                                echo "<td align=\"center\" class=\"$row_style\">&nbsp;</td>\n";
                            }
                        }

                        // Display the members annual average score
                        if ($total_score > 0 && $num_scores > 0) {
                            echo "<td align=\"center\" class=\"$row_style\">" . sprintf("%3.1f", $total_score / $num_scores) . "</td>\n";
                        } else {
                            echo "<td align=\"center\" class=\"$row_style\">&nbsp;</td>\n";
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
                        echo $medium . ' scores for ' . $selected_season . ' season';
                        echo '</td></tr>' . "\n";

                        // Display the first row column headers
                        echo "<tr>\n<th class=\"form_frame_header\" colspan=\"2\">&nbsp;</th>\n";
                        foreach ($comp_dates as $comp_dates_key => $comp_dates_date) {
                            echo "<th class=\"form_frame_header\" colspan=\"" . $comp_max_entries[$comp_dates_key] . "\">$comp_dates_date</th>\n";
                        }
                        echo "<th class=\"form_frame_header\">&nbsp;</th>\n";
                        echo "</tr>\n";
                        // Display the second row column headers
                        echo "<tr>\n";
                        echo "<th class=\"form_frame_header\">Member</th>\n";
                        echo "<th class=\"form_frame_header\">Cl.</th>\n";
                        foreach ($comp_dates as $comp_dates_key => $comp_dates_date) {
                            for ($i = 1; $i <= $comp_max_entries[$comp_dates_key]; $i++) {
                                echo "<th class=\"form_frame_header\">$i</th>\n";
                            }
                        }
                        echo "<th class=\"form_frame_header\">Avg</th>\n";
                        echo "</tr>\n";
                    }

                    // Reset the score array to be ready to start accumulating the scores for this
                    // new member we just started.
                    $member_scores = array();
                    foreach ($comp_dates as $comp_dates_key => $comp_dates_date) {
                        $member_scores[$comp_dates_key] = array();
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
            $row_count += 1;
            $row_style = $row_count % 2 == 1 ? "odd_row" : "even_row";
            // Display the members name and classification
            echo "<tr>";
            echo "<td align=\"left\" class=\"$row_style\">" . $first_name . " " . $last_name . "</td>\n";
            echo "<td align=\"center\" class=\"$row_style\">" . substr($classification, 0, 1) . "</td>\n";
            // Iterate through all the accumulated scores for this member
            foreach ($member_scores as $key => $score_array) {
                // Print the scores for the submitted entries for this month
                $total_score_array = count($score_array);
                for ($i = 0; $i < $total_score_array; $i++) {
                    echo "<td align=\"center\" class=\"$row_style\">$score_array[$i]</td>\n";
                }
                // Pad the unused entries for this member for this month
                for ($i = 0; $i < $comp_max_entries[$key] - $total_score_array; $i++) {
                    echo "<td align=\"center\" class=\"$row_style\">&nbsp;</td>\n";
                }
            }

            // Display the members annual average score
            if ($total_score > 0 && $num_scores > 0) {
                echo "<td align=\"center\" class=\"$row_style\">" . sprintf("%3.1f", $total_score / $num_scores) . "</td>\n";
            } else {
                echo "<td align=\"center\" class=\"$row_style\">&nbsp;</td>\n";
            }
            echo "</tr>";

            // We're all done
            echo "</table>";
        }
        unset($query_competitions, $query_miscellaneous, $season_helper);
    }

    /**
     * Display the possible Banquet entries for the current user.
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @see Frontend::actionHandleHttpPostRpsBanquetEntries
     */
    public function displayBanquetCurrentUser($attr, $content, $tag)
    {
        global $post;

        $query_miscellaneous = new QueryMiscellaneous($this->rpsdb);
        $query_entries = new QueryEntries($this->rpsdb);
        $query_banquet = new QueryBanquet($this->rpsdb);
        $season_helper = new SeasonHelper($this->settings, $this->rpsdb);
        $seasons = $season_helper->getSeasons();
        $selected_season = end($seasons);

        if ($this->request->isMethod('POST')) {
            $selected_season = esc_attr($this->request->input('new_season', $selected_season));
        }

        list ($season_start_date, $season_end_date) = $season_helper->getSeasonStartEnd($selected_season);
        $scores = $query_miscellaneous->getScoresUser(get_current_user_id(), $season_start_date, $season_end_date);
        $banquet_id = $query_banquet->getBanquets($season_start_date, $season_end_date);
        $banquet_id_string = '0';
        $banquet_id_array = array();
        $disabled = '';
        $banquet_entries = array();
        if (is_array($banquet_id) && !empty($banquet_id)) {
            foreach ($banquet_id as $record) {
                $banquet_id_array[] = $record['ID'];
                if ($record['Closed'] == 'Y') {
                    $disabled = 'disabled="1"';
                }
            }

            $banquet_id_string = implode(',', $banquet_id_array);
            $where = 'Competition_ID in (' . $banquet_id_string . ') AND Member_ID = "' . get_current_user_id() . '"';
            $banquet_entries = $query_entries->query(array('where' => $where));
        }

        if (!is_array($banquet_entries)) {
            $banquet_entries = array();
        }
        $all_entries = array();
        foreach ($banquet_entries as $banquet_entry) {
            $all_entries[] = $banquet_entry->ID;
        }

        // Start building the form
        $action = home_url('/' . get_page_uri($post->ID));
        $form = '';
        $form .= '<form name="banquet_form" method="post" action="' . $action . '">';
        // Drop down list for season
        $form .= $season_helper->getSeasonDropdown($selected_season);
        $form .= '<input type="hidden" name="selected_season" value="' . $selected_season . '" />';
        $form .= '<input name="submit_control" type="hidden">' . "\n";
        $form .= "</form>";
        echo '<script type="text/javascript">' . "\n";
        echo 'function submit_form(control_name) {' . "\n";
        echo '	document.banquet_form.submit();' . "\n";
        echo '}' . "\n";
        echo '</script>' . "\n";
        echo "My banquet entries for ";
        echo $form;

        echo '<p>Select up to 5 entries</p>';
        echo '<form name="BanquetEntries" action=' . $action . ' method="post">' . "\n";
        echo '<table class="banquet form_frame" width="99%">';
        echo '<tr>';
        echo '<th>Banquet Entry</th>';
        echo '<th width="12%">Date</th>';
        echo '<th>Theme</th>';
        echo '<th>Competition</th>';
        echo '<th>Title</th>';
        echo '<th width="8%">Score</th>';
        echo '<th width="8%">Award</th>';
        echo '<th width="3%"></th>';
        echo '</tr>';

        // Bail out if not entries found
        if (empty($scores)) {
            echo "<tr><td colspan=\"6\">No eligible banquet entries</td></tr>\n";
            echo "</table>\n";
            echo '</form>';
        } else {

            // Build the list of submitted images
            $comp_count = 0;
            $prev_date = "";
            $prev_medium = "";
            $row_style = 'odd_row';
            foreach ($scores as $recs) {
                if (empty($recs['Award'])) {
                    continue;
                }

                $date_parts = explode(" ", $recs['Competition_Date']);
                $date_parts[0] = strftime('%d-%b-%Y', strtotime($date_parts[0]));
                $comp_date = $date_parts[0];
                $medium = $recs['Medium'];
                $theme = $recs['Theme'];
                $title = $recs['Title'];
                $score = $recs['Score'];
                $award = $recs['Award'];
                if ($date_parts[0] != $prev_date) {
                    $comp_count += 1;
                    $row_style = $comp_count % 2 == 1 ? "odd_row" : "even_row";
                    $prev_medium = "";
                }

                $image_url = home_url($recs['Server_File_Name']);

                if ($prev_date == $date_parts[0]) {
                    $date_parts[0] = "";
                    $theme = "";
                } else {
                    $prev_date = $date_parts[0];
                }
                if ($prev_medium == $medium) {
                    // $medium = "";
                    $theme = "";
                } else {
                    $prev_medium = $medium;
                }
                $score_award = "";
                if ($score > "") {
                    $score_award = " / {$score}pts";
                }
                if ($award > "") {
                    $score_award .= " / $award";
                }

                echo "<tr>";
                echo "<td align=\"center\" valign=\"middle\" class=\"$row_style\" width=\"3%\">";
                $checked = '';
                foreach ($banquet_entries as $banquet_entry) {

                    if (!empty($banquet_entry) && $banquet_entry->Title == $title) {
                        $checked = 'checked="checked"';
                        break;
                    }
                }

                $entry_id = $recs['Entry_ID'];
                echo "<input type=\"checkbox\" name=\"entry_id[]\" value=\"$entry_id\" {$checked} {$disabled}/>";
                echo '</td>';
                echo "<td align=\"left\" valign=\"top\" class=\"{$row_style}\" width=\"12%\">" . $date_parts[0] . "</td>\n";
                echo "<td align=\"left\" valign=\"top\" class=\"{$row_style}\">{$theme}</td>\n";
                echo "<td align=\"left\" valign=\"top\" class=\"{$row_style}\">{$medium}</td>\n";
                // echo "<td align=\"left\" valign=\"top\" class=\"$row_style\"><a href=\"$image_url\" target=\"_blank\">$title</a></td>\n";

                echo "<td align=\"left\" valign=\"top\" class=\"{$row_style}\"><a href=\"$image_url\" rel=\"lightbox[{$comp_date}]\" title=\"" . htmlentities($title) . " / {$comp_date} / {$medium}{$score_award}\">" . htmlentities(
                        $title
                    ) . "</a></td>\n";
                echo "<td class=\"$row_style\" valign=\"top\" align=\"center\" width=\"8%\">$score</td>\n";
                echo "<td class=\"$row_style\" valign=\"top\" align=\"center\" width=\"8%\">$award</td>";
                echo "<td align=\"center\" valign=\"middle\" class=\"$row_style\" width=\"3%\">";

                echo "</tr>\n";
            }
            echo "</table>";
            if (empty($disabled)) {
                echo '<input type="submit" name="submit" value="Update">';
                echo '<input type="submit" name="cancel" value="Cancel">';
            }
            echo '<input type="hidden" name="wp_get_referer" value="' . remove_query_arg(array('m', 'id'), wp_get_referer()) . '" />';
            echo '<input type="hidden" name="allentries" value="', implode(',', $all_entries) . '" />';
            echo '<input type="hidden" name="banquetids" value="' . $banquet_id_string . '" />';
            echo '</form>';
            echo '<script type="text/javascript">' . "\n";
            echo "jQuery('.banquet :checkbox').change(function () {\n";
            echo "    var cs=jQuery(this).closest('.banquet').find(':checkbox:checked');\n";
            echo "    if (cs.length > 5) {\n";
            echo "        this.checked=false;\n";
            echo "    }\n";
            echo "});\n";
            echo '</script>';
        }
        unset($query_miscellaneous, $query_banquet, $query_entries, $season_helper);
    }

    /**
     * Display the given awards for the given classification on a given date.
     *
     * @param array  $attr    The shortcode argument list. Allowed arguments:
     *                        - class
     *                        - award
     *                        - date
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     */
    public function displayCategoryWinners($attr, $content, $tag)
    {
        $query_miscellaneous = new QueryMiscellaneous($this->rpsdb);
        $photo_helper = new PhotoHelper($this->settings, $this->request, $this->rpsdb);

        $class = 'Beginner';
        $award = '1';
        $date = '';
        extract($attr, EXTR_OVERWRITE);

        $competition_date = date('Y-m-d H:i:s', strtotime($date));
        $award_map = array('1' => '1st', '2' => '2nd', '3' => '3rd', 'H' => 'HM');

        $entries = $query_miscellaneous->getWinner($competition_date, $award_map[$award], $class);

        /**
         * Check if we ran the filter filterWpseoPreAnalysisPostsContent.
         *
         * @see Frontend::filterWpseoPreAnalysisPostsContent
         */
        $didFilterWpseoPreAnalysisPostsContent = $this->settings->get('didFilterWpseoPreAnalysisPostsContent', false);

        if (is_array($entries)) {
            if (!$didFilterWpseoPreAnalysisPostsContent) {
                $this->displayCategoryWinnersFacebookThumbs($entries);
                unset($query_miscellaneous, $photo_helper);

                return;
            }

            echo '<section class="rps-showcase-category-winner">';
            echo '<div class="rps-sc-tile suf-tile-1c entry-content bottom">';

            echo '<div class="suf-gradient suf-tile-topmost">';
            echo '<h3>' . $class . '</h3>';
            echo '</div>';

            echo '<div class="gallery gallery-size-250">';
            echo '<ul class="gallery-row gallery-row-equal">';
            foreach ($entries as $entry) {
                $user_info = get_userdata($entry->Member_ID);

                echo '<li class="gallery-item">';
                echo '	<div class="gallery-item-content">';
                echo '<div class="gallery-item-content-image">';
                echo '	<a href="' . $photo_helper->rpsGetThumbnailUrl($entry, 800) . '" rel="rps-showcase' . tag_escape(
                        $entry->Classification
                    ) . '" title="' . $entry->Title . ' by ' . $user_info->user_firstname . ' ' . $user_info->user_lastname . '">';
                echo '	<img class="thumb_img" src="' . $photo_helper->rpsGetThumbnailUrl($entry, 250) . '" /></a>' . "\n";

                $caption = $entry->Title . "<br /><span class='wp-caption-credit'>Credit: $user_info->user_firstname $user_info->user_lastname";
                echo "<p class='wp-caption-text showcase-caption'>" . wptexturize($caption) . "</p>\n";
                echo '	</div></div>';
                echo '</li>' . "\n";
            }
            echo '</ul>';
            echo '</div>';
            echo '</section>';
        }
        unset($query_miscellaneous, $photo_helper);
    }

    /**
     * Display the form to edit the title of the selected entry
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @see Frontend::actionHandleHttpPostRpsEditTitle
     */
    public function displayEditTitle($attr, $content, $tag)
    {
        global $post;
        $query_entries = new QueryEntries($this->rpsdb);
        $photo_helper = new PhotoHelper($this->settings, $this->request, $this->rpsdb);

        $medium_subset = "Digital";
        $medium_param = "?m=digital";
        if ($this->request->input('m') == "prints") {
            $medium_subset = "Prints";
            $medium_param = "?m=prints";
        }
        $entry_id = $this->request->input('id');

        $recs = $query_entries->getEntryById($entry_id);
        // Legacy need. Previously titles would be stores with added slashes.
        $title = $recs->Title;
        $server_file_name = $recs->Server_File_Name;

        if ($this->settings->has('errmsg')) {
            echo '<div id="errmsg">';
            echo $this->settings->get('errmsg');
            echo '</div>';
        }
        $action = home_url('/' . get_page_uri($post->ID));
        echo '<form action="' . $action . $medium_param . '" method="post">';

        echo '<table class="form_frame" width="80%">';
        echo '<tr><th class="form_frame_header" colspan=2>Update Image Title</th></tr>';
        echo '<tr><td align="center">';
        echo '<table>';
        echo '<tr><td align="center" colspan="2">';

        echo "<img src=\"" . $photo_helper->rpsGetThumbnailUrl($recs, 200) . "\" />\n";
        echo '</td></tr>';
        echo '<tr><td align="center" class="form_field_label">Title:</td><td class="form_field">';
        echo '<input style="width:300px" type="text" name="new_title" maxlength="128" value="' . esc_attr($title) . '">';
        echo '</td></tr>';
        echo '<tr><td style="padding-top:20px" align="center" colspan="2">';
        echo '<input type="submit" name="submit" value="Update">';
        echo '<input type="submit" name="cancel" value="Cancel">';
        echo '<input type="hidden" name="id" value="' . esc_attr($entry_id) . '" />';
        echo '<input type="hidden" name="title" value="' . esc_attr($title) . '" />';
        echo '<input type="hidden" name="server_file_name" value="' . esc_attr($server_file_name) . '" />';
        echo '<input type="hidden" name="m" value="' . esc_attr(strtolower($medium_subset)) . '" />';
        echo '<input type="hidden" name="wp_get_referer" value="' . remove_query_arg(array('m', 'id'), wp_get_referer()) . '" />';
        echo '</td></tr>';
        echo '</table>';
        echo '</td></tr>';
        echo '</table>';
        echo '</form>';
        unset($query_entries, $photo_helper);
    }

    /**
     * Display an obfuscated email link.
     *
     * @param array  $attr    The shortcode argument list. Allowed arguments:
     *                        - email
     *                        - HTML Attributes
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     */
    public function displayEmail($attr, $content, $tag)
    {
        $email = $attr['email'];
        unset($attr['email']);
        echo $this->html->mailto($email, $content, $attr);
    }

    /**
     * Show all entries for a month.
     * The default is to show the entries for the latest closed competition.
     * A dropdown selection to choose different months and/or season is also displayed.
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     */
    public function displayMonthlyEntries($attr, $content, $tag)
    {
        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
        $query_miscellaneous = new QueryMiscellaneous($this->rpsdb);
        $query_entries = new QueryEntries($this->rpsdb);
        $season_helper = new SeasonHelper($this->settings, $this->rpsdb);
        $photo_helper = new PhotoHelper($this->settings, $this->request, $this->rpsdb);

        $months = array();
        $themes = array();

        $session = new Session(array('name' => 'monthly_entries_' . COOKIEHASH));
        $session->start();
        $selected_date = $session->get('selected_date');
        $selected_season = $session->get('selected_season');

        list ($season_start_date, $season_end_date) = $season_helper->getSeasonStartEnd($selected_season);
        $scored_competitions = $query_competitions->getScoredCompetitions($season_start_date, $season_end_date);

        $is_scored_competitions = false;
        if (is_array($scored_competitions) && (!empty($scored_competitions))) {
            $is_scored_competitions = true;
            /** @var QueryCompetitions $competition */
            foreach ($scored_competitions as $competition) {
                $date_object = new \DateTime($competition->Competition_Date);
                $key = $date_object->format('Y-m-d');
                $months[$key] = $date_object->format('F') . ': ' . $competition->Theme;
                $themes[$key] = $competition->Theme;
            }
        }

        echo '<span class="competition-monthly-winners-form">Select a theme or season';
        $this->displayMonthAndSeasonSelectionForm($selected_season, $selected_date, $is_scored_competitions, $months, $season_helper);
        echo '<p></p></span>';

        $output = '';
        if ($is_scored_competitions) {
            $date = new \DateTime($selected_date);
            $date_text = $date->format('F j, Y');

            // We display these in masonry style
            $output = $this->html->element('div', array('id' => 'gallery-month-entries', 'class' => 'gallery gallery-masonry gallery-columns-5'));
            $output .= $this->html->element('div', array('class' => 'grid-sizer', 'style' => 'width: 193px'), true);
            $output .= '</div>';
            $output .= $this->html->element('div', array('id' => 'images'));
            $entries = $query_miscellaneous->getAllEntries($selected_date, $selected_date);
            if (is_array($entries)) {
                // Iterate through all the award winners and display each thumbnail in a grid
                /** @var QueryEntries $entry */
                foreach ($entries as $entry) {
                    $user_info = get_userdata($entry->Member_ID);
                    $title = $entry->Title;
                    $last_name = $user_info->user_lastname;
                    $first_name = $user_info->user_firstname;
                    // Display this thumbnail in the the next available column

                    $output .= $this->html->element('figure', array('class' => 'gallery-item-masonry masonry-150'));
                    $output .= $this->html->element('div', array('class' => 'gallery-item-content'));
                    $output .= $this->html->element('div', array('class' => 'gallery-item-content-images'));
                    $output .= $this->html->element('a', array('href' => $photo_helper->rpsGetThumbnailUrl($entry, 800), 'title' => $title . ' by ' . $first_name . ' ' . $last_name, 'rel' => 'rps-entries'));
                    $output .= $this->html->image($photo_helper->rpsGetThumbnailUrl($entry, '150w'));
                    $output .= '</a>';
                    $output .= '</div>';
                    $caption = "${title}<br /><span class='wp-caption-credit'>Credit: ${first_name} ${last_name}";
                    $output .= $this->html->element('figcaption', array('class' => 'wp-caption-text showcase-caption')) . wptexturize($caption) . "</figcaption>\n";
                    $output .= '</div>';

                    $output .= '</figure>' . "\n";
                }
            }
            $output .= '</div>';
            $header = '<p class="competition-theme">The '.count($entries).' entries submitted to Raritan Photographic Society for the theme "' . $themes[$selected_date] . '" held on ' . $date_text.'</p>';
            $output = $header . $output;
        }



        echo $output;

        unset($query_competitions, $query_miscellaneous, $query_entries, $season_helper, $photo_helper);
    }

    /**
     * Display all winners for the month.
     * All winners of the month are shown, which defaults to the latest month.
     * A dropdown selection to choose different months and/or season is also displayed.
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     */
    public function displayMonthlyWinners($attr, $content, $tag)
    {
        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
        $query_miscellaneous = new QueryMiscellaneous($this->rpsdb);
        $season_helper = new SeasonHelper($this->settings, $this->rpsdb);
        $photo_helper = new PhotoHelper($this->settings, $this->request, $this->rpsdb);

        $months = array();
        $themes = array();

        $session = new Session(array('name' => 'monthly_winners_' . COOKIEHASH));
        $session->start();
        $selected_date = $session->get('selected_date');
        $selected_season = $session->get('selected_season');

        list ($season_start_date, $season_end_date) = $season_helper->getSeasonStartEnd($selected_season);
        $scored_competitions = $query_competitions->getScoredCompetitions($season_start_date, $season_end_date);

        $is_scored_competitions = false;
        if (is_array($scored_competitions) && (!empty($scored_competitions))) {
            $is_scored_competitions = true;
            /** @var QueryCompetitions $competition */
            foreach ($scored_competitions as $competition) {
                $date_object = new \DateTime($competition->Competition_Date);
                $key = $date_object->format('Y-m-d');
                $months[$key] = $date_object->format('F') . ': ' . $competition->Theme;
                $themes[$key] = $competition->Theme;
            }
        }
        $max_num_awards = $query_miscellaneous->getMaxAwards($selected_date);

        // Start displaying the form

        echo '<span class="competition-monthly-winners-form">Select a theme or season ';
        $this->displayMonthAndSeasonSelectionForm($selected_season, $selected_date, $is_scored_competitions, $months, $season_helper);
        echo '<p></p></span>';

        $output ='';
        if ($is_scored_competitions) {

            $output .= "<table class=\"thumb_grid\">\n";
            // Output the column headings
            $output .= "<tr><th class='thumb_col_header' align='center'></th>\n";
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
                $output .= "<th class=\"thumb_col_header\" align=\"center\">$award_title</th>\n";
            }
            $award_winners = $query_miscellaneous->getWinners($selected_date);
            // Iterate through all the award winners and display each thumbnail in a grid
            $row = 0;
            $column = 0;
            $comp = "";
            foreach ($award_winners as $competition) {
                $user_info = get_userdata($competition->Member_ID);

                // Remember the important values from the previous record
                $prev_comp = $comp;

                // Grab a new record from the database
                $medium = $competition->Medium;
                $classification = $competition->Classification;
                $comp = "$classification<br>$medium";
                $title = $competition->Title;
                $last_name = $user_info->user_lastname;
                $first_name = $user_info->user_firstname;
                $award = $competition->Award;

                // If we're at the end of a row, finish off the row and get ready for the next one
                if ($prev_comp != $comp) {
                    // As necessary, pad the row out with empty cells
                    if ($row > 0 && $column < $max_num_awards) {
                        for ($i = $column; $i < $max_num_awards; $i++) {
                            $output .= "<td align=\"center\" class=\"thumb_cell\">";
                            $output .= "<div class=\"thumb_canvas\"></div></td>\n";
                        }
                    }
                    // Terminate this row
                    $output .= "</tr>\n";

                    // Initialize the new row
                    $row += 1;
                    $column = 0;
                    $output .= "<tr><td class=\"comp_cell\" align=\"center\">$comp</td>\n";
                }
                // Display this thumbnail in the the next available column
                $output .= "<td align=\"center\" class=\"thumb_cell\">\n";
                $output .= "  <div class=\"thumb_canvas\">\n";
                $output .= "    <a href=\"" . $photo_helper->rpsGetThumbnailUrl($competition, 800) . "\" rel=\"" . tag_escape($classification) . tag_escape($medium) . "\" title=\"($award) $title - $first_name $last_name\">\n";
                $output .= "    <img class=\"thumb_img\" src=\"" . $photo_helper->rpsGetThumbnailUrl($competition, 75) . "\" /></a>\n";
                $output .= "<div id='rps_colorbox_title'>$title<br />$first_name $last_name</div>";
                $output .= "  </div>\n</td>\n";
                $column += 1;
            }
            // As necessary, pad the last row out with empty cells
            if ($row > 0 && $column < $max_num_awards) {
                for ($i = $column; $i < $max_num_awards; $i++) {
                    $output .= "<td align=\"center\" class=\"thumb_cell\">";
                    $output .= "<div class=\"thumb_canvas\"></div></td>\n";
                }
            }
            // Close out the table
            $output .= "</tr>\n</table>\n";
            $date = new \DateTime($selected_date);
            $date_text = $date->format('F j, Y');
            $entries_amount = $query_miscellaneous->countAllEntries($selected_date);
            $header = '<p class="competition-theme">Out of '.$entries_amount.' entries, a jury selected these winners of the competition with the theme "' . $themes[$selected_date] . '" held on ' . $date_text.', which was organized by Raritan Photographic Society.</p>';
            $output = $header . $output;
        } else {
            $output = '<p>There are no scored competitions for the selected season.</p>';
        }

        echo $output;

        unset($query_competitions, $query_miscellaneous, $season_helper, $photo_helper);
    }

    /**
     * Display the entries of the current user.
     * This page shows the current entries for a competition of the current user.
     *
     * @param array  $attr    The shortcode argument list. Allowed arguments:
     *                        - medium
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @see Frontend::actionHandleHttpPostRpsMyEntries
     */
    public function displayMyEntries($attr, $content, $tag)
    {
        global $post;

        $attr = shortcode_atts(array('medium' => 'digital'), $attr);

        $query_entries = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
        $competition_helper = new CompetitionHelper($this->settings, $this->rpsdb);
        $photo_helper = new PhotoHelper($this->settings, $this->request, $this->rpsdb);

        $medium_subset_medium = $attr['medium'];

        $open_competitions = $query_competitions->getOpenCompetitions(get_current_user_id(), $medium_subset_medium);
        $open_competitions = CommonHelper::arrayMsort($open_competitions, array('Competition_Date' => array(SORT_ASC), 'Medium' => array(SORT_ASC)));

        if (empty($open_competitions)) {
            $this->settings->set('errmsg', 'There are no competitions available to enter');
            echo '<div id="errmsg">' . esc_html($this->settings->get('errmsg')) . '</div>';

            return;
        }

        $session = new Session(array('name' => 'rps_my_entries_' . COOKIEHASH, 'cookie_path' => get_page_uri($post->ID)));
        $session->start();
        if ($this->request->isMethod('POST')) {
            switch ($this->request->input('submit_control')) {

                case 'select_comp':
                    $competition_date = $this->request->input('select_comp');
                    $medium = $this->request->input('medium');
                    break;

                case 'select_medium':
                    $competition_date = $this->request->input('comp_date');
                    $medium = $this->request->input('selected_medium');
                    break;
                default:
                    $competition_date = $this->request->input('comp_date');
                    $medium = $this->request->input('medium');
                    break;
            }
        } else {
            $current_competition = reset($open_competitions);
            $competition_date = $session->get('myentries/competition_date', mysql2date('Y-m-d', $current_competition->Competition_Date));
            $medium = $session->get('myentries/medium', $current_competition->Medium);
        }
        $classification = CommonHelper::getUserClassification(get_current_user_id(), $medium);
        $current_competition = $query_competitions->getCompetitionByDateClassMedium($competition_date, $classification, $medium);

        $session->set('myentries/competition_date', $current_competition->Competition_Date);
        $session->set('myentries/medium', $current_competition->Medium);
        $session->save();

        if ($this->settings->has('errmsg')) {
            echo '<div id="errmsg">' . esc_html($this->settings->get('errmsg')) . '</div>';
        }

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

        // Start the form
        $action = home_url('/' . get_page_uri($post->ID));
        echo '<form name="MyEntries" action=' . $action . ' method="post">' . "\n";
        echo '<input type="hidden" name="submit_control">' . "\n";
        echo '<input type="hidden" name="comp_date" value="' . $current_competition->Competition_Date . '">' . "\n";
        echo '<input type="hidden" name="medium" value="' . $current_competition->Medium . '">' . "\n";
        echo '<input type="hidden" name="classification" value="' . $current_competition->Classification . '">' . "\n";
        echo '<input type="hidden" name="_wpnonce" value="' . wp_create_nonce('avh-rps-myentries') . '" />' . "\n";
        echo '<table class="form_frame" width="90%">' . "\n";

        // Form Heading
        echo "<tr><th colspan=\"6\" align=\"center\" class=\"form_frame_header\">My Entries for " . $current_competition->Medium . " on " . mysql2date('Y-m-d', $current_competition->Competition_Date) . "</th></tr>\n";
        echo "<tr><td align=\"center\" colspan=\"6\">\n";
        echo "<table width=\"100%\">\n";
        echo '<tr>';
        echo '<td width="25%">';
        // echo '<span class="rps-comp-medium">' . $this->settings->medium . '</span>';
        switch ($current_competition->Medium) {
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

        echo '<img src="' . plugins_url('/images' . $img, $this->settings->get('plugin_basename')) . '">';
        echo '</td>';
        echo "<td width=\"75%\">\n";
        echo "<table width=\"100%\">\n";

        // The competition date dropdown list
        echo "<tr>\n";
        echo "<td width=\"33%\" align=\"right\"><b>Competition Date:&nbsp;&nbsp;</b></td>\n";
        echo "<td width=\"64%\" align=\"left\">\n";

        $previous_date = '';
        $open_competitions_options = array();
        foreach ($open_competitions as $open_competition) {
            if ($previous_date == $open_competition->Competition_Date) {
                continue;
            }
            $previous_date = $open_competition->Competition_Date;
            $open_competitions_options[$open_competition->Competition_Date] = strftime('%d-%b-%Y', strtotime($open_competition->Competition_Date)) . " " . $open_competition->Theme;
        }
        echo $this->formBuilder->select('select_comp', $open_competitions_options, $current_competition->Competition_Date, array('onchange' => 'submit_form(\'select_comp\')'));
        echo "</td></tr>\n";

        // Competition medium dropdown list
        echo "<tr>\n<td width=\"33%\" align=\"right\"><b>Competition:&nbsp;&nbsp;</b></td>\n";
        echo "<td width=\"64%\" align=\"left\">\n";
        echo $this->formBuilder->select('selected_medium', $competition_helper->getMedium($open_competitions), $current_competition->Medium, array('onchange' => 'submit_form(\'select_medium\')'));
        echo "</td></tr>\n";

        // Display the Classification and Theme for the selected competition
        echo "<tr><td width=\"33%\" align=\"right\"><b>Classification:&nbsp;&nbsp;<b></td>\n";
        echo "<td width=\"64%\" align=\"left\">" . $current_competition->Classification . "</td></tr>\n";
        echo "<tr><td width=\"33%\" align=\"right\"><b>Theme:&nbsp;&nbsp;<b></td>\n";
        echo "<td width=\"64%\" align=\"left\">$current_competition->Theme</td></tr>\n";

        echo "</table>\n";
        echo "</td></tr></table>\n";

        // Display a warning message if the competition is within one week aka 604800 secs (60*60*24*7) of closing
        $close_date = $query_competitions->getCompetitionCloseDate($current_competition->Competition_Date, $current_competition->Classification, $current_competition->Medium);
        if ($close_date !== null) {
            $close_epoch = strtotime($close_date);
            $time_to_close = $close_epoch - current_time('timestamp');
            if ($time_to_close >= 0 && $time_to_close <= 604800) {
                echo "<tr><td colspan=\"6\" align=\"center\" style=\"color:red\"><b>Note:</b> This competition will close on " . mysql2date("F j, Y", $close_date) . " at " . mysql2date('h:i a', $close_date) . "</td></tr>\n";
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
        $max_entries_per_member_per_comp = $query_competitions->getCompetitionMaxEntries($current_competition->Competition_Date, $current_competition->Classification, $current_competition->Medium);

        // Retrieve the total number of entries submitted by this member for this competition date
        $total_entries_submitted = $query_entries->countEntriesSubmittedByMember(get_current_user_id(), $current_competition->Competition_Date);

        $entries = $query_entries->getEntriesSubmittedByMember(get_current_user_id(), $current_competition->Competition_Date, $current_competition->Classification, $current_competition->Medium);
        // Build the rows of submitted images
        $num_rows = 0;
        /** @var QueryEntries $recs */
        foreach ($entries as $recs) {
            $competition = $query_competitions->getCompetitionById($recs->Competition_ID);
            $num_rows += 1;
            $row_style = $num_rows % 2 == 1 ? "odd_row" : "even_row";

            // Checkbox column
            echo '<tr class="' . $row_style . '"><td align="center" width="5%"><input type="checkbox" name="EntryID[]" value="' . $recs->ID . '">' . "\n";

            // Thumbnail column
            $image_url = home_url($recs->Server_File_Name);
            echo "<td align=\"center\" width=\"10%\">\n";
            echo '<a href="' . $image_url . '" rel="' . $current_competition->Competition_Date . '" title="' . $recs->Title . ' ' . $competition->Classification . ' ' . $competition->Medium . '">' . "\n";
            echo "<img src=\"" . $photo_helper->rpsGetThumbnailUrl($recs, 75) . "\" />\n";
            echo "</a></td>\n";

            // Title column
            echo '<td align="left" width="40%">';
            echo htmlentities($recs->Title) . "</td>\n";

            // File Name
            echo '<td align="left" width="25%">' . $recs->Client_File_Name . "</td>\n";

            // Image width and height columns. The height and width values are suppressed if the Client_File_Name is
            // empty i.e. no image uploaded for a print competition.
            if (file_exists($this->request->server('DOCUMENT_ROOT') . $recs->Server_File_Name)) {
                $size = getimagesize($this->request->server('DOCUMENT_ROOT') . $recs->Server_File_Name);
            } else {
                $size = array(0, 0);
            }
            if ($recs->Client_File_Name > "") {
                echo '<td align="center" style="text-align:center" width="10%">' . $size[0] . "</td>\n";
                echo '<td align="center" width="10%">' . $size[1] . "</td>\n";
            } else {
                echo "<td align=\"center\" width=\"10%\">&nbsp;</td>\n";
                echo "<td align=\"center\" width=\"10%\">&nbsp;</td>\n";
            }
        }

        // Add some instructional bullet points above the buttons
        echo '<tr><td align="left" style="padding-top: 5px;" colspan="6">';
        echo '<ul style="margin: 0 0 0 15px;padding:0">';
        if ($num_rows > 0) {
            echo "<li>Click the thumbnail or title to view the full size image</li>\n";
        }
        echo "<ul></td></tr>\n";

        if ($this->request->has('resized') && ('1' == $this->request->input('resized'))) {
            echo "<tr><td align=\"left\" colspan=\"6\" class=\"warning_cell\">";
            echo "<ul><li><b>Note</b>: The web site automatically resized your image to match the digital projector.\n";
            echo "</li></ul>\n";
        }

        // Buttons at the bottom of the list of submitted images
        echo "<tr><td align=\"center\" style=\"padding-top: 10px; text-align:center\" colspan=\"6\">\n";
        echo '<span>';
        // Don't show the Add button if the max number of images per member reached
        if ($num_rows < $max_entries_per_member_per_comp && $total_entries_submitted < $this->settings->get('club_max_entries_per_member_per_date')) {
            echo $this->formBuilder->input('submit[add]', 'Add', array('type' => 'submit', 'onclick' => 'submit_form(\'add\')')) . "&nbsp;";
        }
        if ($num_rows > 0 && $max_entries_per_member_per_comp > 0) {
            echo $this->formBuilder->input('submit[edit_title]', 'Change Title', array('type' => 'submit', 'onclick' => 'submit_form(\'add\')')) . "&nbsp;";
            //echo "<input type=\"submit\" name=\"submit[edit_title]\" value=\"Change Title\"  onclick=\"submit_form('edit')\">" . "&nbsp;\n";
        }
        if ($num_rows > 0) {
            echo $this->formBuilder->input('submit[delete]', 'Remove', array('type' => 'submit', 'onclick' => 'return  confirmSubmit()'));
            //echo '<input type="submit" name="submit[delete]" value="Remove" onclick="return  confirmSubmit()"></td></tr>' . "\n";
        }
        echo '</span></td></tr>';
        // All done, close out the table and the form
        echo "</table>\n</form>\n<br />\n";

        unset($query_entries, $query_competitions, $competition_helper, $photo_helper);
    }

    /**
     * Display the eights and higher for a given member ID.
     *
     * @param array  $attr    The shortcode argument list. Allowed arguments:
     *                        - id => The member ID
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     */
    public function displayPersonWinners($attr, $content, $tag)
    {
        $query_miscellaneous = new QueryMiscellaneous($this->rpsdb);
        $photo_helper = new PhotoHelper($this->settings, $this->request, $this->rpsdb);

        $attr = shortcode_atts(array('id' => 0, 'images'=>6), $attr);

        echo '<section class="rps-showcases">';

        echo '<div class="rps-sc-text entry-content">';
        $entries = $query_miscellaneous->getEightsAndHigherPerson($attr['id']);
        $images = array_rand($entries, $attr['images']);

        $output = $this->html->element('div', array('id' => 'gallery-month-entries', 'class' => 'gallery gallery-masonry gallery-columns-3'));
        $output .= $this->html->element('div', array('class' => 'grid-sizer', 'style' => 'width: 193px'), true);
        $output .= '</div>';
        $output .= $this->html->element('div', array('id' => 'images'));
        foreach ($images as $key) {
            $recs = $entries[$key];
            $user_info = get_userdata($recs->Member_ID);


            $title = $recs->Title;
            $last_name = $user_info->user_lastname;
            $first_name = $user_info->user_firstname;
            // Display this thumbnail in the the next available column
            $output .= $this->html->element('figure', array('class' => 'gallery-item-masonry masonry-150'));
            $output .= $this->html->element('div', array('class' => 'gallery-item-content'));
            $output .= $this->html->element('div', array('class' => 'gallery-item-content-images'));
            $output .= $this->html->element('a', array('href' => $photo_helper->rpsGetThumbnailUrl($recs, 800), 'title' => $title . ' by ' . $first_name . ' ' . $last_name, 'rel' => 'rps-entries'));
            $output .= $this->html->image($photo_helper->rpsGetThumbnailUrl($recs, '150w'));
            $output .= '</a>';
            $output .= '</div>';
            $caption = "${title}<br /><span class='wp-caption-credit'>Credit: ${first_name} ${last_name}";
            $output .= $this->html->element('figcaption', array('class' => 'wp-caption-text showcase-caption')) . wptexturize($caption) . "</figcaption>\n";
            $output .= '</div>';

            $output .= '</figure>' . "\n";
        }
        $output .= '</div>';

        echo $output;

        unset($query_miscellaneous, $photo_helper);
    }

    /**
     * Displays the scores of the current user.
     * By default the scores of the latest season is shown.
     * A drop down with a season list is shown for the user to select.
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     */
    public function displayScoresCurrentUser($attr, $content, $tag)
    {
        global $post;
        $query_miscellaneous = new QueryMiscellaneous($this->rpsdb);
        $season_helper = new SeasonHelper($this->settings, $this->rpsdb);

        $seasons = $season_helper->getSeasons();
        $selected_season = esc_attr($this->request->input('new_season', end($seasons)));
        list ($season_start_date, $season_end_date) = $season_helper->getSeasonStartEnd($selected_season);

        // Start building the form
        $action = home_url('/' . get_page_uri($post->ID));
        $form = '';
        $form .= '<form name="my_scores_form" method="post" action="' . $action . '">';
        $form .= '<input type="hidden" name="selected_season" value="' . $selected_season . '" />';
        // Drop down list for season
        $form .= $season_helper->getSeasonDropdown($selected_season);
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
        $scores = $query_miscellaneous->getScoresUser(get_current_user_id(), $season_start_date, $season_end_date);

        // Bail out if not entries found
        if (empty($scores)) {
            echo "<tr><td colspan=\"6\">No entries submitted</td></tr>\n";
            echo "</table>\n";
        } else {

            // Build the list of submitted images
            $comp_count = 0;
            $prev_date = "";
            $prev_medium = "";
            $row_style = 'odd_row';
            foreach ($scores as $recs) {
                $date_parts = explode(" ", $recs['Competition_Date']);
                $date_parts[0] = strftime('%d-%b-%Y', strtotime($date_parts[0]));
                $comp_date = $date_parts[0];
                $medium = $recs['Medium'];
                $theme = $recs['Theme'];
                $title = $recs['Title'];
                $score = $recs['Score'];
                $award = $recs['Award'];
                if ($date_parts[0] != $prev_date) {
                    $comp_count += 1;
                    $row_style = $comp_count % 2 == 1 ? "odd_row" : "even_row";
                    $prev_medium = "";
                }

                $image_url = home_url($recs['Server_File_Name']);

                if ($prev_date == $date_parts[0]) {
                    $date_parts[0] = "";
                    $theme = "";
                } else {
                    $prev_date = $date_parts[0];
                }
                if ($prev_medium == $medium) {
                    // $medium = "";
                    $theme = "";
                } else {
                    $prev_medium = $medium;
                }
                $score_award = "";
                if ($score > "") {
                    $score_award = " / {$score}pts";
                }
                if ($award > "") {
                    $score_award .= " / $award";
                }

                echo "<tr>";
                echo "<td align=\"left\" valign=\"top\" class=\"$row_style\" width=\"12%\">" . $date_parts[0] . "</td>\n";
                echo "<td align=\"left\" valign=\"top\" class=\"$row_style\">$theme</td>\n";
                echo "<td align=\"left\" valign=\"top\" class=\"$row_style\">$medium</td>\n";
                // echo "<td align=\"left\" valign=\"top\" class=\"$row_style\"><a href=\"$image_url\" target=\"_blank\">$title</a></td>\n";
                echo "<td align=\"left\" valign=\"top\" class=\"$row_style\"><a href=\"{$image_url}\" rel=\"lightbox[{$comp_date}]\" title=\"" . htmlentities($title) . " / {$comp_date} / $medium{$score_award}\">" . htmlentities(
                        $title
                    ) . "</a></td>\n";
                echo "<td class=\"$row_style\" valign=\"top\" align=\"center\" width=\"8%\">$score</td>\n";
                echo "<td class=\"$row_style\" valign=\"top\" align=\"center\" width=\"8%\">$award</td></tr>\n";
            }
            echo "</table>";
        }
        unset($query_miscellaneous, $season_helper);
    }

    /**
     * Displays the form to upload a new entry.
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @see Frontend::actionHandleHttpPostRpsUploadEntry
     */
    public function displayUploadEntry($attr, $content, $tag)
    {
        global $post;

        // Error messages
        if ($this->settings->has('errmsg')) {
            echo '<div id="errmsg">';
            echo $this->settings->get('errmsg');
            echo '</div>';
        }

        $action = home_url('/' . get_page_uri($post->ID));
        echo $this->formBuilder->open($action . '/?post=1', array('enctype' => 'multipart/form-data'));

        if ($this->request->has('m')) {
            $medium_subset = "Digital";
            if ($this->request->input('m') == "prints") {
                $medium_subset = "Prints";
            }
            echo $this->formBuilder->hidden('medium_subset', $medium_subset);
        }
        if ($this->request->has('wp_get_referer')) {
            $_ref = $this->request->input('wp_get_referer');
        } else {
            $_ref = wp_get_referer();
        }
        echo $this->formBuilder->hidden('wp_get_referer', remove_query_arg(array('m'), $_ref));
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
        echo $this->formBuilder->close();
    }

    /**
     * Display the Facebook thumbs for the Category Winners Page.
     *
     * @param array $entries
     */
    private function displayCategoryWinnersFacebookThumbs($entries)
    {
        $photo_helper = new PhotoHelper($this->settings, $this->request, $this->rpsdb);
        foreach ($entries as $entry) {
            echo '<img src="' . $photo_helper->rpsGetThumbnailUrl($entry, 'fb_thumb') . '" /></a>';
        }
        unset($photo_helper);
    }

    /**
     * Display the form for selecting the month and season.
     *
     * @param string       $selected_season
     * @param string       $selected_date
     * @param boolean      $is_scored_competitions
     * @param array        $months
     * @param SeasonHelper $season_helper
     */
    private function displayMonthAndSeasonSelectionForm($selected_season, $selected_date, $is_scored_competitions, $months, $season_helper)
    {
        global $post;
        echo '<script type="text/javascript">';
        echo 'function submit_form(control_name) {' . "\n";
        echo '	document.month_season_form.submit_control.value = control_name;' . "\n";
        echo '	document.month_season_form.submit();' . "\n";
        echo '}' . "\n";
        echo '</script>';

        $action = home_url('/' . get_page_uri($post->ID));
        $form = '';
        $form .= $this->formBuilder->open($action, array('name' => 'month_season_form'));
        $form .= $this->formBuilder->hidden('submit_control');
        $form .= $this->formBuilder->hidden('selected_season', $selected_season);
        $form .= $this->formBuilder->hidden('selected_date', $selected_date);

        if ($is_scored_competitions) {
            // Drop down list for months
            $form .= $this->getMonthsDropdown($months, $selected_date);
        } else {
            $form .= 'No scored competitions this season. ';
        }

        // Drop down list for season
        $form .= $season_helper->getSeasonDropdown($selected_season);
        $form .= $this->formBuilder->close();
        echo $form;
        unset($form);
    }

    /**
     * Display a dropdown for the given months
     *
     * @param array   $months
     * @param string  $selected_month
     * @param boolean $echo
     *
     * @return string|null
     */
    private function getMonthsDropdown($months, $selected_month, $echo = false)
    {

        $form = $this->formBuilder->select('new_month', $months, $selected_month, array('onChange' => 'submit_form("new_month")'));

        if ($echo) {
            echo $form;
        } else {
            return $form;
        }

        return null;
    }
}
