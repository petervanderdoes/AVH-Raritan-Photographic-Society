<?php
namespace RpsCompetition\Api;

use Illuminate\Http\Request;
use PDO;
use RpsCompetition\Constants;
use RpsCompetition\Db\RpsPdo;
use RpsCompetition\Helpers\CommonHelper;
use RpsCompetition\Helpers\PhotoHelper;

/**
 * Class Client
 *
 * @package   RpsCompetition\Api
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class Client
{
    private $json;
    private $json_error;
    private $photo_helper;

    /**
     * Constructor
     *
     * @param PhotoHelper $photo_helper
     * @param Json        $json
     */
    public function __construct(PhotoHelper $photo_helper, Json $json)
    {
        $this->photo_helper = $photo_helper;
        $this->json = $json;
    }

    /**
     * Handles the competitions results
     *
     * @param Request $request
     */
    public function doUploadScore(Request $request)
    {
        $username = $request->input('username');
        $password = $request->input('password');
        $db = $this->getDatabaseHandle();

        if (is_object($db)) {
            $this->checkUserAuthentication($username, $password);
            // Check to see if there were any file upload errors
            $json = $request->input('json');
            if ($json !== null) {
                $scores = json_decode($json);
                $warning = $this->handleCompetitionResults($db, $scores);

                // Return success to the client
                $warning = '  <info>Scores successfully uploaded</info>' . "\n" . $warning;
                $this->doRESTSuccess($warning);
            }
        }
        die();
    }

    /**
     * Handle request by client for Competition Dates
     *
     * @param Request $request
     */
    public function sendCompetitionDates(Request $request)
    {
        $closed = $request->input('closed');
        $scored = $request->input('scored');
        $db = $this->getDatabaseHandle();
        if (is_object($db)) {
            $result = $this->getCompetitionDates($db, $closed, $scored);
            $this->json->send_response(null, null, $result);
        } else {
            $this->json->addError('Can not connect to server database.');
            $this->json->send_response();
        }
    }

    /**
     * Handles request by client to download images for a particular date.
     *
     * @param Request $request
     */
    public function sendCompetitions(Request $request)
    {
        $username = $request->input('username');
        $password = $request->input('password');
        $db = $this->getDatabaseHandle();
        if (is_object($db)) {
            $this->checkUserAuthentication($username, $password);
            // @todo Check if the user has the role needed.
            $competition_info = $this->getCompetitionInfo($db, $request->input('medium'), $request->input('comp_date'));
            echo json_encode($competition_info);
        }
        die();
    }

    /**
     * @param string $key
     * @param mixed  $data
     *
     * @return array
     */
    private function addJsonData($key, $data)
    {
        $this->json_data['data'][$key] = $data;
    }

    /**
     * Create a REST error
     *
     * @param string $error_message The actual error message
     *
     * @return array
     */
    private function addJsonError($error_message)
    {
        $error_detail['detail'] = $error_message;
        $this->json_error['errors'][] = $error_detail;
    }

    /**
     * Check if user/password combination is valid
     *
     * @param string $username
     * @param string $password
     */
    private function checkUserAuthentication($username, $password)
    {
        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            $error_message = strip_tags($user->get_error_message());
            $this->doRESTError($error_message);
            die();
        }

        return;
    }

    /**
     * Create a REST error
     *
     * @param string $error_message The actual error message
     *
     * @return array
     */
    private function doRESTError($error_message)
    {

        $error_detail['detail'] = $error_message;
        $json_error[] = $error_detail;

        return $json_error;
    }

    /**
     * Create the REST response
     *
     * @param string $status
     * @param string $message
     */
    private function doRESTResponse($status, $message)
    {
        echo '<?xml version="1.0" encoding="utf-8" ?>' . "\n";
        echo '<rsp stat="' . $status . '">' . "\n";
        echo '	' . $message . "\n";
        echo '</rsp>' . "\n";
    }

    /**
     * Create a REST success message
     *
     * @param string $message The actual message
     */
    private function doRESTSuccess($message)
    {
        $this->doRESTResponse('ok', $message);
    }

    /**
     * Fetch the competiton dates from the databse.
     *
     * @param RpsPdo $db
     * @param string $closed
     * @param string $scored
     *
     * @return array|boolean
     */
    private function fetchCompetitionDates($db, $closed, $scored)
    {
        try {
            $select = 'SELECT DISTINCT(Competition_Date) FROM competitions ';
            if ($closed !== null || $scored !== null) {
                $where = 'WHERE';
                if ($closed !== null) {
                    $where .= ' Closed=:closed';
                }
                if ($scored !== null) {
                    $where .= ' AND Scored=:scored';
                }
            } else {
                $where = 'WHERE Competition_Date >= CURDATE()';
            }

            $sth = $db->prepare($select . $where);

            if ($closed !== null) {
                $sth->bindParam(':closed', $closed, \PDO::PARAM_STR, 1);
            }
            if ($scored !== null) {
                $sth->bindParam(':scored', $scored, \PDO::PARAM_STR, 1);
            }
            $sth->execute();
        } catch (\PDOException $e) {
            return $e;
        }

        $recs = $sth->fetchall(\PDO::FETCH_ASSOC);

        return $recs;
    }

    /**
     * @param RpsPdo $db     Connection to the RPS Database
     * @param string $closed Competition closed info
     * @param string $scored Competition scored info
     *
     * @return array
     */
    private function getCompetitionDates($db, $closed, $scored)
    {
        $dates = [];
        $recs = $this->fetchCompetitionDates($db, $closed, $scored);
        if (get_class($recs) == "PDOException") {
            /* @var $recs PDOExection */
            $this->json->addError('Failed to SELECT list of competitions from database');
            $this->json->addError($recs->getMessage());

            return $this->json->get_json();
        }
        foreach ($recs as $record) {
            $date_parts = explode(' ', $record['Competition_Date']);
            $dates[] = $date_parts[0];
        }
        if ($dates === []) {
            $this->json->addError('No competition dates found');
        } else {
            $this->json->addResource('CompetitionDates', $dates);
        }

        return $this->json->get_json();
    }

    /**
     * Collect information for the client
     *
     * @param RpsPdo $db               Connection to the RPS Database
     * @param string $requested_medium Which competition medium to use, either digital or print
     * @param string $comp_date        The competition date
     *
     * @return array
     */
    private function getCompetitionInfo($db, $requested_medium, $comp_date)
    {

        $competition_information = [];
        $competitions = [];
        $total_entries = 0;

        $medium_clause = '';
        if (!(empty($requested_medium))) {
            $medium_clause = ($requested_medium ==
                              'prints') ? ' AND Medium like \'%Prints\' ' : ' AND Medium like \'%Digital\' ';
        }
        $sql = 'SELECT ID, Competition_Date, Theme, Medium, Classification
        FROM competitions
        WHERE Competition_Date = DATE(:compdate) AND Closed = "Y" ' . $medium_clause . '
        ORDER BY Medium, Classification';
        try {
            $sth_competitions = $db->prepare($sql);
            $sth_competitions->bindParam(':compdate', $comp_date);
            $sth_competitions->execute();
        } catch (\PDOException $e) {
            $this->doRESTError(
                'Failed to SELECT competition records with date = ' .
                $comp_date .
                ' from database - ' .
                $e->getMessage()
            );
            die();
        }
        // Iterate through all the matching Competitions
        $record_competitions = $sth_competitions->fetch(\PDO::FETCH_ASSOC);
        while ($record_competitions !== false) {
            $comp_id = $record_competitions['ID'];
            $date_parts = explode(' ', $record_competitions['Competition_Date']);
            $date = $date_parts[0];
            $theme = $record_competitions['Theme'];
            $medium = $record_competitions['Medium'];
            $classification = $record_competitions['Classification'];
            // Create the competition node in the XML response
            $competition = [];
            $competition['Date'] = $date;
            $competition['Theme'] = $theme;
            $competition['Medium'] = $medium;
            $competition['Classification'] = $classification;

            // Get all the entries for this competition
            try {
                $sql = 'SELECT entries.ID, entries.Title, entries.Member_ID,
            entries.Server_File_Name, entries.Score, entries.Award
            FROM entries
                WHERE entries.Competition_ID = :comp_id
                        ORDER BY entries.Member_ID, entries.Title';
                $sth_entries = $db->prepare($sql);
                $sth_entries->bindValue(':comp_id', $comp_id, \PDO::PARAM_INT);
                $sth_entries->execute();
            } catch (\Exception $e) {
                $this->doRESTError('Failed to SELECT competition entries from database - ' . $e->getMessage());
                die();
            }
            $all_records_entries = $sth_entries->fetchAll();

            // Iterate through all the entries for this competition
            $entries = [];
            foreach ($all_records_entries as $record_entries) {
                $user = get_user_by('id', $record_entries['Member_ID']);
                if (CommonHelper::isPaidMember($user->ID)) {
                    $entry = [];
                    // Create an Entry node
                    $entry['ID'] = $record_entries['ID'];
                    $entry['First_Name'] = $user->user_firstname;
                    $entry['Last_Name'] = $user->user_lastname;
                    $entry['Title'] = $record_entries['Title'];
                    $entry['Score'] = $record_entries['Score'];
                    $entry['Award'] = $record_entries['Award'];
                    $entry['Image_URL'] = $this->photo_helper->getThumbnailUrl(
                        $record_entries['Server_File_Name'],
                        Constants::IMAGE_CLIENT_SIZE
                    );
                    $entries[] = $entry;
                    $total_entries++;
                }
            }
            $competition['Entries'] = $entries;
            $competitions[] = $competition;
            $record_competitions = $sth_competitions->fetch(\PDO::FETCH_ASSOC);
        }
        $competition_information['Configuration']['ImageSize']['Width'] = 1440;
        $competition_information['Configuration']['ImageSize']['Height'] = 990;
        $competition_information['Configuration']['TotalEntries'] = $total_entries;
        $competition_information['Competitions'] = $competitions;

        $fp = fopen('peter.json', 'w');
        fwrite($fp, json_encode($competition_information));
        fclose($fp);

        return $competition_information;
    }

    /**
     * Open database
     *
     * @return RpsPdo|null
     */
    private function getDatabaseHandle()
    {
        try {
            $db = new RpsPdo();
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            $this->doRESTError('Failed to obtain database handle ' . $e->getMessage());
            die($e->getMessage());
        }

        return $db;
    }

    /**
     * Handle the data containing the competition results and add them to the database
     *
     * @param RpsPdo $db Database handle.
     * @param array  $competition_results
     *
     * @return string|null
     */
    private function handleCompetitionResults($db, $competition_results)
    {
        $warning = '';

        try {
            $sql = 'UPDATE entries SET Score = :score, Date_Modified = NOW(), Award = :award WHERE ID = :entryid';
            $stmt = $db->prepare($sql);
        } catch (\PDOException $e) {
            $this->doRESTError('Error - ' . $e->getMessage() . ' - ' . $sql);
            die();
        }

        foreach ($competition_results->Competitions as $competition) {
            $comp_date = (string) $competition->CompDate;
            $classification = (string) $competition->Classification;
            $medium = (string) $competition->Medium;

            foreach ($competition->Entries as $entry) {
                $entry_id = $entry->ID;
                $first_name = html_entity_decode($entry->First_Name);
                $last_name = html_entity_decode($entry->Last_Name);
                $title = html_entity_decode($entry->Title);
                $score = html_entity_decode($entry->Score);
                $award = html_entity_decode($entry->Award);

                if ($entry_id != '') {
                    if ($score != '') {
                        try {
                            $stmt->bindValue(':score', $score, PDO::PARAM_STR);
                            $stmt->bindValue(':award', $award, PDO::PARAM_STR);
                            $stmt->bindValue(':entryid', $entry_id, PDO::PARAM_INT);
                            $stmt->execute();
                        } catch (\PDOException $e) {
                            $this->doRESTError(
                                'Failed to UPDATE scores in database - ' . $e->getMessage() . ' - ' . $sql
                            );
                            die();
                        }
                        if ($stmt->rowCount() < 1) {
                            $warning .= '  <info>' .
                                        (string) $comp_date .
                                        ', ' .
                                        $first_name .
                                        ' ' .
                                        $last_name .
                                        ', ' .
                                        $title .
                                        ' -- Row failed to update</info>' .
                                        "\n";
                        }
                    }
                } else {
                    $warning .= '  <info>' .
                                (string) $comp_date .
                                ', ' .
                                $first_name .
                                ' ' .
                                $last_name .
                                ', ' .
                                $title .
                                ' -- ID is Null -- skipped</info>' .
                                "\n";
                }
            }
            $this->markCompetitonScored($db, $comp_date, $classification, $medium);
        }

        return $warning;
    }

    /**
     * Mark a competition as scored.
     *
     * @param RpsPdo $db
     * @param string $comp_date
     * @param string $classification
     * @param string $medium
     */
    private function markCompetitonScored($db, $comp_date, $classification, $medium)
    {
        try {
            $sql_update = 'UPDATE competitions SET Scored = "Y", Date_Modified = NOW()
                        WHERE Competition_Date = :comp_date AND
                        Classification = :classification AND
                        Medium = :medium';
            $stmt_update = $db->prepare($sql_update);
            $date = new \DateTime($comp_date);
            $sql_date = $date->format('Y-m-d H:i:s');
            $stmt_update->bindValue(':comp_date', $sql_date, PDO::PARAM_STR);
            $stmt_update->bindValue(':classification', $classification, PDO::PARAM_STR);
            $stmt_update->bindValue(':medium', $medium, PDO::PARAM_STR);
            $stmt_update->execute();
        } catch (\PDOException $e) {
            $this->doRESTError(
                'Failed to execute UPDATE' . $e->getMessage()
            );
            die();
        }
        if ($stmt_update->rowCount() < 1) {
            $this->doRESTError(
                'No rows updated when setting Scored flag to Y in database for ' .
                $sql_date .
                ' / ' .
                $classification .
                ' / ' .
                $medium
            );
            die();
        }
    }
}
