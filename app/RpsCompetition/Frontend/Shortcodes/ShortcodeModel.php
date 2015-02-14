<?php

namespace RpsCompetition\Frontend\Shortcodes;

use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Photo\Helper as PhotoHelper;
use RpsCompetition\Season\Helper as SeasonHelper;

class ShortcodeModel
{
    private $photo_helper;
    private $query_competitions;
    private $query_entries;
    private $query_miscellaneous;
    private $season_helper;

    /**
     * Constructor
     *
     * @param QueryCompetitions  $query_competitions
     * @param QueryEntries       $query_entries
     * @param QueryMiscellaneous $query_miscellaneous
     * @param PhotoHelper        $photo_helper
     * @param SeasonHelper       $season_helper
     */
    public function __construct(QueryCompetitions $query_competitions, QueryEntries $query_entries, QueryMiscellaneous $query_miscellaneous, PhotoHelper $photo_helper, SeasonHelper $season_helper)
    {
        $this->query_competitions = $query_competitions;
        $this->query_entries = $query_entries;
        $this->query_miscellaneous = $query_miscellaneous;
        $this->photo_helper = $photo_helper;
        $this->season_helper = $season_helper;
    }

    /**
     * return an array with the awards up to the maximum number of awrads.
     *
     * @param integer $max_num_awards
     *
     * @return array
     */
    public function getAwardsData($max_num_awards)
    {
        $data = array();
        for ($i = 0; $i < $max_num_awards; $i++) {
            switch ($i) {
                case 0:
                    $data[] = "1st";
                    break;
                case 1:
                    $data[] = "2nd";
                    break;
                case 2:
                    $data[] = "3rd";
                    break;
                default:
                    $data[] = "HM";
            }
        }

        return $data;
    }

    /**
     * Collect needed data to render the Category Winners
     *
     * @param string $class
     * @param array  $entries
     * @param string $thumb_size
     *
     * @return array
     */
    public function getCategoryWinners($class, $entries, $thumb_size)
    {
        $data = array();
        $data['class'] = $class;
        $data['records'] = $entries;
        $data['thumb_size'] = $thumb_size;
        $data['images'] = array();
        foreach ($data['records'] as $recs) {
            $data['images'][] = $this->dataPhotoGallery($recs, $data['thumb_size']);
        }

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
        $images = array();
        foreach ($entries as $entry) {
            $images[] = $this->photo_helper->getThumbnailUrl($entry->Server_File_Name, 'fb_thumb');
        }

        return array('images' => $images);
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
        $data = array();
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

        $data['images'] = array();
        if (is_array($data['entries'])) {
            // Iterate through all the award winners and display each thumbnail in a grid
            /** @var QueryEntries $entry */
            foreach ($data['entries'] as $entry) {
                $data['images'][] = $this->dataPhotoMasonry($entry, $data['thumb_size']);
            }
        }

        return $data;
    }

    /**
     * Get the monthly winners
     *
     * @param string $selected_season
     * @param string $selected_date
     * @param array  $scored_competitions
     *
     * @return array
     */
    public function getMonthlyWinners($selected_season, $selected_date, $scored_competitions)
    {

        $max_num_awards = $this->query_miscellaneous->getMaxAwards($selected_date);

        $data = array();
        $data['selected_season'] = $selected_season;
        $data['selected_date'] = $selected_date;
        $data['is_scored_competitions'] = false;
        $data['thumb_size'] = '75';

        if (is_array($scored_competitions) && (!empty($scored_competitions))) {
            $months = [];
            $themes = [];
            $data['is_scored_competitions'] = true;
            foreach ($scored_competitions as $competition) {
                $date_object = new \DateTime($competition->Competition_Date);
                $key = $date_object->format('Y-m-d');
                $months[$key] = $date_object->format('F') . ': ' . $competition->Theme;
                $themes[$key] = $competition->Theme;
            }
            $data['month_season_form'] = $this->dataMonthAndSeasonSelectionForm($months);
            $date = new \DateTime($selected_date);
            $data['date_text'] = $date->format('F j, Y');
            $data['count_entries'] = $this->query_miscellaneous->countAllEntries($selected_date);
            $data['theme_name'] = $themes[$selected_date];
            $data['winners'] = true;
            $data['max_awards'] = $max_num_awards;
            $data['awards'] = $this->getAwardsData($max_num_awards);
            $award_winners = $this->query_miscellaneous->getWinners($selected_date);
            $row = 0;
            $prev_comp = "";
            foreach ($award_winners as $competition) {
                $comp = $competition->ID;
                // If we're at the end of a row, finish off the row and get ready for the next one
                if ($prev_comp != $comp) {
                    $prev_comp = $comp;
                    // Initialize the new row
                    $row++;
                    $data['row'][$row]['competition']['classification'] = $competition->Classification;
                    $data['row'][$row]['competition']['medium'] = $competition->Medium;
                }
                // Display this thumbnail in the the next available column
                $data['row'][$row]['images'][] = $this->dataPhotoGallery($competition, '75');
            }
        }

        return $data;
    }

