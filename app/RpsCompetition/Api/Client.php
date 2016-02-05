<?php
namespace RpsCompetition\Api;

use Illuminate\Http\Request;
use PDO;
use RpsCompetition\Constants;
use RpsCompetition\Db\RpsPdo;
use RpsCompetition\Helpers\CommonHelper;
use RpsCompetition\Helpers\ImageSizeHelper;
use RpsCompetition\Helpers\PhotoHelper;

/**
 * Class Client
 *
 * @package   RpsCompetition\Api
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class Client
{
    private $json;
    private $photo_helper;
    private $total_entries;

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
    public function receiveScores(Request $request)
    {
        $username = $request->input('username');
        $password = $request->input('password');
        $db = $this->getDatabaseHandle();

        if (is_object($db)) {
            if ($this->checkUserAuthentication($username, $password)) {
                // Check to see if there were any file upload errors
                $json = $request->input('json');
                if ($json !== null) {
                    $scores = json_decode($json);
                    $this->handleCompetitionResults($db, $scores);
                } else {
                    $this->json->addError('Empty JSON');
                    $this->json->setStatusError();
                }
            } else {
                $this->json->setStatusError();
            }
        }
        $this->json->sendResponse();
    }

    /**
     * Handle request by client for Competition Dates
     *
     * @param Request $request
     *
     * @return void
     */
    public function sendCompetitionDates(Request $request)
    {
        $closed = $request->input('closed');
        $scored = $request->input('scored');
        $db = $this->getDatabaseHandle();
        if (is_object($db)) {
            $this->jsonCompetitionDates($db, $closed, $scored);
        }
        $this->json->sendResponse();
    }

    /**
     * Handles request by client to download images for a particular date.
     *
     * @param Request $request
     *
     * @return void
     */
    public function sendCompetitions(Request $request)
    {
        $username = $request->input('username');
        $password = $request->input('password');
        $db = $this->getDatabaseHandle();
        if (is_object($db)) {
            if ($this->checkUserAuthentication($username, $password)) {

                // @todo Check if the user has the role needed.
                $this->jsonCompetitionData($db, $request->input('medium'), $request->input('comp_date'));
            } else {
                $this->json->setStatusError();
            }
        }
        $this->json->sendResponse();
    }

    /**
     * Check if user/password combination is valid
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    private function checkUserAuthentication($username, $password)
    {
        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            $error_message = strip_tags($user->get_error_message());
            $this->json->addError($error_message);

            return false;
        }

        return true;
    }

    /**
     * Fetch the competiton dates from the databse.
     *
     * @param RpsPdo $db
     * @param string $closed
     * @param string $scored
     *
     * @return array|\PDOException
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
            $this->json->setStatusError();
            $this->json->addError('Failed to obtain database handle');
            $this->json->addError($e->getMessage());

            return null;
        }

        return $db;
    }

    /**
     * Get all the entries for this competition
     *
     * @param RpsPdo $db      Connection to the RPS Database
     * @param int    $comp_id Competition ID
     *
     * @return array|false
     */
    private function getEntries($db, $comp_id)
    {

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
            $this->json->setStatusError();
            $this->json->addError('Failed to SELECT competition entries from database');
            $this->json->addError($e->getMessage());

            return false;
        }

        $entries = $sth_entries->fetchAll();
        // Iterate through all the entries for this competition
        $all_entries = [];
        foreach ($entries as $entry_record) {
            $user = get_user_by('id', $entry_record['Member_ID']);
            if (CommonHelper::isPaidMember($user->ID)) {
                $entry = [];
                // Create an Entry node
                $entry['id'] = $entry_record['ID'];
                $entry['first_name'] = $user->user_firstname;
                $entry['last_name'] = $user->user_lastname;
                $entry['title'] = $entry_record['Title'];
                $entry['score'] = $entry_record['Score'];
                $entry['award'] = $entry_record['Award'];
                $entry['image_url'] = $this->photo_helper->getThumbnailUrl(
                    $entry_record['Server_File_Name'],
                    Constants::IMAGE_CLIENT_SIZE
                );
                $all_entries[] = $entry;
                $this->total_entries++;
            }
        }

        return $all_entries;
    }

    /**
     * Handle the data containing the competition results and add them to the database
     *
     * @param RpsPdo $db Database handle.
     * @param mixed  $competition_results
     *
     * @return string|null
     */
    private function handleCompetitionResults($db, $competition_results)
    {
        $this->json->setStatusSuccess();
        try {
            $sql = 'UPDATE entries SET Score = :score, Date_Modified = NOW(), Award = :award WHERE ID = :entryid';
            $stmt = $db->prepare($sql);
        } catch (\PDOException $e) {
            $this->json->addError($e->getMessage());
            $this->json->setStatusError();

            return;
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
                    try {
                        $stmt->bindValue(':score', $score, PDO::PARAM_STR);
                        $stmt->bindValue(':award', $award, PDO::PARAM_STR);
                        $stmt->bindValue(':entryid', $entry_id, PDO::PARAM_INT);
                        $stmt->execute();
                    } catch (\PDOException $e) {
                        $this->json->addError($e->getMessage());
                        $this->json->addError($sql);
                        $this->json->setStatusError();

                        return;
                    }
                    if ($stmt->rowCount() < 1) {
                        $this->json->setStatusFail();
                        $this->json->addError('-- Record failed to update -- skipped the following record');
                        $this->json->addError($comp_date . ', ' . $first_name . ' ' . $last_name . ', ' . $title);
                        $this->json->addError('------');
                    }
                } else {
                    $this->json->setStatusFail();
                    $this->json->addError(' -- ID is Empty -- skipped the following record');
                    $this->json->addError($comp_date . ', ' . $first_name . ' ' . $last_name . ', ' . $title);
                    $this->json->addError('------');
                }
            }
            $this->markCompetitonScored($db, $comp_date, $classification, $medium);
        }

        return;
    }

    /**
     * Collect information for the client
     *
     * @param RpsPdo $db               Connection to the RPS Database
     * @param string $requested_medium Which competition medium to use, either digital or print
     * @param string $comp_date        The competition date
     *
     * @return void
     */
    private function jsonCompetitionData($db, $requested_medium, $comp_date)
    {
        $competitions = [];
        $this->total_entries = 0;
        $image_size = '1024';

        $this->json->setStatusSuccess();

        $medium_clause = '';
        if (!(empty($requested_medium))) {
            $medium_clause = ($requested_medium ==
                              'prints') ? ' AND Medium like \'%Prints\' ' : ' AND Medium like \'%Digital\' ';
        }
        $sql = 'SELECT ID, Competition_Date, Theme, Medium, Classification, Image_Size
        FROM competitions
        WHERE Competition_Date = DATE(:compdate) AND Closed = "Y" ' . $medium_clause . '
        ORDER BY Medium, Classification';
        try {
            $sth_competitions = $db->prepare($sql);
            $sth_competitions->bindParam(':compdate', $comp_date);
            $sth_competitions->execute();
        } catch (\PDOException $e) {
            $this->json->setStatusError();
            $this->json->addError(
                'Failed to SELECT competition records with date = ' . $comp_date . ' from database'
            );
            $this->json->addError($e->getMessage());

            return;
        }
        // Iterate through all the matching Competitions
        $record_competitions = $sth_competitions->fetchall(\PDO::FETCH_ASSOC);

        foreach ($record_competitions as $record_competition) {
            $comp_id = $record_competition['ID'];
            $date_parts = explode(' ', $record_competition['Competition_Date']);
            $date = $date_parts[0];
            $theme = $record_competition['Theme'];
            $medium = $record_competition['Medium'];
            $classification = $record_competition['Classification'];
            if (empty($record_competition['Image_Size'])) {
                $image_size = '1024';
            } else {
                $image_size = $record_competition['Image_Size'];
            }
            // Create the competition node in the XML response
            $competition = [];
            $competition['date'] = $date;
            $competition['theme'] = $theme;
            $competition['medium'] = $medium;
            $competition['classification'] = $classification;
            $entries = $this->getEntries($db, $comp_id);
            if ($entries === false) {
                return;
            }

            $competition['entries'] = $entries;
            $competitions[] = $competition;
        }
        $this->jsonCompetitionInformation($image_size);
        $this->json->addResource('competitions', $competitions);

        $fp = fopen('peter.json', 'w');
        fwrite(
            $fp,
            $this->json->getJson(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        fclose($fp);

        return;
    }

    /**
     * Set JSON data for the competition dates.
     *
     * @param RpsPdo $db     Connection to the RPS Database
     * @param string $closed Competition closed info
     * @param string $scored Competition scored info
     *
     * @return void
     */
    private function jsonCompetitionDates($db, $closed, $scored)
    {
        $dates = [];
        $recs = $this->fetchCompetitionDates($db, $closed, $scored);
        if (is_object($recs) && get_class($recs) == 'PDOException') {
            /* @var $recs \PDOException */
            $this->json->setStatusError();
            $this->json->addError('Failed to SELECT list of competitions from database');
            $this->json->addError($recs->getMessage());

            return;
        }
        foreach ($recs as $record) {
            $date_parts = explode(' ', $record['Competition_Date']);
            $dates[] = $date_parts[0];
        }
        $this->json->addResource('competition_dates', $dates);
        $this->json->setStatusSuccess();

        return;
    }

    /**
     * Add JSON Competition Information
     *
     * @param $image_size
     *
     * @return void
     */
    private function jsonCompetitionInformation($image_size)
    {
        $options = get_option('avh-rps');
        $competition_information = [];
        $seleced_image_size = ImageSizeHelper::getImageSize($image_size);
        /**
         * If the image size does not exists in our table we set the size to the default value and set a fail in the JSON file
         */
        if ($seleced_image_size === null) {
            $this->json->setStatusFail();
            $this->json->addError('Unknown Image Size for the competition. Value given: ' . $image_size);
            $seleced_image_size = ImageSizeHelper::getImageSize($options['default_image_size']);
        }
        $competition_information['ImageSize']['Width'] = $seleced_image_size['width'];
        $competition_information['ImageSize']['Height'] = $seleced_image_size['height'];
        $competition_information['total_entries'] = $this->total_entries;

        $this->json->addResource('information', $competition_information);
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
            if ($stmt_update->rowCount() < 1) {
                $this->json->addError('-- No rows updated when setting Scored flag to Y in database for:');
                $this->json->addError($sql_date . ' / ' . $classification . ' / ' . $medium);
                $this->json->addError('------');
                $this->json->setStatusFail();
            }
        } catch (\PDOException $e) {
            $this->json->addError('Failed to mark competition as scored');
            $this->json->addError($e->getMessage());
            $this->json->addError('------');
            $this->json->setStatusFail();
        }
    }
}
