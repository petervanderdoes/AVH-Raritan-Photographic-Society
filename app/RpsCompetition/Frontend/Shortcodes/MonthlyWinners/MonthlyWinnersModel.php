<?php
namespace RpsCompetition\Frontend\Shortcodes\MonthlyWinners;

use Avh\Network\Session;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Photo\Helper as PhotoHelper;
use RpsCompetition\Season\Helper as SeasonHelper;

/**
 * Class MonthlyWinnersModel
 *
 * @package   RpsCompetition\Frontend\Shortcodes\MonthlyWinners
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class MonthlyWinnersModel
{
    private $photo_helper;
    private $query_competitions;
    private $query_miscellaneous;
    private $season_helper;
    private $session;

    /**
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
     * return an array with the awards up to the maximum number of awrads.
     *
     * @param integer $max_num_awards
     *
     * @return array
     */
    public function getAwardsData($max_num_awards)
    {
        $data = [];
        for ($i = 0; $i < $max_num_awards; $i++) {
            switch ($i) {
                case 0:
                    $data[] = '1st';
                    break;
                case 1:
                    $data[] = '2nd';
                    break;
                case 2:
                    $data[] = '3rd';
                    break;
                default:
                    $data[] = 'HM';
            }
        }

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
        $entries = $this->query_miscellaneous->getWinners($selected_start_date, $selected_end_date);
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
     * Get the monthly winners
     *
     * @param string $selected_season
     * @param string $selected_date
     *
     * @return array
     *
     */
    public function getMonthlyWinners($selected_season, $selected_date)
    {

        $max_num_awards = $this->query_miscellaneous->getMaxAwards($selected_date);

        $data = [];
        $data['selected_season'] = $selected_season;
        $data['selected_date'] = $selected_date;
        $data['is_scored_competitions'] = false;
        $data['thumb_size'] = '75';

        if ($this->isScoredCompetition($selected_date)) {
            $scored_competitions = $this->getScoredCompetitions($selected_season);
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
            $prev_comp = '';
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
                $data['row'][$row]['images'][] = $this->photo_helper->dataPhotoGallery($competition, '75');
            }
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
     * Get the selected date from the session.
     *
     * @return string
     */
    public function getSelectedDate()
    {
        return $this->session->get('monthly_winners_selected_date');
    }

    /**
     * Get the selected season from the session.
     *
     * @return string
     */
    public function getSelectedSeason()
    {
        return $this->session->get('monthly_winners_selected_season');
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
        $data = [];
        $data['action'] = home_url('/' . get_page_uri($post->ID));
        $data['months'] = $months;
        $seasons = $this->season_helper->getSeasons();
        $data['seasons'] = array_combine($seasons, $seasons);

        return $data;
    }
}
