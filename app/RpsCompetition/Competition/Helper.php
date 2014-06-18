<?php
namespace RpsCompetition\Competition;

use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\RpsDb;


class Helper
{
    private $rpsdb;

    function __construct(RpsDb $rpsdb)
    {
        $this->rpsdb = $rpsdb;
    }

    public function getMedium($competitions)
    {

        $medium = array();

        foreach ($competitions as $competition) {
            if (in_array($competition->Medium, $medium)) {
                continue;
            }
            $medium[] = $competition->Medium;
        }

        return $medium;
    }

    /**
     * Select the list of open competitions for this member's classification and validate the currently selected competition against that list.
     *
     * @param string $date
     * @param string $medium_subset
     *
     * @return boolean
     */
    public function validateSelectedComp($date, $medium_subset)
    {
        $query_competitions = new QueryCompetitions($this->rpsdb);
        $open_competitions = $query_competitions->getOpenCompetitions(get_current_user_id(), $medium_subset);

        if (empty($open_competitions)) {
            unset($query_competitions);

            return false;
        }

        // Read the competition attributes into a series of arrays
        $index = 0;
        $date_index = -1;
        $medium_index = -1;
        foreach ($open_competitions as $recs) {
            if ($recs['Theme'] == 'Annual Banquet') {
                continue;
            }
            // Append this competition to the arrays
            $date_parts = explode(" ", $recs['Competition_Date']);
            $this->_open_comp_date[$index] = $date_parts[0];
            $this->_open_comp_medium[$index] = $recs['Medium'];
            $this->_open_comp_class[$index] = $recs['Classification'];
            $this->_open_comp_theme[$index] = $recs['Theme'];
            // If this is the first competition whose date matches the currently selected
            // competition date, save its array index
            if ($this->_open_comp_date[$index] == $date) {
                if ($date_index < 0) {
                    $date_index = $index;
                }
                // If this competition matches the date AND the medium of the currently selected
                // competition, save its array index
                if ($this->_open_comp_medium[$index] == $med) {
                    if ($medium_index < 0) {
                        $medium_index = $index;
                    }
                }
            }
            $index += 1;
        }

        // If date and medium both matched, then the currently selected competition is in the
        // list of open competitions for this member
        if ($medium_index >= 0) {
            $index = $medium_index;

            // If the date matched but the medium did not, then there are valid open competitions on
            // the selected date for this member, but not in the currently selected medium. In this
            // case set the medium to the first one in the list for the selected date.
        } elseif ($medium_index < 0 && $date_index >= 0) {
            $index = $date_index;

            // If neither the date or medium matched, simply select the first open competition in the
            // list.
        } else {
            $index = 0;
        }
        // Establish the (possibly adjusted) selected competition
        $this->settings->open_comp_date = $this->_open_comp_date;
        $this->settings->open_comp_medium = $this->_open_comp_medium;
        $this->settings->open_comp_theme = $this->_open_comp_theme;
        $this->settings->open_comp_class = $this->_open_comp_class;
        $this->settings->comp_date = $this->_open_comp_date[$index];
        $this->settings->classification = $this->_open_comp_class[$index];
        $this->settings->medium = $this->_open_comp_medium[$index];
        // Save the currently selected competition in a cookie
        $hour = time() + (2 * 3600);
        $url = parse_url(get_bloginfo('url'));
        setcookie("RPS_MyEntries", $this->settings->comp_date . "|" . $this->settings->classification . "|" . $this->settings->medium, $hour, '/', $url['host']);

        unset($query_competitions);

        return true;
    }
}

?>
