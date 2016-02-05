<?php

namespace RpsCompetition\Frontend\Shortcodes\AllScores;

use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Entity\Form\AllScores as EntityFormAllScores;
use RpsCompetition\Form\Type\AllScoresType;
use RpsCompetition\Helpers\CommonHelper;
use RpsCompetition\Helpers\SeasonHelper;
use Symfony\Component\Form\FormFactory;

/**
 * Class AllScoresModel
 *
 * @package   RpsCompetition\Frontend\Shortcodes\AllScores
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class AllScoresModel
{
    private $form_factory;
    private $query_competitions;
    private $query_miscellaneous;
    private $request;
    private $season_helper;

    /**
     * Constructor
     *
     * @param QueryCompetitions  $query_competitions
     * @param QueryMiscellaneous $query_miscellaneous
     * @param SeasonHelper       $season_helper
     * @param IlluminateRequest  $request
     * @param FormFactory        $form_factory
     */
    public function __construct(
        QueryCompetitions $query_competitions,
        QueryMiscellaneous $query_miscellaneous,
        SeasonHelper $season_helper,
        IlluminateRequest $request,
        FormFactory $form_factory
    ) {
        $this->query_competitions = $query_competitions;
        $this->query_miscellaneous = $query_miscellaneous;
        $this->season_helper = $season_helper;
        $this->request = $request;
        $this->form_factory = $form_factory;
    }

    /**
     * Collect all the data neccesary for the shortcode
     *
     * @return array
     */
    public function getAllData()
    {
        $season_options = $this->getSeasonsOptions();
        $selected_season = $this->request->input('form.seasons', end($season_options));

        $template_data = $this->getTemplateData($selected_season);
        $form = $this->getFormData($season_options, $selected_season);
        $return = [];
        $return['data'] = $template_data;
        $return['form'] = $form;

        return $return;
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
     * Create an empty score array.
     * This array is used per Medium/User so all competitions are populated.
     *
     * @param array $competition_dates
     *
     * @return array
     */
    private function getDefaultScoreArray($competition_dates)
    {
        $score = [];
        foreach ($competition_dates as $competition) {
            $key = $competition['Competition_Date'];
            for ($entries = 0; $entries < $competition['Max_Entries']; $entries++) {
                $score[$key][] = ['score' => '', 'award' => ''];
            }
        }

        return $score;
    }

    /**
     * Get the form to be used in the template.
     *
     * @param array  $season_options
     * @param string $selected_season
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    private function getFormData($season_options, $selected_season)
    {
        // Start the form
        global $post;
        $action = home_url('/' . get_page_uri($post->ID));
        $entity = new EntityFormAllScores();
        $entity->setSeasonChoices($season_options);
        $entity->setSeasons($selected_season);
        $form = $this->form_factory->create(
            new AllScoresType($entity),
            $entity,
            ['action' => $action, 'attr' => ['id' => 'allscores']]
        );

        return $form;
    }

    /**
     * Get the data for the template.
     *
     * @param string $selected_season
     *
     * @return array
     */
    private function getTemplateData($selected_season)
    {

        $award_map = ['1st' => '1', '2nd' => '2', '3rd' => '3', 'HM' => 'H'];
        $classification_map = ['0' => 'B', '1' => 'A', '2' => 'S'];
        $medium_map = ['Color Prints' => 'cp', 'Color Digital' => 'cd', 'B&W Prints' => 'bp', 'B&W Digital' => 'bd'];

        list ($season_start_date, $season_end_date) = $this->season_helper->getSeasonStartEnd($selected_season);

        $competition_dates = $this->query_competitions->getCompetitionDates($season_start_date, $season_end_date);

        $heading = [];
        foreach ($competition_dates as &$recs) {
            $date = new \DateTime($recs['Competition_Date']);
            $date_key = $date->format('Y-m-d');
            $recs['Competition_Date'] = $date_key;
            $heading[$date_key] = ['date' => $date->format('m/d'), 'entries' => $recs['Max_Entries']];
        }

        $club_competition_results_unsorted = $this->query_miscellaneous->getCompetitionResultByDate(
            $season_start_date,
            $season_end_date
        );
        $club_competition_results = CommonHelper::arrayMsort(
            $club_competition_results_unsorted,
            [
                'Medium'           => [SORT_DESC],
                'Class_Code'       => [SORT_ASC],
                'LastName'         => [SORT_ASC],
                'FirstName'        => [SORT_ASC],
                'Competition_Date' => [SORT_ASC]
            ]
        );
        unset($club_competition_results_unsorted);
        $average_score = 0;
        $medium_array = [];
        $new_entries = 0;
        $previous_date = '';
        $previous_medium = '';
        $previous_member = '';
        $scored_entries = 0;
        $scores = [];
        $total_score = 0;
        $default_score_array = $this->getDefaultScoreArray($competition_dates);

        foreach ($club_competition_results as $result) {
            $medium_key = $medium_map[$result['Medium']];
            if ($result['Member_ID'] !== $previous_member || $result['Medium'] !== $previous_medium) {
                $previous_member = $result['Member_ID'];
                $previous_medium = $result['Medium'];
                $scored_entries = 0;
                $total_score = 0;
                $new_entries = 0;
                $scores[$medium_key][$result['Member_ID']] = $default_score_array;
            }

            $award = avh_get_array_value($award_map, $result['Award']);

            $date = new \DateTime($result['Competition_Date']);
            $competition_date_key = $date->format('Y-m-d');

            if ($competition_date_key !== $previous_date) {
                $new_entries = 0;
                $previous_date = $competition_date_key;
            }
            $scores[$medium_key][$result['Member_ID']][$competition_date_key][$new_entries++] = [
                'score' => $result['Score'],
                'award' => $award
            ];

            /**
             * Calculate the average score for the user for the medium.
             * This works without an array because the array we're walking through is sorted.
             */
            if ($result['Score'] !== null) {
                $scored_entries++;
                $total_score += $result['Score'];
                $average_score = $total_score / $scored_entries;
            }

            $classification = $classification_map[$result['Class_Code']];
            $medium_array[$medium_key]['users'][$result['Member_ID']] = [
                'name'           => $result['FirstName'] . ' ' . $result['LastName'],
                'classification' => $classification,
                'scores'         => $scores[$medium_key][$result['Member_ID']],
                'average_score'  => $average_score
            ];
            $medium_array[$medium_key]['title'] = $result['Medium'];
        }

        return ['entries' => $medium_array, 'heading' => $heading];
    }
}
