<?php
namespace RpsCompetition\Frontend\Shortcodes;

use Avh\Html\FormBuilder;
use Avh\Html\HtmlBuilder;
use RpsCompetition\Application;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Libs\Controller;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class ShortcodeController
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes
 */
final class ShortcodeController extends Controller
{
    /** @var FormBuilder */
    private $form_builder;
    /** @var \Symfony\Component\Form\FormFactory */
    private $form_factory;
    /** @var HtmlBuilder */
    private $html;
    /** @var ShortcodeModel */
    private $model;
    /** @var ShortcodeView */
    private $view;

    /**
     * Constructor
     *
     * @param Application $container
     */
    public function __construct(Application $container)
    {
        $this->setContainer($container);
        $this->setSettings($this->container->make('Settings'));
        $this->setRpsdb($this->container->make('RpsDb'));
        $this->setRequest($this->container->make('IlluminateRequest'));
        $this->setSession($this->container->make('Session'));
        $this->form_factory = $this->container->make('formFactory');
        $template = [];
        $template[] = $this->settings->get('template_dir');
        $template[] = $this->settings->get('template_dir') . '/social-networks';
        $this->setTemplateEngine(
            $this->container->make(
                'Templating',
                ['template_dir' => $template, 'cache_dir' => $this->settings->get('upload_dir') . '/twig-cache/']
            )
        )
        ;
        $this->view = $this->container->make('ShortcodeView');

        $this->html = $this->container->make('HtmlBuilder');
        $this->form_builder = new FormBuilder($this->html);

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
            $date_parts = explode(' ', $recs['Competition_Date']);
            list (, $comp_month, $comp_day) = explode('-', $date_parts[0]);
            $comp_dates[$date_parts[0]] = sprintf('%d/%d', $comp_month, $comp_day);
            $comp_max_entries[$date_parts[0]] = $recs['Max_Entries'];
            $total_max_entries += $recs['Max_Entries'];
            $comp_num_judges[$date_parts[0]] = $recs['Num_Judges'];
        }

        $club_competition_results_unsorted = $query_miscellaneous->getCompetitionResultByDate(
            $season_start_date,
            $season_end_date
        )
        ;
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
                $date_parts = explode(' ', $recs['Competition_Date']);
                $this_date = $date_parts[0];
                $member = $recs['Username'];
                $last_name = $recs['LastName'];
                $first_name = $recs['FirstName'];
                $score = $recs['Score'];
                $award = $recs['Award'];
                $special_event = $recs['Special_Event'];

