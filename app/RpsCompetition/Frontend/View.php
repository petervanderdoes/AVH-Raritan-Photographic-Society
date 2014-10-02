<?php
namespace RpsCompetition\Frontend;

use Avh\Html\FormBuilder;
use Avh\Html\HtmlBuilder;
use Illuminate\Http\Request;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Photo\Helper as PhotoHelper;
use RpsCompetition\Season\Helper as SeasonHelper;
use RpsCompetition\Settings;

class View
{
    private $form_builder;
    private $html_builder;
    private $photo_helper;
    private $request;
    private $rpsdb;
    private $season_helper;
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
        $this->html_builder = new HtmlBuilder();
        $this->form_builder = new FormBuilder($this->html_builder);
        $this->request = $request;
        $this->photo_helper = new PhotoHelper($this->settings, $this->request, $this->rpsdb);
        $this->season_helper = new SeasonHelper($this->settings, $this->rpsdb);
    }

    /**
     * Display the form for selecting the month and season.
     *
     * @param string  $selected_season
     * @param string  $selected_date
     * @param boolean $is_scored_competitions
     * @param array   $months
     * @param bool    $echo
     *
     * @return string|void
     */
    public function displayMonthAndSeasonSelectionForm($selected_season, $selected_date, $is_scored_competitions, $months, $echo = false)
    {
        global $post;
        $output = '<script type="text/javascript">';
        $output .= 'function submit_form(control_name) {' . "\n";
        $output .= '	document.month_season_form.submit_control.value = control_name;' . "\n";
        $output .= '	document.month_season_form.submit();' . "\n";
        $output .= '}' . "\n";
        $output .= '</script>';

        $action = home_url('/' . get_page_uri($post->ID));
        $output .= $this->form_builder->open($action, array('name' => 'month_season_form'));
        $output .= $this->form_builder->hidden('submit_control');
        $output .= $this->form_builder->hidden('selected_season', $selected_season);
        $output .= $this->form_builder->hidden('selected_date', $selected_date);

        if ($is_scored_competitions) {
            // Drop down list for months
            $output .= $this->getMonthsDropdown($months, $selected_date);
        } else {
            $output .= 'No scored competitions this season. ';
        }

        // Drop down list for season
        $output .= $this->season_helper->getSeasonDropdown($selected_season);
        $output .= $this->form_builder->close();

        if ($echo === true) {
            echo $output;
        } else {
            return $output;
        }
    }

    /**
     * Display a photo in masonry style.
     *
     * @param QueryEntries $record
     * @param bool         $echo
     *
     * @return string|void
     */
    public function displayPhotoMasonry($record, $echo = false)
    {
        $user_info = get_userdata($record->Member_ID);
        $title = $record->Title;
        $last_name = $user_info->user_lastname;
        $first_name = $user_info->user_firstname;
        // Display this thumbnail in the the next available column
        $output = '';
        $output .= $this->html_builder->element('figure', array('class' => 'gallery-item-masonry masonry-150'));
        $output .= $this->html_builder->element('div', array('class' => 'gallery-item-content'));
        $output .= $this->html_builder->element('div', array('class' => 'gallery-item-content-images'));
        $output .= $this->html_builder->element('a', array('href' => $this->photo_helper->rpsGetThumbnailUrl($record, 800), 'title' => $title . ' by ' . $first_name . ' ' . $last_name, 'rel' => 'rps-entries'));
        $output .= $this->html_builder->image($this->photo_helper->rpsGetThumbnailUrl($record, '150w'));
        $output .= '</a>';
        $output .= '</div>';
        $caption = "${title}<br /><span class='wp-caption-credit'>Credit: ${first_name} ${last_name}";
        $output .= $this->html_builder->element('figcaption', array('class' => 'wp-caption-text showcase-caption')) . wptexturize($caption) . "</figcaption>\n";
        $output .= '</div>';

        $output .= '</figure>' . "\n";

        if ($echo === true) {
            echo $output;
        } else {
            return $output;
        }
    }

    /**
     * Display a dropdown for the given months
     *
     * @param array   $months
     * @param string  $selected_month
     * @param boolean $echo
     *
     * @return string|void
     */
    private function getMonthsDropdown($months, $selected_month, $echo = false)
    {

        $output = $this->form_builder->select('new_month', $months, $selected_month, array('onChange' => 'submit_form("new_month")'));

        if ($echo === true) {
            echo $output;
        } else {
            return $output;
        }
    }
}