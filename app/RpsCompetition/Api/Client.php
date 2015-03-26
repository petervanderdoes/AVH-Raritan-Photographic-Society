<?php
namespace RpsCompetition\Api;

use DOMDocument;
use Illuminate\Http\Request;
use PDO;
use RpsCompetition\Db\RpsPdo;
use RpsCompetition\Helpers\CommonHelper;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

/**
 * Class Client
 *
 * @package   RpsCompetition\Api
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class Client
{
    /**
     * Constructor
     *
     */
    public function __construct()
    {
    }

    /**
     * Handles the uploading of the score file
     *
     * @param Request $request
     */
    public function doUploadScore(Request $request)
    {
        $username = $request->input('username');
        $password = $request->input('password');
        $comp_date = $request->input('date');
        $db = $this->getDatabaseHandle();

        if (is_object($db)) {
            $this->checkUserAuthentication($username, $password);
            // Check to see if there were any file upload errors
            $file = $request->file('file');
            if ($file === null) {
                $this->doRESTError('No file was given to upload!');
                die();
            }

            if (!$file->isValid()) {
                $this->doRESTError($file->getErrorMessage());
                die();
            }

            // Move the file to its final location
            $path = $request->server('DOCUMENT_ROOT') . '/Digital_Competitions';
            $dest_name = 'scores_' . $comp_date . '.xml';
            $file_name = $path . '/' . $dest_name;
            try {
                $file->move($path, $dest_name);
            } catch (FileException $e) {
                $this->doRESTError($e->getMessage());
                die();
            }

            $warning = $this->handleUploadScoresFile($db, $file_name);

            // Remove the uploaded .xml file
            unlink($file_name);

            // Return success to the client
            $warning = '  <info>Scores successfully uploaded</info>' . "\n" . $warning;
            $this->doRESTSuccess($warning);
        }
        die();
    }

    /**
     * Handles request by client to download images for a particular date,
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
            $this->sendXmlCompetitions($db, $request->input('medium'), $request->input('comp_date'));
        }
        die();
    }

    /**
     * Create a XML File with the competition dates
     *
     * @param Request $request
     */
    public function sendXmlCompetitionDates(Request $request)
    {
        // Connect to the Database
        $db = $this->getDatabaseHandle();

        try {
            $select = 'SELECT DISTINCT(Competition_Date) FROM competitions ';
            if ($request->has('closed') || $request->has('scored')) {
                $where = 'WHERE';
                if ($request->has('closed')) {
                    $where .= ' Closed=:closed';
                }
                if ($request->has('scored')) {
                    $where .= ' AND Scored=:scored';
                }
            } else {
                $where = 'WHERE Competition_Date >= CURDATE()';
            }

            $sth = $db->prepare($select . $where);
            if ($request->has('closed')) {
                $closed = $request->input('closed');
                $sth->bindParam(':closed', $closed, \PDO::PARAM_STR, 1);
            }
            if ($request->has('scored')) {
                $scored = $request->input('scored');
                $sth->bindParam(':scored', $scored, \PDO::PARAM_STR, 1);
            }
            $sth->execute();
        } catch (\PDOException $e) {
            $this->doRESTError('Failed to SELECT list of competitions from database - ' . $e->getMessage());
            die($e->getMessage());
        }

        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('rsp');
        $dom->appendChild($root);
        $stat = $dom->createAttribute('stat');
        $root->appendChild($stat);
        $value = $dom->CreateTextNode('ok');
        $stat->appendChild($value);
        $recs = $sth->fetch(\PDO::FETCH_ASSOC);
        while ($recs != false) {
            $date_parts = explode(' ', $recs['Competition_Date']);
            $comp_date = $root->appendChild($dom->createElement('Competition_Date'));
            $comp_date->appendChild($dom->createTextNode($date_parts[0]));
            $recs = $sth->fetch(\PDO::FETCH_ASSOC);
        }
        echo $dom->saveXML();
        unset($db);
        die();
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
     * @param string $errMsg The actual error message
     */
    private function doRESTError($errMsg)
    {
        $this->doRESTResponse('fail', '<err msg="' . $errMsg . '" ></err>');
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
     * Handle the XML file containing the scores and add them to the database
     *
     * @param RpsPdo $db Database handle.
     * @param string $file_name
     *
     * @return string|null
     */
    private function handleUploadScoresFile($db, $file_name)
    {
        $warning = '';

        $xml = simplexml_load_file($file_name);
        if (!$xml) {
            $this->doRESTError('Failed to open scores XML file');
            die();
        }
        try {
            $sql = 'UPDATE entries SET Score = :score, Date_Modified = NOW(), Award = :award WHERE ID = :entryid';
            $stmt = $db->prepare($sql);
        } catch (\PDOException $e) {
            $this->doRESTError('Error - ' . $e->getMessage() . ' - ' . $sql);
            die();
        }

        foreach ($xml->{'Competition'} as $comp) {
            $comp_date = (string) $comp->{'Date'};
            $classification = (string) $comp->{'Classification'};
            $medium = (string) $comp->{'Medium'};

            foreach ($comp->{'Entries'} as $entries) {
                foreach ($entries->{'Entry'} as $entry) {
                    $entry_id = $entry->{'ID'};
                    $first_name = html_entity_decode($entry->{'First_Name'});
                    $last_name = html_entity_decode($entry->{'Last_Name'});
                    $title = html_entity_decode($entry->{'Title'});
                    $score = html_entity_decode($entry->{'Score'});
                    $award = html_entity_decode($entry->{'Award'});

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
                                )
                                ;
                                die();
                            }
                            if ($stmt->rowCount() < 1) {
                                $warning .= '  <info>' . (string) $comp_date . ', ' . $first_name . ' ' . $last_name . ', ' . $title . ' -- Row failed to update</info>' . "\n";
                            }
                        }
                    } else {
                        $warning .= '  <info>' . (string) $comp_date . ', ' . $first_name . ' ' . $last_name . ', ' . $title . ' -- ID is Null -- skipped</info>' . "\n";
                    }
                }
            }

            // Mark this competition as scored
            try {
                $sql = 'UPDATE competitions SET Scored="Y", Date_Modified=NOW()
                        WHERE Competition_Date=":comp_date" AND
                        Classification=":classification" AND
                        Medium = ":medium"';
                $stmt = $db->prepare($sql);
                $stmt->bindValue(':compdate', $comp_date);
                $stmt->bindValue(':classification', $classification);
                $stmt->bindValue(':medium', $medium);
                $stmt->execute();
            } catch (\PDOException $e) {
                $this->doRESTError(
                    'Failed to execute UPDATE to set Scored flag to Y in database for ' . $comp_date . ' / ' . $classification
                )
                ;
                die();
            }
            if (mysql_affected_rows() < 1) {
                $this->doRESTError(
                    'No rows updated when setting Scored flag to Y in database for ' . $comp_date . ' / ' . $classification
                )
                ;
                die();
            }
        }

        return $warning;
    }

    /**
     * Create a XML file for the client with information about images for a particular date
     *
     * @param RpsPdo $db
     *            Connection to the RPS Database
     * @param string $requested_medium
     *            Which competition medium to use, either digital or print
     * @param string $comp_date
     *            The competition date
     */
    private function sendXmlCompetitions($db, $requested_medium, $comp_date)
    {

        // Start building the XML response
        $dom = new \DOMDocument('1.0', 'utf-8');
        // Create the root node
        $node = $dom->CreateElement('rsp');
        $node->SetAttribute('stat', 'ok');
        $rsp = $dom->AppendChild($node);

        $medium_clause = '';
        if (!(empty($requested_medium))) {
            $medium_clause = ($requested_medium == 'prints') ? ' AND Medium like \'%Prints\' ' : ' AND Medium like \'%Digital\' ';
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
                'Failed to SELECT competition records with date = ' . $comp_date . ' from database - ' . $e->getMessage(
                )
            )
            ;
            die();
        }
        // Create a Competitions node
        $xml_competitions = $rsp->AppendChild($dom->CreateElement('Competitions'));
        // Iterate through all the matching Competitions and create corresponding Competition nodes
        $record_competitions = $sth_competitions->fetch(\PDO::FETCH_ASSOC);
        while ($record_competitions !== false) {
            $comp_id = $record_competitions['ID'];
            $date_parts = explode(' ', $record_competitions['Competition_Date']);
            $date = $date_parts[0];
            $theme = $record_competitions['Theme'];
            $medium = $record_competitions['Medium'];
            $classification = $record_competitions['Classification'];
            // Create the competition node in the XML response
            $competition_element = $xml_competitions->AppendChild($dom->CreateElement('Competition'));

            $date_element = $competition_element->AppendChild($dom->CreateElement('Date'));
            $date_element->AppendChild($dom->CreateTextNode(utf8_encode($date)));

            $theme_element = $competition_element->AppendChild($dom->CreateElement('Theme'));
            $theme_element->AppendChild($dom->CreateTextNode(utf8_encode($theme)));

            $medium_element = $competition_element->AppendChild($dom->CreateElement('Medium'));
            $medium_element->AppendChild($dom->CreateTextNode(utf8_encode($medium)));

            $classification_node = $competition_element->AppendChild($dom->CreateElement('Classification'));
            $classification_node->AppendChild($dom->CreateTextNode(utf8_encode($classification)));

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
            // Create an Entries node

            $entries = $competition_element->AppendChild($dom->CreateElement('Entries'));
            // Iterate through all the entries for this competition
            foreach ($all_records_entries as $record_entries) {
                $user = get_user_by('id', $record_entries['Member_ID']);
                if (CommonHelper::isPaidMember($user->ID)) {

                    // Create an Entry node
                    $entry_element = $entries->AppendChild($dom->CreateElement('Entry'));

                    $id = $entry_element->AppendChild($dom->CreateElement('ID'));
                    $id->AppendChild($dom->CreateTextNode(utf8_encode($record_entries['ID'])));

                    $fname = $entry_element->AppendChild($dom->CreateElement('First_Name'));
                    $fname->AppendChild($dom->CreateTextNode(utf8_encode($user->first_name)));

                    $lname = $entry_element->AppendChild($dom->CreateElement('Last_Name'));
                    $lname->AppendChild($dom->CreateTextNode(utf8_encode($user->last_name)));

                    $title_node = $entry_element->AppendChild($dom->CreateElement('Title'));
                    $title_node->AppendChild($dom->CreateTextNode(utf8_encode($record_entries['Title'])));

                    $score_node = $entry_element->AppendChild($dom->CreateElement('Score'));
                    $score_node->AppendChild($dom->CreateTextNode(utf8_encode($record_entries['Score'])));

                    $award_node = $entry_element->AppendChild($dom->CreateElement('Award'));
                    $award_node->AppendChild($dom->CreateTextNode(utf8_encode($record_entries['Award'])));

                    $url_node = $entry_element->AppendChild($dom->CreateElement('Image_URL'));
                    $url_node->AppendChild(
                        $dom->CreateTextNode(utf8_encode(home_url($record_entries['Server_File_Name'])))
                    )
                    ;
                }
            }
            $record_competitions = $sth_competitions->fetch(\PDO::FETCH_ASSOC);
        }
        // Send the completed XML response back to the client
        // header('Content-Type: text/xml');
        $dom->save('peter.xml');
        echo $dom->saveXML();
    }
}
