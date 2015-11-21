<?php
namespace RpsCompetition\Frontend\Shortcodes\MyEntries;

use Avh\Framework\Network\Session;
use Carbon\Carbon;
use Illuminate\Config\Repository as Settings;
use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Entity\Form\MyEntries as EntityFormMyEntries;
use RpsCompetition\Form\Type\MyEntriesType;
use RpsCompetition\Helpers\CommonHelper;
use RpsCompetition\Helpers\CompetitionHelper;
use RpsCompetition\Helpers\PhotoHelper;
use Symfony\Component\Form\FormFactory;

/**
 * Class MyEntriesModel
 *
 * @package   RpsCompetition\Frontend\Shortcodes\MyEntries
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class MyEntriesModel
{
    private $competition_helper;
    private $form_factory;
    /** @var  integer */
    private $num_rows;
    private $photo_helper;
    private $query_competitions;
    private $query_entries;
    private $request;
    private $session;
    private $settings;

    /**
     * @param QueryCompetitions $query_competitions
     * @param QueryEntries      $query_entries
     * @param PhotoHelper       $photo_helper
     * @param CompetitionHelper $competition_helper
     * @param Session           $session
     * @param FormFactory       $form_factory
     * @param Settings          $settings
     * @param IlluminateRequest $request
     */
    public function __construct(
        QueryCompetitions $query_competitions,
        QueryEntries $query_entries,
        PhotoHelper $photo_helper,
        CompetitionHelper $competition_helper,
        Session $session,
        FormFactory $form_factory,
        Settings $settings,
        IlluminateRequest $request
    ) {
        $this->query_competitions = $query_competitions;
        $this->query_entries = $query_entries;
        $this->photo_helper = $photo_helper;
        $this->competition_helper = $competition_helper;
        $this->session = $session;
        $this->form_factory = $form_factory;
        $this->settings = $settings;
        $this->request = $request;
    }

    /**
     * Get the data and form data for the page My Entries
     *
     * @param string $medium_subset_medium
     *
     * @return array|bool
     */
    public function getMyEntries($medium_subset_medium)
    {

        global $post;

        $open_competitions = $this->getOpenCompetitions($medium_subset_medium);

        if ($open_competitions === []) {
            $return = false;
        } else {
            $open_competitions_options = $this->getOpenCompetitionsOptions($open_competitions);

            $current_competition = $this->getCurrentCompetition($medium_subset_medium, $open_competitions);

            $this->saveSession($medium_subset_medium, $current_competition);

            $data = $this->getTemplateData($current_competition);

            $action = home_url('/' . get_page_uri($post->ID));
            $entity = new EntityFormMyEntries();
            $entity->setWpnonce(wp_create_nonce('avh-rps-myentries'));
            $entity->setSelectComp($current_competition->Competition_Date);
            $entity->setSelectedMedium($current_competition->Medium);
            $entity->setSelectedCompChoices($open_competitions_options);
            $entity->setSelectedMediumChoices($this->competition_helper->getMedium($open_competitions));
            $entity->setClassification($current_competition->Classification);
            /** @var \Symfony\Component\Form\Form $form */
            $form = $this->form_factory->create(
                new MyEntriesType($entity),
                $entity,
                ['action' => $action, 'attr' => ['id' => 'myentries']]
            );

            $this->addFormButtons($current_competition, $form);
            $return = [];
            $return['data'] = $data;
            $return['form'] = $form;
        }

        return $return;
    }

    /**
     * Add the Add/Edit/Delete buttons when needed.
     *
     * @param array|mixed|QueryCompetitions $current_competition
     * @param \Symfony\Component\Form\Form  $form
     */
    private function addFormButtons($current_competition, $form)
    {
        // Retrieve the maximum number of entries per member for this competition
        $max_entries_per_member_per_comp = $this->query_competitions->getCompetitionMaxEntries(
            $current_competition->Competition_Date,
            $current_competition->Classification,
            $current_competition->Medium
        );

        // Retrieve the total number of entries submitted by this member for this competition date
        $total_entries_submitted = $this->query_entries->countEntriesSubmittedByMember(
            get_current_user_id(),
            $current_competition->Competition_Date
        );

        // Don't show the Add button if the max number of images per member reached
        if ($this->num_rows < $max_entries_per_member_per_comp && $total_entries_submitted < $this->settings->get(
                'club_max_entries_per_member_per_date'
            )
        ) {
            $form->add('add', 'submit', ['label' => 'Add', 'attr' => ['onclick' => 'submit_form("add")']]);
        }
        if ($this->num_rows > 0) {
            $form->add('delete', 'submit', ['label' => 'Remove', 'attr' => ['onclick' => 'return  confirmSubmit()']]);
            if ($max_entries_per_member_per_comp > 0) {
                $form->add('edit', 'submit', ['label' => 'Edit Title', 'attr' => ['onclick' => 'submit_form("edit")']]);
            }
        }
    }

    /**
     * Get the Current Competition we are working with.
     *
     * @param string $medium_subset_medium
     * @param array  $open_competitions
     *
     * @return array|mixed|QueryCompetitions
     */
    private function getCurrentCompetition($medium_subset_medium, $open_competitions)
    {
        $current_competition = reset($open_competitions);
        $competition_date = $this->session->get(
            'myentries.' . $medium_subset_medium . '.competition_date',
            mysql2date('Y-m-d', $current_competition->Competition_Date)
        );
        $medium = $this->session->get('myentries.' . $medium_subset_medium . '.medium', $current_competition->Medium);
        $classification = CommonHelper::getUserClassification(get_current_user_id(), $medium);
        $current_competition = $this->query_competitions->getCompetitionByDateClassMedium(
            $competition_date,
            $classification,
            $medium
        );

        return $current_competition;
    }

    /**
     * Get the template data for all the entries of the given competition.
     *
     * @param array|mixed|QueryCompetitions $current_competition
     *
     * @return array
     */
    private function getEntries($current_competition)
    {
        $data = [];
        $entries = $this->query_entries->getEntriesSubmittedByMember(
            get_current_user_id(),
            $current_competition->Competition_Date,
            $current_competition->Classification,
            $current_competition->Medium
        );
        // Build the rows of submitted images
        $this->num_rows = 0;
        /** @var QueryEntries $recs */
        foreach ($entries as $recs) {
            $competition = $this->query_competitions->getCompetitionById($recs->Competition_ID);
            $this->num_rows++;

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
            $data[] = $entry;
        }

        return $data;
    }

    /**
     * Get all the open competitions for the given subset
     *
     * @param string $medium_subset_medium
     *
     * @return array|QueryCompetitions
     */
    private function getOpenCompetitions($medium_subset_medium)
    {
        $open_competitions = $this->query_competitions->getOpenCompetitions(
            get_current_user_id(),
            $medium_subset_medium
        );
        $open_competitions = CommonHelper::arrayMsort(
            $open_competitions,
            ['Competition_Date' => [SORT_ASC], 'Medium' => [SORT_ASC]]
        );

        return $open_competitions;
    }

    /**
     * Get the options for the open competitions.
     * The open competitions array has every competition, for the options we only need the date of the competitions.
     *
     * @param array $open_competitions
     *
     * @return array
     */
    private function getOpenCompetitionsOptions($open_competitions)
    {
        $open_competitions_options = [];
        $previous_date = '';
        foreach ($open_competitions as $open_competition) {
            if ($previous_date == $open_competition->Competition_Date) {
                continue;
            }
            $previous_date = $open_competition->Competition_Date;
            $open_competitions_options[$open_competition->Competition_Date] = strftime(
                                                                                  '%d-%b-%Y',
                                                                                  strtotime(
                                                                                      $open_competition->Competition_Date
                                                                                  )
                                                                              ) . ' ' . $open_competition->Theme;
        }

        return $open_competitions_options;
    }

    /**
     * Get all teh data need to fill the template.
     *
     * @param array|mixed|QueryCompetitions $current_competition
     *
     * @return array
     */
    private function getTemplateData($current_competition)
    {
        $data = [];
        $data['competition_date'] = $current_competition->Competition_Date;
        $data['medium'] = $current_competition->Medium;
        $data['classification'] = $current_competition->Classification;

        $img = CommonHelper::getCompetitionThumbnail($current_competition);

        $data['image_source'] = CommonHelper::getPluginUrl($img, $this->settings->get('images_dir'));
        $data['theme'] = $current_competition->Theme;

        // Display a warning message if the competition is within one week aka 604800 secs (60*60*24*7) of closing
        $close_date = $this->query_competitions->getCompetitionCloseDate(
            $current_competition->Competition_Date,
            $current_competition->Classification,
            $current_competition->Medium
        );
        if ($close_date !== null) {
            // We give a warning 7 days in advance that a competition will close.
            $close_competition_warning_date = Carbon::instance(
                new \DateTime($close_date, new \DateTimeZone('America/New_York'))
            )
                                                    ->subDays(7)
            ;
            if (Carbon::now('America/New_York')
                      ->gte($close_competition_warning_date)
            ) {
                $data['close'] = $close_date;
            }
        }

        $data['entries'] = $this->getEntries($current_competition);

        return $data;
    }

    /**
     * Save the session.
     * We store the Competition Date, Medium and Classification in a session.
     *
     * @param string $medium_subset_medium
     * @param array|mixed|QueryCompetitions $current_competition
     */
    private function saveSession($medium_subset_medium, $current_competition)
    {
        $this->session->set('myentries.subset', $medium_subset_medium);
        $this->session->set(
            'myentries.' . $medium_subset_medium . '.competition_date',
            $current_competition->Competition_Date
        );
        $this->session->set('myentries.' . $medium_subset_medium . '.medium', $current_competition->Medium);
        $this->session->set(
            'myentries.' . $medium_subset_medium . '.classification',
            $current_competition->Classification
        );
    }
}
