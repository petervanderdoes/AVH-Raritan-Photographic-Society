<?php
/**
 * Created by PhpStorm.
 * User: pdoes
 * Date: 3/4/15
 * Time: 1:04 PM
 */

namespace RpsCompetition\Frontend\Shortcodes\BanquetCurrentUser;

use Avh\Network\Session;
use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Db\QueryBanquet;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Entity\Forms\BanquetCurrentUser as BanquetCurrentUserEntity;
use RpsCompetition\Form\Type\BanquetCurrentUserType;
use RpsCompetition\Season\Helper as SeasonHelper;
use Symfony\Component\Form\FormFactory;

/**
 * Class BanquetCurrentUserModel
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\BanquetCurrentUser
 */
class BanquetCurrentUserModel
{
    private $banquet_entries;
    private $banquet_id_array;
    private $formDisabled;
    private $formFactory;
    private $query_banquet;
    private $query_entries;
    private $query_miscellaneous;
    private $requests;
    private $season_helper;
    private $session;

    /**
     * Constructor
     *
     * @param FormFactory        $formFactory
     * @param SeasonHelper       $season_helper
     * @param QueryMiscellaneous $query_miscellaneous
     * @param QueryBanquet       $query_banquet
     * @param QueryEntries       $query_entries
     * @param IlluminateRequest  $requests
     * @param Session            $session
     */
    public function __construct(
        FormFactory $formFactory,
        SeasonHelper $season_helper,
        QueryMiscellaneous $query_miscellaneous,
        QueryBanquet $query_banquet,
        QueryEntries $query_entries,
        IlluminateRequest $requests,
        Session $session
    ) {

        $this->season_helper = $season_helper;
        $this->query_miscellaneous = $query_miscellaneous;
        $this->query_banquet = $query_banquet;
        $this->query_entries = $query_entries;
        $this->formFactory = $formFactory;
        $this->requests = $requests;
        $this->session = $session;

        $this->formDisabled = false;
    }

    /**
     * Get all data used in the template
     *
     * @return array
     */
    public function getAllData()
    {

        $data = [];
        $season_options = $this->getSeasonsOptions();
        $selected_season = $this->requests->input('form.seasons', end($season_options));

        $scores = $this->getScores($selected_season);

        $this->setupBanquetInformation($selected_season);

        $all_entries = [];
        foreach ($this->banquet_entries as $banquet_entry) {
            $all_entries[] = $banquet_entry->ID;
        }

        // Start building the form
        if (!empty($scores)) {
            $data = $this->getTemplateData($season_options, $selected_season, $scores);
        }

        // Start the form
        global $post;
        $action = home_url('/' . get_page_uri($post->ID));
        $entity = new BanquetCurrentUserEntity();
        $entity->setWpGetReferer(remove_query_arg(['m', 'id'], wp_get_referer()));
        $entity->setAllentries(base64_encode(json_encode($all_entries)));
        $entity->setBanquetids(base64_encode(json_encode($this->banquet_id_array)));
        $entity->setSeasonChoices($season_options);
        $entity->setSeasons($selected_season);
        $form = $this->formFactory->create(
            new BanquetCurrentUserType($entity),
            $entity,
            ['action' => $action, 'attr' => ['id' => 'banquetentries']]
        )
        ;

        $return = [];
        $return['data'] = $data;
        $return ['form'] = $form;

        return $return;
    }

    /**
     * Get the scores for the current user for the given season.
     *
     * @param string $season
     *
     * @return array
     *
     */
    public function getScores($season)
    {
        list ($season_start_date, $season_end_date) = $this->season_helper->getSeasonStartEnd($season);
        $scores = $this->query_miscellaneous->getScoresUser(
            get_current_user_id(),
            $season_start_date,
            $season_end_date
        )
        ;

        return $scores;
    }

    /**
     * Get the Seasons to be used in a select
     *
     * @return array
     */
    public function getSeasonsOptions()
    {
        $seasons = $this->season_helper->getSeasons();

        return array_combine($seasons, $seasons);
    }

    /**
     * Get the data used in the template
     *
     * @param array  $season_options
     * @param string $selected_season
     * @param array  $scores
     *
     * @return array
     */
    public function getTemplateData($season_options, $selected_season, $scores)
    {
        $data = [];
        $data['disabled'] = $this->formDisabled;
        $data['seasons'] = $season_options;
        $data['selected_season'] = $selected_season;
        $data['scores'] = true;

        // Build the list of submitted images
        $comp_count = 0;
        $prev_date = '';
        $prev_medium = '';

        foreach ($scores as $recs) {
            $entry = [];
            if (empty($recs['Award'])) {
                continue;
            }

            $date_parts = explode(' ', $recs['Competition_Date']);
            $date_parts[0] = strftime('%d-%b-%Y', strtotime($date_parts[0]));
            $entry['date'] = $date_parts[0];
            $entry['comp_date'] = $date_parts[0];
            $entry['medium'] = $recs['Medium'];
            $entry['theme'] = $recs['Theme'];
            $entry['title'] = $recs['Title'];
            $entry['score'] = $recs['Score'];
            $entry['award'] = $recs['Award'];
            if ($date_parts[0] != $prev_date) {
                $comp_count += 1;
                $prev_medium = "";
            }

            $entry['image_url'] = home_url($recs['Server_File_Name']);

            if ($prev_date == $entry['date']) {
                $entry['date'] = '';
                $entry['theme'] = '';
            } else {
                $prev_date = $date_parts[0];
            }
            if ($prev_medium == $entry['medium']) {
                $entry['theme'] = '';
            } else {
                $prev_medium = $entry['medium'];
            }
            $entry['score_award'] = '';
            if ($entry['score'] > '') {
                $entry['score_award'] = ' / ' . $entry['score'] . 'pts';
            }
            if ($entry['award'] > '') {
                $entry['score_award'] .= ' / ' . $entry['award'];
            }

            foreach ($this->banquet_entries as $banquet_entry) {

                if (!empty($banquet_entry) && $banquet_entry->Title == $entry['title']) {
                    $entry['checked'] = true;
                    break;
                }
            }

            $entry['entry_id'] = $recs['Entry_ID'];
            $data['entries'][] = $entry;
        }

        return $data;
    }

    /**
     * Set up the banquet information used in the method.
     *
     * @param string $season
     *
     * @return void
     */
    public function setupBanquetInformation($season)
    {
        list ($season_start_date, $season_end_date) = $this->season_helper->getSeasonStartEnd($season);
        $banquet_id = $this->query_banquet->getBanquets($season_start_date, $season_end_date);
        $this->banquet_id_array = [];
        $this->banquet_entries = [];
        if (is_array($banquet_id) && !empty($banquet_id)) {
            foreach ($banquet_id as $record) {
                $this->banquet_id_array[] = $record['ID'];
                if ($record['Closed'] == 'Y') {
                    $this->formDisabled = true;
                }
            }

            $where = 'Competition_ID in (' . implode(
                    ',',
                    $this->banquet_id_array
                ) . ') AND Member_ID = "' . get_current_user_id() . '"';
            /** @var array banquet_entries */
            $this->banquet_entries = $this->query_entries->query(['where' => $where]);
        }

        if (!is_array($this->banquet_entries)) {
            $this->banquet_entries = [];
        }

        return;
    }
}
