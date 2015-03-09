<?php

namespace RpsCompetition\Frontend\Shortcodes\AllScores;

use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Entity\Forms\AllScores as AllScoresEntity;
use RpsCompetition\Form\Type\AllScoresType;
use RpsCompetition\Season\Helper as SeasonHelper;
use Symfony\Component\Form\FormFactory;

/**
 * Class AllScoresModel
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\AllScores
 */
class AllScoresModel
{
    /** @var FormFactory */
    private $form_factory;
    /** @var QueryCompetitions */
    private $query_competitions;
    /** @var QueryMiscellaneous */
    private $query_miscellaneous;
    /** @var IlluminateRequest */
    private $request;
    /** @var SeasonHelper */
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

        $award_map = ['1st' => '1', '2nd' => '2', '3rd' => '3', 'HM' => 'H'];
        $classification_map = ['0' => 'B', '1' => 'A', '2' => 'S'];
        $medium_map = ['Color Prints' => 'cp', 'Color Digital' => 'cd', 'B&W Prints' => 'bp', 'B&W Digital' => 'bd'];

        list ($season_start_date, $season_end_date) = $this->season_helper->getSeasonStartEnd($selected_season);

        $competition_dates = $this->query_competitions->getCompetitionDates($season_start_date, $season_end_date);
        // Build an array of competition dates in "MM/DD" format for column titles.
        // Also remember the max entries per member for each competition and the number
        // of judges for each competition.
        $total_max_entries = 0;
        $comp_dates = [];
        $comp_max_entries = [];
        $comp_num_judges = [];
        $heading = [];
        foreach ($competition_dates as $key => $recs) {
            $date = new \DateTime($recs['Competition_Date']);
            $date_key = $date->format('Y-m-d');
            $comp_dates[$date_key] = $date->format('m/d');
            $comp_max_entries[$date_key] = $recs['Max_Entries'];
            $total_max_entries += $recs['Max_Entries'];
            $comp_num_judges[$date_key] = $recs['Num_Judges'];
            $heading[$date_key] = ['date' => $date->format('m/d'), 'entries' => $recs['Max_Entries']];
        }

        $club_competition_results_unsorted = $this->query_miscellaneous->getCompetitionResultByDate(
            $season_start_date,
            $season_end_date
        )
        ;
        $club_competition_results = CommonHelper::arrayMsort(
            $club_competition_results_unsorted,
            [
                'Medium'           => [SORT_DESC],
                'Class_Code'       => [SORT_ASC],
                'LastName'         => [SORT_ASC],
                'FirstName'        => [SORT_ASC],
                'Competition_Date' => [SORT_ASC]
            ]
        )
        ;
        $average_score =0;
        $medium_array = [];
        $new_entries = 0;
        $previous_date = '';
        $previous_medium = '';
        $previous_member = '';
        $scored_entries = 0;
        $scores = [];
        $total_score = 0;
        foreach ($club_competition_results as $result) {

            $medium_key = $medium_map[$result['Medium']];
            if ($result['Member_ID'] !== $previous_member || $result['Medium'] !== $previous_medium) {
                $previous_member = $result['Member_ID'];
                $previous_medium = $result['Medium'];
                $scored_entries = 0;
                $total_score = 0;
                $new_entries = 0;
                foreach ($comp_dates as $key => $value) {
                    for ($entries = 0; $entries < $comp_max_entries[$key]; $entries++) {
                        $scores[$medium_key][$result['Member_ID']][$key][] = ['score' => ' ', 'award' => ''];
                    }
                }
            }

            if (!array_key_exists($result['Award'], $award_map)) {
                $award = '';
            } else {
                $award = $award_map[$result['Award']];
            }

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

        // Start the form
        global $post;
        $action = home_url('/' . get_page_uri($post->ID));
        $entity = new AllScoresEntity();
        $entity->setSeasonChoices($season_options);
        $entity->setSeasons($selected_season);
        $form = $this->form_factory->create(
            new AllScoresType($entity),
            $entity,
            ['action' => $action, 'attr' => ['id' => 'allscores']]
        )
        ;
        $return = [];
        $return['data'] = ['entries' => $medium_array, 'heading' => $heading];
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
}
