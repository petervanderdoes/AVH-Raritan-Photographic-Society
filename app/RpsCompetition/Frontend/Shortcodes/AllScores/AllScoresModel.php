<?php

namespace RpsCompetition\Frontend\Shortcodes\AllScores;

use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Season\Helper as SeasonHelper;

class AllScoresModel
{
    /**
     * @var QueryCompetitions
     */
    private $query_competitions;
    /**
     * @var QueryMiscellaneous
     */
    private $query_miscellaneous;
    /**
     * @var IlluminateRequest
     */
    private $request;
    /**
     * @var SeasonHelper
     */
    private $season_helper;

    /**
     * @param QueryCompetitions  $query_competitions
     * @param QueryMiscellaneous $query_miscellaneous
     * @param SeasonHelper       $season_helper
     * @param IlluminateRequest  $request
     */
    public function __construct(
        QueryCompetitions $query_competitions,
        QueryMiscellaneous $query_miscellaneous,
        SeasonHelper $season_helper,
        IlluminateRequest $request
    ) {
        $this->query_competitions = $query_competitions;
        $this->query_miscellaneous = $query_miscellaneous;
        $this->season_helper = $season_helper;
        $this->request = $request;
    }

    public function getAllData()
    {
        $seasons = $this->season_helper->getSeasons();
        $selected_season = esc_attr($this->request->input('new_season', end($seasons)));

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
        $previous_member = '';
        $previous_date = '';
        $previous_medium = '';
        $scored_entries = 0;
        $total_score = 0;
        $scores = [];
        foreach ($club_competition_results as $result) {

            $medium_key = $medium_map[$result['Medium']];
            if ($result['Member_ID'] !== $previous_member || $result['Medium'] !== $previous_medium) {
                $previous_member = $result['Member_ID'];
                $previous_medium = $result['Medium'];
                $scored_entries = 0;
                $total_score = 0;
                $new_entries = 0;
                foreach ($comp_dates as $key => $value) {
                    for ($entries = 1; $entries <= $comp_max_entries[$key]; $entries++) {
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

        return ['data' => ['entries' => $medium_array, 'heading' => $heading]];
    }
}
