<?php

namespace RpsCompetition\Frontend\Shortcodes\MyEntries;

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

class MyEntriesModel
{
    /**
     * @param QueryCompetitions  $query_competitions
     * @param QueryEntries       $query_entries
     * @param QueryMiscellaneous $query_miscellaneous
     * @param PhotoHelper        $photo_helper
     * @param SeasonHelper       $season_helper
     * @param CompetitionHelper  $competition_helper
     * @param Session            $session
     * @param FormFactory        $formFactory
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
}
