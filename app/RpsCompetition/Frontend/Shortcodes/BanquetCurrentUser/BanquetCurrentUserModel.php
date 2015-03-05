<?php
/**
 * Created by PhpStorm.
 * User: pdoes
 * Date: 3/4/15
 * Time: 1:04 PM
 */

namespace RpsCompetition\Frontend\Shortcodes\BanquetCurrentUser;

use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Db\QueryBanquet;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Entity\Forms\BanquetCurrentUser as BanquetCurrentUserEntity;
use RpsCompetition\Form\Type\BanquetCurrentUserType;
use RpsCompetition\Season\Helper as SeasonHelper;
use Symfony\Component\Form\FormFactory;

class BanquetCurrentUserModel
{
    /**
     * @var FormFactory
     */
    private $formFactory;
    /**
     * @var QueryBanquet
     */
    private $query_banquet;
    /**
     * @var QueryEntries
     */
    private $query_entries;
    /**
     * @var QueryMiscellaneous
     */
    private $query_miscellaneous;
    /**
     * @var Requests
     */
    private $requests;
    private $season_helper;

    public function __construct(
        FormFactory $formFactory,
        SeasonHelper $season_helper,
        QueryMiscellaneous $query_miscellaneous,
        QueryBanquet $query_banquet,
        QueryEntries $query_entries,
        IlluminateRequest $requests
    ) {

        $this->season_helper = $season_helper;
        $this->query_miscellaneous = $query_miscellaneous;
        $this->query_banquet = $query_banquet;
        $this->query_entries = $query_entries;
        $this->formFactory = $formFactory;
        $this->requests = $requests;
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

    public function getEntries()
    {

        $data = [];
        $data['season_form'] = $this->dataMonthAndSeasonSelectionForm([]);
        $data['selected_season'] = $this->requests->input('new_season', end($data['season_form']['seasons']));
        if ($this->requests) {
            list ($season_start_date, $season_end_date) = $this->season_helper->getSeasonStartEnd(
                $data['selected_season']
            )
            ;
        }
        $scores = $this->query_miscellaneous->getScoresUser(
            get_current_user_id(),
            $season_start_date,
            $season_end_date
        )
        ;

        $current_user_id = get_current_user_id();
        $banquet_id = $this->query_banquet->getBanquets($season_start_date, $season_end_date);
        $banquet_id_array = [];
        $banquet_entries = [];
        if (is_array($banquet_id) && !empty($banquet_id)) {
            foreach ($banquet_id as $record) {
                $banquet_id_array[] = $record['ID'];
                if ($record['Closed'] == 'Y') {
                    $data['disabled'] = true;
                }
            }

            $where = 'Competition_ID in (' . implode(',', $banquet_id_array) . ') AND Member_ID = "' . $current_user_id . '"';
            $banquet_entries = $this->query_entries->query(['where' => $where]);
        }

        if (!is_array($banquet_entries)) {
            $banquet_entries = [];
        }
        $all_entries = [];
        foreach ($banquet_entries as $banquet_entry) {
            $all_entries[] = $banquet_entry->ID;
        }

        // Start building the form
        if (!empty($scores)) {
            $data['scores'] = true;

            // Build the list of submitted images
            $comp_count = 0;
            $prev_date = "";
            $prev_medium = "";

            foreach ($scores as $recs) {
                $entry = [];
                if (empty($recs['Award'])) {
                    continue;
                }

                $date_parts = explode(" ", $recs['Competition_Date']);
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

                foreach ($banquet_entries as $banquet_entry) {

                    if (!empty($banquet_entry) && $banquet_entry->Title == $entry['title']) {
                        $entry['checked'] = true;
                        break;
                    }
                }

                $entry['entry_id'] = $recs['Entry_ID'];
                $data['entries'][] = $entry;
            }
            if (empty($disabled)) {
            }
        }

        // Start the form
        global $post;
        $action = home_url('/' . get_page_uri($post->ID));
        $entity = new BanquetCurrentUserEntity();
        $entity->setWpGetReferer(remove_query_arg(['m', 'id'], wp_get_referer()));
        $entity->setAllentries(base64_encode(json_encode($all_entries)));
        $entity->setBanquetids(base64_encode(json_encode($banquet_id_array)));
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
}
