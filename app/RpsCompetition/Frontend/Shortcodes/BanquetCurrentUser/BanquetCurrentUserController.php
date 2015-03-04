<?php

namespace RpsCompetition\Frontend\Shortcodes\BanquetEntries;


class BanquetCurrentUserController  extends Controller{
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

        $data=[];
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
        if (!empty($scores)) {

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

                echo "<td align=\"left\" valign=\"top\" class=\"{$row_style}\"><a href=\"$image_url\" rel=\"lightbox[{$comp_date}]\" title=\"" . htmlentities(
                        $title
                    ) . " / {$comp_date} / {$medium}{$score_award}\">" . htmlentities(
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
            echo '<input type="hidden" name="wp_get_referer" value="' . remove_query_arg(
                    ['m', 'id'],
                    wp_get_referer()
                ) . '" />';
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
}
