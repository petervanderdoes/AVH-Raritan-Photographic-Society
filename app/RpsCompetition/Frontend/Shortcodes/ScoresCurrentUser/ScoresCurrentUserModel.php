<?php

namespace RpsCompetition\Frontend\Shortcodes\ScoresCurrentUser;

use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Entity\Forms\ScoresCurrentUser as ScoresCurrentUserEntity;
use RpsCompetition\Form\Type\ScoresCurrentUserType;
use RpsCompetition\Season\Helper as SeasonHelper;
use Symfony\Component\Form\FormFactory;

class ScoresCurrentUserModel
{
    /** @var FormFactory */
    private $form_factory;
    /** @var QueryMiscellaneous */
    private $query_miscellaneous;
    /** @var IlluminateRequest */
    private $requests;
    /** @var SeasonHelper */
    private $season_helper;

    /**
     * Construcor
     *
     * @param FormFactory        $form_factory
     * @param QueryMiscellaneous $query_miscellaneous
     * @param SeasonHelper       $season_helper
     * @param IlluminateRequest  $requests
     */
    public function __construct(
        FormFactory $form_factory,
        QueryMiscellaneous $query_miscellaneous,
        SeasonHelper $season_helper,
        IlluminateRequest $requests
    ) {
        $this->query_miscellaneous = $query_miscellaneous;
        $this->season_helper = $season_helper;
        $this->requests = $requests;
        $this->form_factory = $form_factory;
    }

    /**
     * Get all form data
     *
     * @return array
     */
    public function getAllData()
    {
        global $post;

        $season_options = $this->getSeasonsOptions();
        $selected_season = $this->requests->input('form.seasons', end($season_options));

        $scores = $this->getScores($selected_season);

        $data = [];
        if (!empty($scores)) {
            $data = $this->getTemplateData($season_options, $selected_season, $scores);
        }
        $entity = new ScoresCurrentUserEntity();
        $action = home_url('/' . get_page_uri($post->ID));
        $entity->setSeasonChoices($season_options);
        $entity->setSeasons($selected_season);
        $form = $this->form_factory->create(
            new ScoresCurrentUserType($entity),
            $entity,
            ['action' => $action, 'attr' => ['id' => 'scorescurrentuser']]
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
     * @param array  $season_options
     * @param string $selected_season
     * @param array  $scores
     *
     * @return array
     */
    public function getTemplateData($season_options, $selected_season, $scores)
    {

        $data = [];
        $data['seasons'] = $season_options;
        $data['selected_season'] = $selected_season;
        $data['scores'] = true;
        $comp_count = 0;
        $prev_date = '';
        $prev_medium = '';
        foreach ($scores as $recs) {
            $entry = [];
            $date_parts = explode(' ', $recs['Competition_Date']);
            $date_parts[0] = strftime('%d-%b-%Y', strtotime($date_parts[0]));
            $entry['competition_date'] = $date_parts[0];
            $entry['medium'] = $recs['Medium'];
            $entry['theme'] = $recs['Theme'];
            $entry['title'] = $recs['Title'];
            $entry['score'] = $recs['Score'];
            $entry['award'] = $recs['Award'];
            if ($date_parts[0] != $prev_date) {
                $comp_count++;
                $prev_medium = '';
            }

            $entry['image_url'] = home_url($recs['Server_File_Name']);

            if ($prev_date == $date_parts[0]) {
                $date_parts[0] = '';
                $entry['theme'] = '';
            } else {
                $prev_date = $date_parts[0];
            }
            if ($prev_medium == $entry['medium']) {
                // $medium = "";
                $entry['theme'] = '';
            } else {
                $prev_medium = $entry['medium'];
            }
            $score_award = '';
            if ($entry['score'] > '') {
                $score_award = ' / ' . $entry['score'] . 'pts';
            }
            if ($entry['award'] > '') {
                $score_award .= ' / ' . $entry['award'];
            }

            $entry['href_title'] = $entry['title'] . ' / ' . $entry['competition_date'] . '/' . $entry['medium'] . $score_award;
            $data['entries'][] = $entry;
        }

        return $data;
    }
}
