<?php

namespace RpsCompetition\Frontend\Shortcodes\MonthlyEntries;

use Avh\Network\Session;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Photo\Helper as PhotoHelper;
use RpsCompetition\Season\Helper as SeasonHelper;

/**
 * Class MonthlyEntriesModel
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\MonthlyEntries
 */
class MonthlyEntriesModel
{
    private $photo_helper;
    private $query_competitions;
    private $query_miscellaneous;
    private $season_helper;
    private $session;

    /**
     * Constructor
     *
     * @param Session            $session
     * @param QueryCompetitions  $query_competitions
     * @param QueryMiscellaneous $query_miscellaneous
     * @param PhotoHelper        $photo_helper
     * @param SeasonHelper       $season_helper
     */
    public function __construct(
        Session $session,
        QueryCompetitions $query_competitions,
        QueryMiscellaneous $query_miscellaneous,
        PhotoHelper $photo_helper,
        SeasonHelper $season_helper
    ) {

        $this->session = $session;
        $this->query_competitions = $query_competitions;
        $this->query_miscellaneous = $query_miscellaneous;
        $this->photo_helper = $photo_helper;
        $this->season_helper = $season_helper;
    }

    /**
     * Collect needed data to render the Month and Season select form
     *
     * @param array $months
     *
     * @return array
     */
    public function dataMonthAndSeasonSelectionForm($months)
    {
        global $post;
        $data = [];
        $data['action'] = home_url('/' . get_page_uri($post->ID));
        $data['months'] = $months;
        $seasons = $this->season_helper->getSeasons();
        $data['seasons'] = array_combine($seasons, $seasons);

        return $data;
    }

    /**
     * Get the data to display the Facebook thumbs
     *
     * @param $selected_start_date
     * @param $selected_end_date
     *
     * @return array
     */
    public function getFacebookData($selected_start_date, $selected_end_date)
    {
        $entries = $this->query_miscellaneous->getAllEntries($selected_start_date, $selected_end_date);
        $data = $this->getFacebookThumbs($entries);

        return $data;
    }

    /**
     * Get the Facebook thumbs for the Category Winners Page.
     *
     * @param array $entries
     *
     * @return array
     */
    public function getFacebookThumbs($entries)
    {
        $images = [];
        foreach ($entries as $entry) {
            $images[] = $this->photo_helper->getThumbnailUrl($entry->Server_File_Name, 'fb_thumb');
        }

        return ['images' => $images];
    }

    /**
     * Get the monthly entries
     *
     * @param string $selected_season
     * @param string $selected_date
     * @param array  $scored_competitions
     *
     * @return array
     */
    public function getMonthlyEntries($selected_season, $selected_date, $scored_competitions)
    {
        $data = [];
        $data['selected_season'] = $selected_season;
        $data['selected_date'] = $selected_date;
        $data['is_scored_competitions'] = false;
        $data['thumb_size'] = '150w';

        if (is_array($scored_competitions) && (!empty($scored_competitions))) {
            $months = [];
            $themes = [];
            foreach ($scored_competitions as $competition) {
                $date_object = new \DateTime($competition->Competition_Date);
                $key = $date_object->format('Y-m-d');
                $months[$key] = $date_object->format('F') . ': ' . $competition->Theme;
                $themes[$key] = $competition->Theme;
            }
            $data['month_season_form'] = $this->dataMonthAndSeasonSelectionForm($months);
            $date = new \DateTime($selected_date);
            $data['date_text'] = $date->format('F j, Y');
            $data['theme_name'] = $themes[$selected_date];
            $data['entries'] = $this->query_miscellaneous->getAllEntries($selected_date, $selected_date);
            $data['count_entries'] = count($data['entries']);
            $data['is_scored_competitions'] = true;
        }

        $data['images'] = [];
        if (is_array($data['entries'])) {
            // Iterate through all the award winners and display each thumbnail in a grid
            foreach ($data['entries'] as $entry) {
                $data['images'][] = $this->photo_helper->dataPhotoMasonry($entry, $data['thumb_size']);
            }
        }

        return $data;
    }

    /**
     * Get the data to display the Monthly Entries
     *
     * @param string $selected_date
     *
     * @return array
     */
    public function getMonthlyEntriesData($selected_date)
    {
        $selected_season = $this->session->get('monthly_entries_selected_season');
        $scored_competitions = $this->getScoredCompetitions($selected_season, $selected_date, $selected_season);
        $data = $this->getMonthlyEntries($selected_season, $selected_date, $scored_competitions);

        return $data;
    }

    /**
     * Get the scored competitions for the given season
     *
     * @param string $season
     *
     * @return array
     */
    public function getScoredCompetitions($season)
    {
        list ($season_start_date, $season_end_date) = $this->season_helper->getSeasonStartEnd($season);

        return $this->query_competitions->getScoredCompetitions($season_start_date, $season_end_date);
    }

    /**
     * Get the selected date from the session.
     *
     * @return string
     */
    public function getSelectedDate()
    {
        return $this->session->get('monthly_entries_selected_date');
    }

    /**
     * Check if the given date has scored competitions.
     *
     * @param $competition_date
     *
     * @return bool
     */
    public function isScoredCompetition($competition_date)
    {
        $return = $this->query_competitions->getScoredCompetitions($competition_date);

        return (is_array($return) && !empty($return));
    }
}
