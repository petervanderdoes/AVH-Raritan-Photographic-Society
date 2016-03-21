<?php
namespace RpsCompetition\Helpers;

use Avh\Framework\Html\FormBuilder;
use Avh\Framework\Html\HtmlBuilder;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Db\RpsDb;

/**
 * Class SeasonHelper
 *
 * @package   RpsCompetition\Helpers
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class SeasonHelper
{
    /** @var RpsDb */
    private $rpsdb;

    /**
     * Constructor
     *
     * @param RpsDb $rpsdb
     */
    public function __construct(RpsDb $rpsdb)
    {
        $this->rpsdb = $rpsdb;
    }

    /**
     * Get the season in a dropdown menu
     *
     * @param string $selected_season
     * @param bool   $echo
     *
     * @return string|null
     */
    public function getSeasonDropdown($selected_season, $echo = false)
    {
        $seasons = $this->getSeasons();
        $html_builder = new HtmlBuilder();
        $form_builder = new FormBuilder($html_builder);

        $form = $form_builder->select('new_season',
                                      array_combine($seasons, $seasons),
                                      $selected_season,
                                      ['onChange' => 'submit_form("new_season")']);

        if ($echo) {
            echo $form;

            return null;
        }

        return $form;
    }

    /**
     * Returns the season of the given date
     *
     * @param $date
     *
     * @return string
     */
    public function getSeasonId($date)
    {
        $options = get_option('avh-rps');
        $date_object = new \DateTime($date);
        $selected_year = (int) $date_object->format('Y');
        if ($date_object->format('m') < $options['season_start_month_num']) {
            $selected_year--;
        }

        return $selected_year . '-' . substr($selected_year + 1, 2, 2);
    }

    /**
     * Set the Season Start date and Season End date
     *
     * @param string $selected_season
     *
     * @return array
     */
    public function getSeasonStartEnd($selected_season)
    {
        $options = get_option('avh-rps');
        $season_date = [];
        // @TODO: Serious to do: Take this construction and make it better.
        $season_start_year = substr($selected_season, 0, 4);

        $date = new \DateTime($season_start_year . '-' . $options['season_start_month_num']);
        $season_date[] = $date->format('Y-m-d');

        // @TODO: The 6 is the end of the season.
        $date = new \DateTime(substr($selected_season, 5, 2) . '-6-1');
        $season_date[] = $date->format('Y-m-t');

        return $season_date;
    }

    /**
     * Get the seasons list
     *
     * @return array
     */
    public function getSeasons()
    {
        $options = get_option('avh-rps');
        $query_miscellaneous = new QueryMiscellaneous($this->rpsdb);
        $seasons = $query_miscellaneous->getSeasonList('ASC',
                                                       $options['season_start_month_num'],
                                                       $options['season_end_month_num']);

        unset($query_miscellaneous);

        return $seasons;
    }

    /**
     * Check if given season-id is a valid season.
     *
     * @param string $season
     *
     * @return bool
     */
    public function isValidSeason($season)
    {
        $return = true;
        $seasons = $this->getSeasons();
        if (!in_array($season, $seasons)) {
            $return = false;
        }

        return $return;
    }
}