    /**
     * Get given amount of random images for the given user.
     *
     * @param integer $user_id
     * @param integer $amount_of_images
     *
     * @return array
     */
    public function getPersonWinners($user_id, $amount_of_images)
    {
        $entries = $this->query_miscellaneous->getEightsAndHigherPerson($user_id);
        $entries_id = array_rand($entries, $amount_of_images);
        $data = array();
        $data['thumb_size'] = '150w';
        $data['records'] = array();
        foreach ($entries_id as $key) {
            $data['entries'][] = $entries[$key];
        }
        foreach ($data['entries'] as $entry) {
            $data['images'][] = $this->dataPhotoMasonry($entry, $data['thumb_size']);
        }

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
     * Get the winner for the given class, award and date.
     *
     * @param string $class
     * @param string $award
     * @param string $date
     *
     * @return QueryMiscellaneous
     */
    public function getWinner($class, $award, $date)
    {
        $competition_date = date('Y-m-d H:i:s', strtotime($date));
        $award_map = array('1' => '1st', '2' => '2nd', '3' => '3rd', 'H' => 'HM');

        return $this->query_miscellaneous->getWinner($competition_date, $award_map[$award], $class);
    }

    /**
     * Retrieve the award winners for the given date.
     *
     * @param string $selected_date
     *
     * @return array
     */
    public function getWinners($selected_date)
    {
        return $this->query_miscellaneous->getWinners($selected_date);
    }

    /**
     * Get all entries between the given dates.
     *
     * @param string $selected_start_date
     * @param string $selected_end_date
     *
     * @return array
     */
    public function getallEntries($selected_start_date, $selected_end_date)
    {
        return $this->query_miscellaneous->getAllEntries($selected_start_date, $selected_end_date);
    }

    /**
     * Collect needed data to render the Month and Season select form
     *
     * @param array $months
     *
     * @return array
     */
    private function dataMonthAndSeasonSelectionForm($months)
    {
        global $post;
        $data = array();
        $data['action'] = home_url('/' . get_page_uri($post->ID));
        $data['months'] = $months;
        $seasons = $this->season_helper->getSeasons();
        $data['seasons'] = array_combine($seasons, $seasons);

        return $data;
    }

    /**
     * Collect needed data to render a photo in masonry style.
     *
     * @param QueryEntries $record
     * @param string       $thumb_size
     *
     * @return array<string,string|array>
     */
    private function dataPhotoGallery($record, $thumb_size)
    {

        $data = array();
        $user_info = get_userdata($record->Member_ID);
        $title = $record->Title;
        $last_name = $user_info->user_lastname;
        $first_name = $user_info->user_firstname;
        $data['award'] = $record->Award;
        $data['url_large '] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, '800');
        $data['url_thumb'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, $thumb_size);
        $data['dimensions'] = $this->photo_helper->getThumbnailImageSize($record->Server_File_Name, $thumb_size);
        $data['title'] = $title . ' by ' . $first_name . ' ' . $last_name;
        $data['caption'] = $this->photo_helper->dataPhotoCredit($title, $first_name, $last_name);

        return $data;
    }

    /**
     * Collect needed data to render a photo in masonry style.
     *
     * @param QueryEntries $record
     * @param string       $thumb_size
     *
     * @return array<string,string|array>
     */
    private function dataPhotoMasonry($record, $thumb_size)
    {
        $data = array();
        $user_info = get_userdata($record->Member_ID);
        $title = $record->Title;
        $last_name = $user_info->user_lastname;
        $first_name = $user_info->user_firstname;
        $data['url_large '] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, '800');
        $data['url_thumb'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, $thumb_size);
        $data['dimensions'] = $this->photo_helper->getThumbnailImageSize($record->Server_File_Name, $thumb_size);
        $data['caption'] = $this->photo_helper->dataPhotoCredit($title, $first_name, $last_name);

        return $data;
    }
}