                // Is this the beginning of the next member's scores?
                if ($member != $prev_member || $classification != $prev_class || $medium != $prev_medium) {
                    $row_count++;
                    $row_style = $row_count % 2 == 1 ? 'odd_row' : 'even_row';

                    // Don't do anything yet if this is the very first member, otherwise, output all
                    // the accumulated scored for the member we just passed.
                    if ($prev_member != '') {
                        // Display the members name and classification
                        echo '<tr>';
                        echo '<td align="left" class="' . $row_style . '">' . $prev_fname . ' ' . $prev_lname . '</td>';
                        echo '<td align="center" class="' . $row_style . '">' . substr($prev_class, 0, 1) . '</td>';

                        // Iterate through all the accumulated scores for this member
                        foreach ($member_scores as $score_key => $score_array) {
                            // Print the scores for the submitted entries for this month
                            $total_score_array = count($score_array);
                            for ($i = 0; $i < $total_score_array; $i++) {
                                echo '<td align="center" class="' . $row_style . '">$score_array[$i]</td>';
                            }
                            // Pad the unused entries for this member for this month
                            for ($i = 0; $i < $comp_max_entries[$score_key] - $total_score_array; $i++) {
                                echo '<td align="center" class="' . $row_style . '">&nbsp;</td>';
                            }
                        }

                        // Display the members annual average score
                        if ($total_score > 0 && $num_scores > 0) {
                            echo '<td align="center" class="' . $row_style . '">' . sprintf(
                                    '%3.1f',
                                    $total_score / $num_scores
                                ) . '</td>';
                        } else {
                            echo '<td align="center" class="' . $row_style . '">&nbsp;</td>';
                        }
                        echo '</tr>';
                    }

                    // Now that we've just output the scores for the previous member, are we at the
                    // beginning of a new classification, but not at the end of the current medium?
                    // If so, draw a horizonal line to mark the beginning of a new classification
                    if ($classification != $prev_class && $medium == $prev_medium) {
                        // echo "<tr class=\"horizontal_separator\">";
                        echo '<tr>';
                        echo '<td colspan="' . ($total_max_entries + 3) . '" class="horizontal_separator"></td>';
                        echo '</tr>';
                    }

                    // Are we at the beginning of a new medium?
                    // If so, output a new set of column headings
                    if ($medium != $prev_medium) {
                        // Draw a horizontal line to end the previous medium
                        if ($prev_medium != '') {
                            echo '<tr class="horizontal_separator">';
                            // echo "<td colspan=\"" . (count($comp_dates) * 2 + 3) .
                            // "\" class=\"horizontal_separator\"></td>";
                            echo '<td colspan="' . ($total_max_entries + 3) . '" class="horizontal_separator"></td>';
                            echo '</tr>';
                        }

                        // Display the category title
                        echo '<tr><td align="left" class="form_title" colspan="' . ($total_max_entries + 3) . '">';
                        echo $medium . ' scores for ' . $selected_season . ' season';
                        echo '</td></tr>' . "\n";

                        // Display the first row column headers
                        echo '<tr><th class="form_frame_header" colspan="2">&nbsp;</th>';
                        foreach ($comp_dates as $comp_dates_key => $comp_dates_date) {
                            echo '<th class="form_frame_header" colspan="' . $comp_max_entries[$comp_dates_key] . '">' . $comp_dates_date . '</th>';
                        }
                        echo '<th class="form_frame_header">&nbsp;</th>';
                        echo '</tr>';
                        // Display the second row column headers
                        echo '<tr>';
                        echo '<th class="form_frame_header">Member</th>';
                        echo '<th class="form_frame_header">Cl.</th>';
                        foreach ($comp_dates as $comp_dates_key => $comp_dates_date) {
                            for ($i = 1; $i <= $comp_max_entries[$comp_dates_key]; $i++) {
                                echo '<th class="form_frame_header">' . $i . '</th>';
                            }
                        }
                        echo '<th class="form_frame_header">Avg</th>';
                        echo '</tr>';
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
                        $num_scores++;
                    }
                }
                // Apply the award as a superscript to the score
                if ($award != '') {
                    $score = '&nbsp;&nbsp;' . $score . '<SUP>&nbsp;' . $award_map[$award] . '</SUP>';
                }
                // Store the score in the appropriate array
                $member_scores[$this_date][] = $score;
            }

            // Output the last remaining row of the table that hasn't been displayed yet
            $row_count++;
            $row_style = $row_count % 2 == 1 ? 'odd_row' : 'even_row';
            // Display the members name and classification
            echo '<tr>';
            echo '<td align="left" class="' . $row_style . '">' . $first_name . ' ' . $last_name . '</td>';
            echo '<td align="center" class="' . $row_style . '">' . substr($classification, 0, 1) . '</td>';
            // Iterate through all the accumulated scores for this member
            foreach ($member_scores as $key => $score_array) {
                // Print the scores for the submitted entries for this month
                $total_score_array = count($score_array);
                for ($i = 0; $i < $total_score_array; $i++) {
                    echo '<td align="center" class="' . $row_style . '">' . $score_array[$i] . '</td>';
                }
                // Pad the unused entries for this member for this month
                for ($i = 0; $i < $comp_max_entries[$key] - $total_score_array; $i++) {
                    echo '<td align="center" class="' . $row_style . '">&nbsp;</td>';
                }
            }

            // Display the members annual average score
            if ($total_score > 0 && $num_scores > 0) {
                echo '<td align="center" class="' . $row_style . '">' . sprintf(
                        '%3.1f',
                        $total_score / $num_scores
                    ) . '</td>';
            } else {
                echo '<td align="center" class="' . $row_style . '">&nbsp;</td>';
            }
            echo '</tr>';

            // We're all done
            echo '</table>';
        }
        unset($query_competitions, $query_miscellaneous, $season_helper);
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
}
