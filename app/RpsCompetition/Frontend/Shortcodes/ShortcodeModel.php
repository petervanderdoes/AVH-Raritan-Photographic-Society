<?php
namespace RpsCompetition\Frontend\Shortcodes;

use Avh\Network\Session;
use RpsCompetition\Competition\Helper as CompetitionHelper;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Photo\Helper as PhotoHelper;
use RpsCompetition\Season\Helper as SeasonHelper;
use RpsCompetition\Settings;
use Symfony\Component\Form\FormFactory;

/**
 * Class ShortcodeModel
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes
 */
class ShortcodeModel
{
    /** @var CompetitionHelper */
    private $competition_helper;
    /** @var FormFactory */
    private $form_factory;
    /** @var PhotoHelper */
    private $photo_helper;
    /** @var QueryCompetitions */
    private $query_competitions;
    /** @var QueryEntries */
    private $query_entries;
    /** @var QueryMiscellaneous */
    private $query_miscellaneous;
    /** @var SeasonHelper */
    private $season_helper;
    /** @var Session */
    private $session;
    /** @var Settings */
    private $settings;

    /**
     * Constructor
     *
     * @param QueryCompetitions  $query_competitions
     * @param QueryEntries       $query_entries
     * @param QueryMiscellaneous $query_miscellaneous
     * @param PhotoHelper        $photo_helper
     * @param SeasonHelper       $season_helper
     * @param CompetitionHelper  $competition_helper
     * @param Session            $session
     * @param FormFactory        $form_factory
     * @param Settings           $settings
     */
    public function __construct(
        QueryCompetitions $query_competitions,
        QueryEntries $query_entries,
        QueryMiscellaneous $query_miscellaneous,
        PhotoHelper $photo_helper,
        SeasonHelper $season_helper,
        CompetitionHelper $competition_helper,
        Session $session,
        FormFactory $form_factory,
        Settings $settings
    ) {
        $this->query_competitions = $query_competitions;
        $this->query_entries = $query_entries;
        $this->query_miscellaneous = $query_miscellaneous;
        $this->photo_helper = $photo_helper;
        $this->season_helper = $season_helper;
        $this->competition_helper = $competition_helper;
        $this->session = $session;
        $this->form_factory = $form_factory;
        $this->settings = $settings;
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
     * @param array  $scored_competitions
     *
     * @return array
     */
    public function getMonthlyWinners($selected_season, $selected_date, $scored_competitions)
    {

        $max_num_awards = $this->query_miscellaneous->getMaxAwards($selected_date);

        $data = [];
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
                $data['row'][$row]['images'][] = $this->dataPhotoGallery($competition, '75');
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
        $award_map = ['1' => '1st', '2' => '2nd', '3' => '3rd', 'H' => 'HM'];

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

        $data = [];
        $user_info = get_userdata($record->Member_ID);
        $title = $record->Title;
        $last_name = $user_info->user_lastname;
        $first_name = $user_info->user_firstname;
        $data['award'] = $record->Award;
        $data['url_large'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, '800');
        $data['url_thumb'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, $thumb_size);
        $data['dimensions'] = $this->photo_helper->getThumbnailImageSize($record->Server_File_Name, $thumb_size);
        $data['title'] = $title . ' by ' . $first_name . ' ' . $last_name;
        $data['caption'] = $this->photo_helper->dataPhotoCredit($title, $first_name, $last_name);

        return $data;
    }
}
