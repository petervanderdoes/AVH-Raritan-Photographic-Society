<?php
if (! defined('AVH_FRAMEWORK'))
    die('You are not allowed to call this page directly.');
class AVH_RPS_Public
{
    /**
     *
     * @var AVH_RPS_Core
     */
    private $_core;
    /**
     * @var AVH_Settings_Registry
     */
    private $_settings;
    /**
     * @var AVH_Class_registry
     */
    private $_classes;
    private $_core_options;

    // Properties of the logged in user
    private $member_id;
    private $username;
    private $first_name;
    private $last_name;
    private $email;
    private $active_user;
    private $digital_admin;
    private $club_officer;
    // Other commonly used globals
    private $digital_chair_email;
    private $errMsg;
    private $url_params;

    /**
     * PHP5 Constructor
     *
     */
    public function __construct()
    {
        // Get The Registry
        $this->_settings = AVH_RPS_Settings::getInstance();
        $this->_classes = AVH_RPS_Classes::getInstance();
        // Initialize the plugin
        //$this->_core = $this->_classes->load_class('Core', 'plugin', true);
        //$this->_core_options = $this->_core->getOptions();
        // Public actions and filters
        add_action('template_redirect', array(
            &$this , 'actionTemplate_Redirect_RPSWindowsClient'
        ));
    }

