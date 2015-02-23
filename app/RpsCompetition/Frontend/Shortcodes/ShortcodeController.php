<?php
namespace RpsCompetition\Frontend\Shortcodes;

use Avh\Html\FormBuilder;
use Illuminate\Container\Container as IlluminateContainer;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Entity\Forms\EditTitle as EntityFormEditTitle;
use RpsCompetition\Entity\Forms\MyEntries as EntityFormMyEntries;
use RpsCompetition\Entity\Forms\UploadEntry as EntityFormUploadEntry;
use RpsCompetition\Form\Type\EditTitleType;
use RpsCompetition\Form\Type\MyEntriesType;
use RpsCompetition\Form\Type\UploadEntryType;
use RpsCompetition\Libs\Controller;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class ShortcodeController
 *
 * @package RpsCompetition\Frontend\Shortcodes
 */
final class ShortcodeController extends Controller
{
    private $formBuilder;
    /** @var  \Symfony\Component\Form\FormFactory */
    private $formFactory;
    private $html;
    /** @var  ShortcodeModel */
    private $model;
    /** @var  ShortcodeView */
    private $view;

    /**
     * Constructor
     *
     * @param IlluminateContainer $container
     */
    public function __construct(IlluminateContainer $container)
    {
        $this->setContainer($container);
        $this->setSettings($this->container->make('Settings'));
        $this->setRpsdb($this->container->make('RpsDb'));
        $this->setRequest($this->container->make('IlluminateRequest'));
        $this->setSession($this->container->make('Session'));
        $this->formFactory = $this->container->make('formFactory');
        $template = [];
        $template[] = $this->settings->get('template_dir');
        $template[] = $this->settings->get('template_dir') . '/social-networks';
        $this->setTemplateEngine($this->container->make('Templating', ['template_dir' => $template, 'cache_dir' => $this->settings->get('upload_dir') . '/twig-cache/']));
        $this->view = $this->container->make('ShortcodeView', ['template_dir' => $this->settings->get('template_dir'), 'cache_dir' => $this->settings->get('upload_dir') . '/twig-cache/']);

        $this->html = $this->container->make('HtmlBuilder');
        $this->formBuilder = new FormBuilder($this->html);

        $this->model = $this->container->make('ShortcodeModel');
    }

