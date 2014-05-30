<?php
namespace RpsCompetition\Api;

use DOMDocument;
use PDO;
use RpsCompetition\Db\RpsPdo;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class Client
{

    /**
     * Create a XML File with the competition dates
     *
     * @param \Illuminate\Http\Request $request
     */
    public static function sendXmlCompetitionDates(\Illuminate\Http\Request $request)
    {
        // Connect to the Database
        try {
            $db = new RpsPdo();
        } catch (\PDOException $e) {
            $this->doRESTError("Failed to obtain database handle " . $e->getMessage());
            die($e->getMessage());
        }

        try {
            $select = "SELECT DISTINCT(Competition_Date) FROM competitions ";
            if ($request->has('closed') || $request->has('scored')) {
                $where = "WHERE";
                if ($request->has('closed')) {
                    $where .= " Closed=:closed";
                }
                if ($request->has('scored')) {
                    $where .= " AND Scored=:scored";
                }
            } else {
                $where .= " Competition_Date >= CURDATE()";
            }

            $sth = $db->prepare($select . $where);
            if ($request->has('closed')) {
                $_closed = $request->input('closed');
                $sth->bindParam(':closed', $_closed, \PDO::PARAM_STR, 1);
            }
            if ($request->has('scored')) {
                $_scored = $request->input('scored');
                $sth->bindParam(':scored', $_scored, \PDO::PARAM_STR, 1);
            }
            $sth->execute();
        } catch (\PDOException $e) {
            $this->doRESTError("Failed to SELECT list of competitions from database - " . $e->getMessage());
            die($e->getMessage());
        }

        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('rsp');
        $dom->appendChild($root);
        $stat = $dom->createAttribute("stat");
        $root->appendChild($stat);
        $value = $dom->CreateTextNode("ok");
        $stat->appendChild($value);
        $recs = $sth->fetch(\PDO::FETCH_ASSOC);
        while ($recs != false) {
            $dateParts = explode(" ", $recs['Competition_Date']);
            $comp_date = $root->appendChild($dom->createElement('Competition_Date'));
            $comp_date->appendChild($dom->createTextNode($dateParts[0]));
            $recs = $sth->fetch(\PDO::FETCH_ASSOC);
        }
        echo $dom->saveXML();
        $db = null;
        die();
    }

    /**
     * Handles request by client to download images for a particular date,
     *
     * @param \Illuminate\Http\Request $request
     */
    public static function sendCompetitions(\Illuminate\Http\Request $request)
    {
        $username = $request->input('username');
        $password = $request->input('password');
        try {
            $db = new RpsPdo();
        } catch (\PDOException $e) {
            $this->doRESTError("Failed to obtain database handle " . $e->getMessage());
            die($e->getMessage());
        }
        if ($db !== false) {
            $user = wp_authenticate($username, $password);
            if (is_wp_error($user)) {
                $a = strip_tags($user->get_error_message());
                $this->doRESTError($a);
                die();
            }
            // @todo Check if the user has the role needed.
            $this->sendXmlCompetitions($db, $request->input('medium'), $request->input('comp_date'));
        }
        die();
    }

    /**
     * Handles the uploading of the score file
     *
     * @param \Illuminate\Http\Request $request
     */
    public static function doUploadScore(\Illuminate\Http\Request $request)
    {
        $username = $request->input('username');
        $password = $request->input('password');
        $comp_date = $request->input('date');
        try {
            $db = new RpsPdo();
        } catch (\PDOException $e) {
            $this->doRESTError("Failed to obtain database handle " . $e->getMessage());
            die();
        }
        if ($db !== false) {
            $user = wp_authenticate($username, $password);
            if (is_wp_error($user)) {
                $a = strip_tags($user->get_error_message());
                $this->doRESTError("Unable to authenticate: $a");
                die();
            }
        }
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
        $dest_name = "scores_" . $comp_date . ".xml";
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
        $warning = "  <info>Scores successfully uploaded</info>\n" . $warning;
        $this->doRESTSuccess($warning);
        die();
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
            $medium_clause = ($requested_medium == "prints") ? " AND Medium like '%Prints' " : " AND Medium like '%Digital' ";
        }
        $sql = "SELECT ID, Competition_Date, Theme, Medium, Classification
        FROM competitions
        WHERE Competition_Date = DATE(:compdate) AND Closed = 'Y' $medium_clause
        ORDER BY Medium, Classification";
        try {
            $sth_competitions = $db->prepare($sql);
            $sth_competitions->bindParam(':compdate', $comp_date);
            $sth_competitions->execute();
        } catch (\Exception $e) {
            $this->doRESTError("Failed to SELECT competition records with date = " . $comp_date . " from database - " . $e->getMessage());
            die();
        }
        // Create a Competitions node
        $xml_competions = $rsp->AppendChild($dom->CreateElement('Competitions'));
        // Iterate through all the matching Competitions and create corresponding Competition nodes
        $record_competitions = $sth_competitions->fetch(\PDO::FETCH_ASSOC);
        while ($record_competitions !== false) {
            $comp_id = $record_competitions['ID'];
            $dateParts = explode(" ", $record_competitions['Competition_Date']);
            $date = $dateParts[0];
            $theme = $record_competitions['Theme'];
            $medium = $record_competitions['Medium'];
            $classification = $record_competitions['Classification'];
            // Create the competition node in the XML response
            $competition_element = $xml_competions->AppendChild($dom->CreateElement('Competition'));

            $date_element = $competition_element->AppendChild($dom->CreateElement('Date'));
            $date_element->AppendChild($dom->CreateTextNode(utf8_encode($date)));

            $theme_element = $competition_element->AppendChild($dom->CreateElement('Theme'));
            $theme_element->AppendChild($dom->CreateTextNode(utf8_encode($theme)));

            $medium_element = $competition_element->AppendChild($dom->CreateElement('Medium'));
            $medium_element->AppendChild($dom->CreateTextNode(utf8_encode($medium)));

            $xml_classification_node = $competition_element->AppendChild($dom->CreateElement('Classification'));
            $xml_classification_node->AppendChild($dom->CreateTextNode(utf8_encode($classification)));

            // Get all the entries for this competition
            try {
                $sql = "SELECT entries.ID, entries.Title, entries.Member_ID,
            entries.Server_File_Name, entries.Score, entries.Award
            FROM entries
                WHERE entries.Competition_ID = :comp_id
                        ORDER BY entries.Member_ID, entries.Title";
                $sth_entries = $db->prepare($sql);
                $sth_entries->bindParam(':comp_id', $comp_id, \PDO::PARAM_INT, 11);
                $sth_entries->execute();
            } catch (\Exception $e) {
                $this->doRESTError("Failed to SELECT competition entries from database - " . $e->getMessage());
                die();
            }
            $all_records_entries = $sth_entries->fetchAll();
            // Create an Entries node

            $entries = $competition_element->AppendChild($dom->CreateElement('Entries'));
            // Iterate through all the entries for this competition
            foreach ($all_records_entries as $record_entries) {
                $user = get_user_by('id', $record_entries['Member_ID']);
                if ($this->core->isPaidMember($user->ID)) {
                    $entry_id = $record_entries['ID'];
                    $first_name = $user->first_name;
                    $last_name = $user->last_name;
                    $title = $record_entries['Title'];
                    $score = $record_entries['Score'];
                    $award = $record_entries['Award'];
                    $server_file_name = $record_entries['Server_File_Name'];
                    // Create an Entry node
                    $entry_element = $entries->AppendChild($dom->CreateElement('Entry'));
                    $id = $entry_element->AppendChild($dom->CreateElement('ID'));
                    $id->AppendChild($dom->CreateTextNode(utf8_encode($entry_id)));
                    $fname = $entry_element->AppendChild($dom->CreateElement('First_Name'));
                    $fname->AppendChild($dom->CreateTextNode(utf8_encode($first_name)));
                    $lname = $entry_element->AppendChild($dom->CreateElement('Last_Name'));
                    $lname->AppendChild($dom->CreateTextNode(utf8_encode($last_name)));
                    $title_node = $entry_element->AppendChild($dom->CreateElement('Title'));
                    $title_node->AppendChild($dom->CreateTextNode(utf8_encode($title)));
                    $score_node = $entry_element->AppendChild($dom->CreateElement('Score'));
                    $score_node->AppendChild($dom->CreateTextNode(utf8_encode($score)));
                    $award_node = $entry_element->AppendChild($dom->CreateElement('Award'));
                    $award_node->AppendChild($dom->CreateTextNode(utf8_encode($award)));
                    // Convert the absolute server file name into a URL
                    $image_url = home_url($record_entries['Server_File_Name']);
                    $url_node = $entry_element->AppendChild($dom->CreateElement('Image_URL'));
                    $url_node->AppendChild($dom->CreateTextNode(utf8_encode($image_url)));
                }
            }
            $record_competitions = $sth_competitions->fetch(\PDO::FETCH_ASSOC);
        }
        // Send the completed XML response back to the client
        // header('Content-Type: text/xml');
        $dom->save('peter.xml');
        echo $dom->saveXML();
    }

    /**
     * Handle the uploaded score from the RPS Client.
     */

    /**
     * Handle the XML file containing the scores and add them to the database
     *
     * @param object $db
     *            Database handle.
     */
    private function handleUploadScoresFile($db, $file_name)
    {
        $warning = '';
        $score = '';
        $award = '';
        $entry_id = '';

        if (!$xml = simplexml_load_file($file_name)) {
            $this->doRESTError("Failed to open scores XML file");
            die();
        }
        try {
            $sql = "UPDATE `entries` SET `Score` = :score, `Date_Modified` = NOW(), `Award` = :award WHERE `ID` = :entryid";
            $sth = $db->prepare($sql);
            $sth->bindParam(':score', $score, PDO::PARAM_STR);
            $sth->bindParam(':award', $award, PDO::PARAM_STR);
            $sth->bindParam(':entryid', $entry_id, PDO::PARAM_INT);
        } catch (\PDOException $e) {
            $this->doRESTError("Error - " . $e->getMessage() . " - $sql");
            die();
        }

        foreach ($xml->Competition as $comp) {
            $comp_date = $comp->Date;
            $classification = $comp->Classification;
            $medium = $comp->Medium;

            foreach ($comp->Entries as $entries) {
                foreach ($entries->Entry as $entry) {
                    $entry_id = $entry->ID;
                    $first_name = html_entity_decode($entry->First_Name);
                    $last_name = html_entity_decode($entry->Last_Name);
                    $title = html_entity_decode($entry->Title);
                    $score = html_entity_decode($entry->Score);
                    if (empty($entry->Award)) {
                        $award = null;
                    } else {
                        $award = html_entity_decode($entry->Award);
                    }

                    if ($entry_id != "") {
                        if ($score != "") {
                            try {
                                $sth->execute();
                            } catch (\PDOException $e) {
                                $this->doRESTError("Failed to UPDATE scores in database - " . $e->getMessage() . " - $sql");
                                die();
                            }
                            if ($sth->rowCount() < 1) {
                                $warning .= "  <info>$comp_date, $first_name $last_name, $title -- Row failed to update</info>\n";
                            }
                        }
                    } else {
                        $warning .= "  <info>$comp_date, $first_name $last_name, $title -- ID is Null -- skipped</info>\n";
                    }
                }
            }

            // Mark this competition as scored
            try {
                $sql = "UPDATE competitions SET Scored='Y', Date_Modified=NOW()
                        WHERE Competition_Date='$comp_date' AND
                        Classification='$classification' AND
                        Medium = '$medium'";
                if (!$rs = mysql_query($sql)) {
                    throw new \Exception(mysql_error());
                }
            } catch (\Exception $e) {
                $this->doRESTError("Failed to execute UPDATE to set Scored flag to Y in database for $comp_date / $classification");
                die();
            }
            if (mysql_affected_rows() < 1) {
                $this->doRESTError("No rows updated when setting Scored flag to Y in database for $comp_date / $classification");
                die();
            }
        }

        return $warning;
    }

    /**
     * Create a REST error
     *
     * @param string $errMsg
     *            The actual error message
     */
    private function doRESTError($errMsg)
    {
        $this->doRESTResponse('fail', '<err msg="' . $errMsg . '" ></err>');
    }

    /**
     * Create a REST success message
     *
     * @param string $message
     *            The actual messsage
     */
    private function doRESTSuccess($message)
    {
        $this->doRESTResponse("ok", $message);
    }

    /**
     * Create the REST respone
     *
     * @param string $status
     * @param string $message
     */
    private function doRESTResponse($status, $message)
    {
        echo '<?xml version="1.0" encoding="utf-8" ?>' . "\n";
        echo '<rsp stat="' . $status . '">' . "\n";
        echo '	' . $message . "\n";
        echo "</rsp>\n";
    }
}