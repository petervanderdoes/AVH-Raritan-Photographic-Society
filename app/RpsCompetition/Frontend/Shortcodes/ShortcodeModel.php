<?php

namespace RpsCompetition\Frontend\Shortcodes;

use Avh\Network\Session;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Competition\Helper as CompetitionHelper;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Entity\Forms\MyEntries as EntityFormMyEntries;
use RpsCompetition\Form\Type\MyEntriesType;
use RpsCompetition\Photo\Helper as PhotoHelper;
use RpsCompetition\Season\Helper as SeasonHelper;
use RpsCompetition\Settings;
use Symfony\Component\Form\FormFactory;

/**
 * Class ShortcodeModel
 *
 * @package RpsCompetition\Frontend\Shortcodes
 */
class ShortcodeModel
{
    private $competition_helper;
    /** @var  FormFactory */
    private $formFactory;
    private $photo_helper;
    private $query_competitions;
    private $query_entries;
    private $query_miscellaneous;
    private $season_helper;
    /**
     * @var Session
     */
    private $session;
    /**
     * @var Settings
     */
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
     * @param FormFactory        $formFactory
     */
    public function __construct(
        QueryCompetitions $query_competitions,
        QueryEntries $query_entries,
        QueryMiscellaneous $query_miscellaneous,
        PhotoHelper $photo_helper,
        SeasonHelper $season_helper,
        CompetitionHelper $competition_helper,
        Session $session,
        FormFactory $formFactory,
        Settings $settings
    ) {
        $this->query_competitions = $query_competitions;
        $this->query_entries = $query_entries;
        $this->query_miscellaneous = $query_miscellaneous;
        $this->photo_helper = $photo_helper;
        $this->season_helper = $season_helper;
        $this->competition_helper = $competition_helper;
        $this->session = $session;
        $this->formFactory = $formFactory;
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
        $data = [];
        $data['class'] = $class;
        $data['records'] = $entries;
        $data['thumb_size'] = $thumb_size;
        $data['images'] = [];
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
     * Get the data and form data for the page My Entries
     *
     * @param string $medium_subset_medium
     *
     * @return array
     */
    public function getMyEntries($medium_subset_medium)
    {

        global $post;

        $open_competitions = $this->query_competitions->getOpenCompetitions(get_current_user_id(), $medium_subset_medium);
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
        $current_competition = $this->query_competitions->getCompetitionByDateClassMedium($competition_date, $classification, $medium);

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
        $entity->setSelectedMedium($this->competition_helper->getMedium($open_competitions));
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
        $close_date = $this->query_competitions->getCompetitionCloseDate($current_competition->Competition_Date, $current_competition->Classification, $current_competition->Medium);
        if ($close_date !== null) {
            $close_epoch = strtotime($close_date);
            $time_to_close = $close_epoch - current_time('timestamp');
            if ($time_to_close >= 0 && $time_to_close <= 604800) {
                $data['close'] = $close_date;
            }
        }

        // Retrieve the maximum number of entries per member for this competition
        $max_entries_per_member_per_comp = $this->query_competitions->getCompetitionMaxEntries($current_competition->Competition_Date, $current_competition->Classification, $current_competition->Medium);

        // Retrieve the total number of entries submitted by this member for this competition date
        $total_entries_submitted = $this->query_entries->countEntriesSubmittedByMember(get_current_user_id(), $current_competition->Competition_Date);

        $entries = $this->query_entries->getEntriesSubmittedByMember(get_current_user_id(), $current_competition->Competition_Date, $current_competition->Classification, $current_competition->Medium);
        // Build the rows of submitted images
        $num_rows = 0;
        /** @var QueryEntries $recs */
        foreach ($entries as $recs) {
            $competition = $this->query_competitions->getCompetitionById($recs->Competition_ID);
            $num_rows += 1;

            $entry = [];
            $entry['id'] = $recs->ID;
            $entry['image']['url'] = home_url($recs->Server_File_Name);
            $entry['image']['title'] = $recs->Title . ' ' . $competition->Classification . ' ' . $competition->Medium;
            $entry['image']['source'] = $this->photo_helper->getThumbnailUrl($recs->Server_File_Name, '75');
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

        $return = [];
        $return['data'] = $data;
        $return ['form'] = $form;

        return $return;
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
        $data = [];
        $data['thumb_size'] = '150w';
        $data['records'] = [];
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
        $data = [];
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