    /**
     * Display all scores.
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @todo: MVC
     */
    public function shortcodeAllScores($attr, $content, $tag)
    {
        global $post;

        $query_competitions = $this->container->make('QueryCompetitions');
        $query_miscellaneous = $this->container->make('QueryMiscellaneous');
        $season_helper = $this->container->make('SeasonHelper');
        $seasons = $season_helper->getSeasons();
        $selected_season = esc_attr($this->request->input('new_season', end($seasons)));

        $award_map = ['1st' => '1', '2nd' => '2', '3rd' => '3', 'HM' => 'H'];

        list ($season_start_date, $season_end_date) = $season_helper->getSeasonStartEnd($selected_season);

        $competition_dates = $query_competitions->getCompetitionDates($season_start_date, $season_end_date);
        // Build an array of competition dates in "MM/DD" format for column titles.
        // Also remember the max entries per member for each competition and the number
        // of judges for each competition.
        $total_max_entries = 0;
        $comp_dates = [];
        $comp_max_entries = [];
        $comp_num_judges = [];
        foreach ($competition_dates as $key => $recs) {
            $date_parts = explode(" ", $recs['Competition_Date']);
            list (, $comp_month, $comp_day) = explode("-", $date_parts[0]);
            $comp_dates[$date_parts[0]] = sprintf("%d/%d", $comp_month, $comp_day);
            $comp_max_entries[$date_parts[0]] = $recs['Max_Entries'];
            $total_max_entries += $recs['Max_Entries'];
            $comp_num_judges[$date_parts[0]] = $recs['Num_Judges'];
        }

        $club_competition_results_unsorted = $query_miscellaneous->getCompetitionResultByDate($season_start_date, $season_end_date);
        $club_competition_results = CommonHelper::arrayMsort(
            $club_competition_results_unsorted,
            [
                'Medium'           => [SORT_DESC],
                'Class_Code'       => [SORT_ASC],
                'LastName'         => [SORT_ASC],
                'FirstName'        => [SORT_ASC],
                'Competition_Date' => [SORT_ASC]
            ]
        )
        ;
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
            $member_scores = [];
            $comp_dates_keys = array_keys($comp_dates);
            foreach ($comp_dates_keys as $key) {
                $member_scores[$key] = [];
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
                    $member_scores = [];
                    foreach ($comp_dates as $comp_dates_key => $comp_dates_date) {
                        $member_scores[$comp_dates_key] = [];
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
     * @todo: MVC
     */
    public function shortcodeBanquetCurrentUser($attr, $content, $tag)
    {
        global $post;

        $query_miscellaneous = $this->container->make('QueryMiscellaneous');
        $query_entries = $this->container->make('QueryEntries');
        $query_banquet = $this->container->make('QueryBanquet');
        $season_helper = $this->container->make('SeasonHelper');
        $seasons = $season_helper->getSeasons();
        $selected_season = end($seasons);

        if ($this->request->isMethod('POST')) {
            $selected_season = esc_attr($this->request->input('new_season', $selected_season));
        }

        list ($season_start_date, $season_end_date) = $season_helper->getSeasonStartEnd($selected_season);
        $scores = $query_miscellaneous->getScoresUser(get_current_user_id(), $season_start_date, $season_end_date);
        $banquet_id = $query_banquet->getBanquets($season_start_date, $season_end_date);
        $banquet_id_string = '0';
        $banquet_id_array = [];
        $disabled = '';
        $banquet_entries = [];
        if (is_array($banquet_id) && !empty($banquet_id)) {
            foreach ($banquet_id as $record) {
                $banquet_id_array[] = $record['ID'];
                if ($record['Closed'] == 'Y') {
                    $disabled = 'disabled="1"';
                }
            }

            $banquet_id_string = implode(',', $banquet_id_array);
            $where = 'Competition_ID in (' . $banquet_id_string . ') AND Member_ID = "' . get_current_user_id() . '"';
            $banquet_entries = $query_entries->query(['where' => $where]);
        }

        if (!is_array($banquet_entries)) {
            $banquet_entries = [];
        }
        $all_entries = [];
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
            echo '<input type="hidden" name="wp_get_referer" value="' . remove_query_arg(['m', 'id'], wp_get_referer()) . '" />';
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
     *
     * @return string
     *
     * @internal Shortcode: rps_category_winners
     *
     */
    public function shortcodeCategoryWinners($attr, $content, $tag)
    {
        $class = 'Beginner';
        $award = '1';
        $date = '';
        $output = '';
        extract($attr, EXTR_OVERWRITE);

        $entries = $this->model->getWinner($class, $award, $date);

        /**
         * Check if we ran the filter filterWpseoPreAnalysisPostsContent.
         *
         * @see Frontend::filterWpseoPreAnalysisPostsContent
         */
        $didFilterWpseoPreAnalysisPostsContent = $this->settings->get('didFilterWpseoPreAnalysisPostsContent', false);

        if (is_array($entries)) {
            if (!$didFilterWpseoPreAnalysisPostsContent) {
                $data = $this->model->getFacebookThumbs($entries);
                $output = $this->view->fetch('facebook.html.twig', $data);
            } else {
                $data = $this->model->getCategoryWinners($class, $entries, '250');
                $output = $this->view->fetch('category-winners.html.twig', $data);
            }
        }

        return $output;
    }

    /**
     * Display the form to edit the title of the selected entry
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     *
     * @see Frontend::actionHandleHttpPostRpsEditTitle
     */
    public function shortcodeEditTitle($attr, $content, $tag)
    {
        global $post;
        $query_entries = $this->container->make('QueryEntries');
        $photo_helper = $this->container->make('PhotoHelper');

        if ($this->settings->has('formerror')) {
            /** @var \Symfony\Component\Form\FormErrorIterator $error_obj */
            $error_obj = $this->settings->get('formerror');
            $form = $error_obj->getForm();
            $server_file_name = $form->get('server_file_name')
                                     ->getData()
            ;
        } else {
            $entity = new EntityFormEditTitle();
            $medium_subset = "Digital";
            if ($this->request->input('m') == "prints") {
                $medium_subset = "Prints";
            }
            $entry_id = $this->request->input('id');

            $recs = $query_entries->getEntryById($entry_id);
            $title = $recs->Title;
            $server_file_name = $recs->Server_File_Name;

            $action = add_query_arg(['id' => $entry_id, 'm' => strtolower($medium_subset)], get_permalink($post->ID));
            $entity->setId($entry_id);

            $entity->setNewTitle($title);
            $entity->setTitle($title);
            $entity->setServerFileName($server_file_name);
            $entity->setM($medium_subset);
            $entity->setWpGetReferer(remove_query_arg(['m', 'id'], wp_get_referer()));
            $form = $this->formFactory->create(new EditTitleType($entity), $entity, ['action' => $action, 'attr' => ['id' => 'edittitle']]);
        }
        $data = [];
        $data['image']['source'] = $photo_helper->getThumbnailUrl($server_file_name, '200');

        unset($query_entries, $photo_helper);

        return $this->view->fetch('edit_title.html.twig', ['data' => $data, 'form' => $form->createView()]);
    }

    /**
     * Display an obfuscated email link.
     *
     * @param array  $attr    The shortcode argument list. Allowed arguments:
     *                        - email
     *                        - HTML Attributes
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     */
    public function shortcodeEmail($attr, $content, $tag)
    {
        return $this->html->mailto($attr['email'], $content, $attr);
    }

    /**
     * Show all entries for a month.
     * The default is to show the entries for the latest closed competition.
     * A dropdown selection to choose different months and/or season is also displayed.
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     *
     * @internal Shortcode: rps_monthly_entries
     */
    public function shortcodeMonthlyEntries($attr, $content, $tag)
    {
        $output = '';
        $selected_date = $this->session->get('monthly_entries_selected_date');

        if ($this->model->isScoredCompetition($selected_date)) {
            /**
             * Check if we ran the filter filterWpseoPreAnalysisPostsContent.
             *
             * @see Frontend::filterWpseoPreAnalysisPostsContent
             */
            $didFilterWpseoPreAnalysisPostsContent = $this->settings->get('didFilterWpseoPreAnalysisPostsContent', false);
            if (!$didFilterWpseoPreAnalysisPostsContent) {
                $entries = $this->model->getAllEntries($selected_date, $selected_date);
                $data = $this->model->getFacebookThumbs($entries);
                $output = $this->view->fetch('facebook.html.twig', $data);
            } else {
                $selected_season = $this->session->get('monthly_entries_selected_season');
                $scored_competitions = $this->model->getScoredCompetitions($selected_season);

                $data = $this->model->getMonthlyEntries($selected_season, $selected_date, $scored_competitions);
                $output = $this->view->fetch('monthly-entries.html.twig', $data);
            }
        }

        return $output;
    }

    /**
     * Display all winners for the month.
     * All winners of the month are shown, which defaults to the latest month.
     * A dropdown selection to choose different months and/or season is also displayed.
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     *
     * @internal Shortcode: rps_monthly_winners
     */
    public function shortcodeMonthlyWinners($attr, $content, $tag)
    {

        $output = '';
        $selected_date = $this->session->get('monthly_winners_selected_date');
        $selected_season = $this->session->get('monthly_winners_selected_season');

        $scored_competitions = $this->model->getScoredCompetitions($selected_season);

        if (is_array($scored_competitions) && (!empty($scored_competitions))) {
            /**
             * Check if we ran the filter filterWpseoPreAnalysisPostsContent.
             *
             * @see Frontend::filterWpseoPreAnalysisPostsContent
             */
            $didFilterWpseoPreAnalysisPostsContent = $this->settings->get('didFilterWpseoPreAnalysisPostsContent', false);
            if (!$didFilterWpseoPreAnalysisPostsContent) {
                $entries = $this->model->getWinners($selected_date);
                $data = $this->model->getFacebookThumbs($entries);
                $output = $this->view->fetch('facebook.html.twig', $data);
            } else {
                $data = $this->model->getMonthlyWinners($selected_season, $selected_date, $scored_competitions);
                $output = $this->view->fetch('monthly-winners.html.twig', $data);
            }
        }

        return $output;
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
     * @return string
     *
     * @see Frontend::actionHandleHttpPostRpsMyEntries
     */
    public function shortcodeMyEntries($attr, $content, $tag)
    {
        global $post;

        $attr = shortcode_atts(['medium' => 'digital'], $attr);

        $query_entries = $this->container->make('QueryEntries');
        $query_competitions = $this->container->make('QueryCompetitions');
        $competition_helper = $this->container->make('CompetitionHelper');
        $photo_helper = $this->container->make('PhotoHelper');

        $medium_subset_medium = $attr['medium'];

        $open_competitions = $query_competitions->getOpenCompetitions(get_current_user_id(), $medium_subset_medium);
        $open_competitions = CommonHelper::arrayMsort($open_competitions, ['Competition_Date' => [SORT_ASC], 'Medium' => [SORT_ASC]]);
        $previous_date = '';
        $open_competitions_options = [];
        foreach ($open_competitions as $open_competition) {
            if ($previous_date == $open_competition->Competition_Date) {
                continue;
            }
            $previous_date = $open_competition->Competition_Date;
            $open_competitions_options[$open_competition->Competition_Date] = strftime('%d-%b-%Y', strtotime($open_competition->Competition_Date)) . " " . $open_competition->Theme;
        }

        $current_competition = reset($open_competitions);
        $competition_date = $this->session->get('myentries/' . $medium_subset_medium . '/competition_date', mysql2date('Y-m-d', $current_competition->Competition_Date));
        $medium = $this->session->get('myentries/' . $medium_subset_medium . '/medium', $current_competition->Medium);
        $classification = CommonHelper::getUserClassification(get_current_user_id(), $medium);
        $current_competition = $query_competitions->getCompetitionByDateClassMedium($competition_date, $classification, $medium);

        $this->session->set('myentries/subset', $medium_subset_medium);
        $this->session->set('myentries/' . $medium_subset_medium . '/competition_date', $current_competition->Competition_Date);
        $this->session->set('myentries/' . $medium_subset_medium . '/medium', $current_competition->Medium);
        $this->session->set('myentries/' . $medium_subset_medium . '/classification', $current_competition->Classification);
        $this->session->save();

        // Start the form
        $action = home_url('/' . get_page_uri($post->ID));
        $entity = new EntityFormMyEntries();
        $entity->setWpnonce(wp_create_nonce('avh-rps-myentries'));
        $entity->setSelectComp($open_competitions_options);
        $entity->setSelectedMedium($competition_helper->getMedium($open_competitions));
        $entity->setCompDate($current_competition->Competition_Date);
        $entity->setMedium($current_competition->Medium);
        $entity->setClassification($current_competition->Classification);
        $form = $this->formFactory->create(new MyEntriesType($entity), $entity, ['action' => $action, 'attr' => ['id' => 'myentries']]);

        $data = [];
        $data['competition_date'] = $current_competition->Competition_Date;
        $data['medium'] = $current_competition->Medium;
        $data['classification'] = $current_competition->Classification;
        $data['select_medium']['selected'] = $current_competition->Medium;
        $data['select_competition']['selected'] = $current_competition->Competition_Date;

        $img = CommonHelper::getCompetitionThumbnail($current_competition);

        $data['image_source'] = CommonHelper::getPluginUrl($img, $this->settings->get('images_dir'));
        $data['theme'] = $current_competition->Theme;

        // Display a warning message if the competition is within one week aka 604800 secs (60*60*24*7) of closing
        $close_date = $query_competitions->getCompetitionCloseDate($current_competition->Competition_Date, $current_competition->Classification, $current_competition->Medium);
        if ($close_date !== null) {
            $close_epoch = strtotime($close_date);
            $time_to_close = $close_epoch - current_time('timestamp');
            if ($time_to_close >= 0 && $time_to_close <= 604800) {
                $data['close'] = $close_date;
            }
        }

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

            $entry = [];
            $entry['id'] = $recs->ID;
            $entry['image']['url'] = home_url($recs->Server_File_Name);
            $entry['image']['title'] = $recs->Title . ' ' . $competition->Classification . ' ' . $competition->Medium;
            $entry['image']['source'] = $photo_helper->getThumbnailUrl($recs->Server_File_Name, '75');
            $entry['title'] = $recs->Title;
            $entry['client_file_name'] = $recs->Client_File_Name;
            $size = getimagesize($this->request->server('DOCUMENT_ROOT') . $recs->Server_File_Name);
            $entry['size']['x'] = $size[0];
            $entry['size']['y'] = $size[1];
            $data['entries'][] = $entry;
        }

        // Don't show the Add button if the max number of images per member reached
        if ($num_rows < $max_entries_per_member_per_comp && $total_entries_submitted < $this->settings->get('club_max_entries_per_member_per_date')) {
            $form->add('add', 'submit', ['label' => 'Add', 'attr' => ['onclick' => 'submit_form("add")']]);
        }
        if ($num_rows > 0) {
            $form->add('delete', 'submit', ['label' => 'Remove', 'attr' => ['onclick' => 'return  confirmSubmit()']]);
            if ($max_entries_per_member_per_comp > 0) {
                $form->add('edit', 'submit', ['label' => 'Edit Title', 'attr' => ['onclick' => 'submit_form("edit")']]);
            }
        }

        return $this->view->fetch('add_entries.html.twig', ['data' => $data, 'form' => $form->createView()]);
    }

    /**
     * Display the eights and higher for a given member ID.
     *
     * @param array  $attr    The shortcode argument list. Allowed arguments:
     *                        - id => The member ID
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     *
     * @internal Shortcode: rps_person_winners
     */
    public function shortcodePersonWinners($attr, $content, $tag)
    {
        $attr = shortcode_atts(['id' => 0, 'images' => 6], $attr);

        $data = $this->model->getPersonWinners($attr['id'], $attr['images']);

        return $this->view->fetch('person-winners.html.twig', $data);
    }

    /**
     * Displays the scores of the current user.
     * By default the scores of the latest season is shown.
     * A drop down with a season list is shown for the user to select.
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @todo: MVC
     */
    public function shortcodeScoresCurrentUser($attr, $content, $tag)
    {
        global $post;
        $query_miscellaneous = $this->container->make('QueryMiscellaneous');
        $season_helper = $this->container->make('SeasonHelper');

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
     * @return string
     *
     * @see Frontend::actionHandleHttpPostRpsUploadEntry
     */
    public function shortcodeUploadImage($attr, $content, $tag)
    {
        global $post;

        // Error messages
        if ($this->settings->has('errmsg')) {
            echo '<div id="errmsg">';
            echo $this->settings->get('errmsg');
            echo '</div>';
        }

        $action = home_url('/' . get_page_uri($post->ID));
        $action .= '/?post=1';
        $medium_subset = "Digital";
        if ($this->request->has('m')) {
            if ($this->request->input('m') == "prints") {
                $medium_subset = "Prints";
            }
        }
        if ($this->request->has('wp_get_referer')) {
            $ref = $this->request->input('wp_get_referer');
        } else {
            $ref = wp_get_referer();
        }

        if ($this->settings->has('formerror')) {
            /** @var \Symfony\Component\Form\FormErrorIterator $error_obj */
            $error_obj = $this->settings->get('formerror');
            $form = $error_obj->getForm();
        } else {
            $entity = new EntityFormUploadEntry();
            $entity->setWpGetReferer($ref);
            $entity->setMediumSubset($medium_subset);
            $form = $this->formFactory->create(new UploadEntryType(), $entity, ['action' => $action, 'attr' => ['id' => 'uploadentry']]);
        }

        return $this->view->fetch('upload.html.twig', ['form' => $form->createView()]);
    }
}
