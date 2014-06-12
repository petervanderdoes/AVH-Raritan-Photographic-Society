<?php
namespace RpsCompetition\Season;

use Avh\Html\FormBuilder;
use Avh\Html\HtmlBuilder;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Settings;

// ---------- Private methods ----------
/**
 *
 * @author pdoes
 *
 */
class Helper
{

    private $rpsdb;
    private $settings;

    public function __construct(Settings $settings, $rpsdb)
    {
        $this->settings = $settings;
        $this->rpsdb = $rpsdb;
    }

// ---------- Public methods ----------
    /**
     * Get the season in a dropdown menu
     *
     * @param string  $selected_season
     * @param boolean $echo
     *
     * @return void string
     */
    public function getSeasonDropdown($selected_season, $echo = false)
    {
        $seasons = $this->getSeasons();
        $html_builder = new HtmlBuilder();
        $form_builder = new FormBuilder($html_builder);

        $form = $form_builder->select('new_season', array_combine($seasons, $seasons), $selected_season, array('onChange' => 'submit_form("new_season")'));

        if ($echo) {
            echo $form;

            return;
        }

        return $form;
    }

    public function getSeasonId($selected_year, $selected_month)
    {
        $options = get_option('avh-rps');
        if ($selected_month < $options['season_start_month_num']) {
            $selected_year--;
        }

        return $selected_year . '-' . substr($selected_year + 1, 2, 2);
    }

    /**
     * Set the Season Start date and Season End date
     *
     * @param string $selected_season
     */
    public function getSeasonStartEnd($selected_season)
    {
        $options = get_option('avh-rps');
        $season_date = array();
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
     * @return Ambigous <multitype:, NULL>
     */
    public function getSeasons()
    {
        $options = get_option('avh-rps');
        $query_miscellaneous = new QueryMiscellaneous($this->rpsdb);
        $seasons = $query_miscellaneous->getSeasonList('ASC', $options['season_start_month_num'], $options['season_end_month_num']);
        if (empty($this->settings->selected_season)) {
            $this->settings->selected_season = $seasons[count($seasons) - 1];
        }

        unset($query_miscellaneous);

        return $seasons;
    }
}