    function actionTemplate_Redirect_RPSWindowsClient()
    {
        if (array_key_exists('rpswinclient', $_REQUEST)) {

            $this->errMsg = "";
            // Properties of the logged in user
            status_header(200);
            switch ($_REQUEST['rpswinclient']) {
                case 'getcompdates':
                    $this->getCompetitionDates();
                    break;
                case 'download':
                    $this->getDownload();
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Enter description here ...
     */
    private function getCompetitionDates()
    {
        // Connect to the Database
        try {
            if (! $db = @mysql_connect($this->_settings->host, $this->_settings->uname, $this->_settings->pw))
                throw new Exception(mysql_error());
            if (! mysql_select_db($this->_settings->dbname))
                throw new Exception(mysql_error());
        } catch (Exception $e) {
            $this->doRESTError("Failed to obtain database handle " . $e->getMessage());
            die($e->getMessage());
        }
        try {
            $select = "SELECT DISTINCT(Competition_Date) FROM competitions ";
            $where = "WHERE ";
            if ($_GET['closed'] || $_GET['scored']) {
                if ($_GET['closed']) {
                    $where .= "Closed='" . $_GET['closed'] . "'";
                }
                if ($_GET['scored']) {
                    $where .= " AND Scored='" . $_GET['scored'] . "'";
                }
            } else {
                $where .= "Competition_Date >= CURDATE()";
            }
            if (! $rs = mysql_query($select . $where))
                throw new Exception(mysql_error());
        } catch (Exception $e) {
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
        while ($recs = mysql_fetch_assoc($rs)) {
            $dateParts = split(" ", $recs['Competition_Date']);
            $comp_date = $dom->createElement('Competition_Date');
            $comp_date->appendChild($dom->createTextNode($dateParts[0]));
            $root->appendChild($comp_date);
        }
        echo $dom->saveXML();
        die();
    }

    private function getDownload()
    {
        // TODO the RPS Client needs to be changed so the password is send encrypted instead of md5.
        // After this this function needs to be tested.
        $username = $_REQUEST['username'];
        $password = $_REQUEST['password'];
        $this->comp_date = $_REQUEST['comp_date'];
        $this->requested_medium = $_REQUEST['medium'];
        try {
            if (! $db = @mysql_connect($this->host, $this->uname, $this->pw))
                throw new Exception(mysql_error());
            if (! mysql_select_db($this->dbname))
                throw new Exception(mysql_error());
        } catch (Exception $e) {
            $this->doRESTError("Failed to obtain database handle " . $e->getMessage());
            die();
        }
        if ($db) {
            $user = wp_authenticate($username, $password);
            if (is_wp_error($user)) {
                $a=strip_tags($user->get_error_message());
                $this->doRESTError($a);
                die();
            }
            // @todo Check if the user has the role needed.
            $this->Send_Competitions($db);
        }
    }

    private function Send_Competitions($comp_date, $requested_medium, $db)
    {
        // Start building the XML response
        $dom = New DOMDocument('1.0');
        // Create the root node
        $rsp = $dom->CreateElement('rsp');
        $rsp = $dom->AppendChild($rsp);
        $rsp->SetAttribute('stat', 'ok');
        // Get all the competitions that match the requested date
        if ($this->requested_medium > "") {
            $medium_clause = ($requested_medium == "prints") ? " AND Medium like '%Prints' " : " AND Medium like '%Digital' ";
        }
        try {
            $sql = "SELECT ID, Competition_Date, Theme, Medium, Classification 
				FROM competitions 
				WHERE Competition_Date = DATE('$this->comp_date') 
			    $this->medium_clause
				ORDER BY Medium, Classification";
            if (! $rs = mysql_query($sql))
                throw new Exception(mysql_error());
        } catch (Exception $e) {
            $this->doRESTError("Failed to SELECT competition records with date = " . $this->comp_date . " from database - " . $e->getMessage());
            die();
        }
        //  Create a Competitions node
        $comps = $rsp->AppendChild($dom->CreateElement('Competitions'));
        // Iterate through all the matching Competitions and create corresponding Competition nodes
        while ($recs = mysql_fetch_assoc($rs)) {
            $comp_id = $recs['ID'];
            $dateParts = split(" ", $recs['Competition_Date']);
            $date = $dateParts[0];
            $theme = $recs['Theme'];
            $medium = $recs['Medium'];
            $classification = $recs['Classification'];
            // Create the competition node in the XML response
            $comp_node = $comps->AppendChild($dom->CreateElement('Competition'));
            $date_node = $comp_node->AppendChild($dom->CreateElement('Date'));
            $date_node->AppendChild($dom->CreateTextNode($date));
            $theme_node = $comp_node->AppendChild($dom->CreateElement('Theme'));
            $theme_node->AppendChild($dom->CreateTextNode($theme));
            $medium_node = $comp_node->AppendChild($dom->CreateElement('Medium'));
            $medium_node->AppendChild($dom->CreateTextNode($medium));
            $class_node = $comp_node->AppendChild($dom->CreateElement('Classification'));
            $class_node->AppendChild($dom->CreateTextNode($classification));
            // Get all the entries for this competition
            try {
                $sql = "SELECT members.FirstName, members.LastName, entries.ID, entries.Title,
						entries.Server_File_Name, entries.Score, entries.Award
						FROM members, entries 
						WHERE entries.Competition_ID = " . $comp_id . " AND 
					      entries.Member_ID = members.ID AND
						  members.Active = 'Y'
						ORDER BY members.LastName, members.FirstName, entries.Title";
                if (! $rs2 = mysql_query($sql))
                    throw new Exception(mysql_error());
            } catch (Exception $e) {
                $this->doRESTError("Failed to SELECT competition entries from database - " . $e->getMessage());
                die();
            }
            // Create an Entries node
            $entries = $comp_node->AppendChild($dom->CreateElement('Entries'));
            // Iterate through all the entries for this competition
            while ($recs2 = mysql_fetch_assoc($rs2)) {
                $entry_id = $recs2['ID'];
                $first_name = $recs2['FirstName'];
                $last_name = $recs2['LastName'];
                $title = $recs2['Title'];
                $score = $recs2['Score'];
                $award = $recs2['Award'];
                $server_file_name = $recs2['Server_File_Name'];
                // Create an Entry node
                $entry = $entries->AppendChild($dom->CreateElement('Entry'));
                $id = $entry->AppendChild($dom->CreateElement('ID'));
                $id->AppendChild($dom->CreateTextNode($entry_id));
                $fname = $entry->AppendChild($dom->CreateElement('First_Name'));
                $fname->AppendChild($dom->CreateTextNode($first_name));
                $lname = $entry->AppendChild($dom->CreateElement('Last_Name'));
                $lname->AppendChild($dom->CreateTextNode($last_name));
                $title_node = $entry->AppendChild($dom->CreateElement('Title'));
                $title_node->AppendChild($dom->CreateTextNode($title));
                $score_node = $entry->AppendChild($dom->CreateElement('Score'));
                $score_node->AppendChild($dom->CreateTextNode($score));
                $award_node = $entry->AppendChild($dom->CreateElement('Award'));
                $award_node->AppendChild($dom->CreateTextNode($award));
                // Convert the absolute server file name into a URL
                $limit = 1;
                $relative_path = str_replace(array(
                    '\\' , '#'
                ), array(
                    '/' , '%23'
                ), str_replace($_SERVER['DOCUMENT_ROOT'], '', $recs2['Server_File_Name'], $limit));
                $url_node = $entry->AppendChild($dom->CreateElement('Image_URL'));
                $url_node->AppendChild($dom->CreateTextNode("http://" . $_SERVER['SERVER_NAME'] . $relative_path));
            }
        }
        // Send the completed XML response back to the client
        //    	header('Content-Type: text/xml');
        echo $dom->saveXML();
    }

    private function doRESTError($errMsg)
    {
        echo '<?xml version="1.0" encoding="utf-8" ?>' . "\n";
        echo '<rsp stat="fail">' . "\n";
        echo '	<err msg="' . $errMsg . '" >' . "</err>\n";
        echo "</rsp>\n";
    }
}