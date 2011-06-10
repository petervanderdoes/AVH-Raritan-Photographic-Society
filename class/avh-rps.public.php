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
        $this->_core = $this->_classes->load_class('Core', 'plugin', true);
        $this->_core_options = $this->_core->getOptions();
		// Public actions and filters
		add_action( 'template_redirect', array(&this,'actionTemplate_Redirect_RPSWindowsClient' ))
        ;
    }

    function actionTemplate_Redirect_RPSWindowsClient()
    {
        // Properties of the Club
        $club_name = "Raritan Photographic Society";
        $club_short_name = "RPS";
        $club_max_entries_per_member_per_date = 4;
        $club_max_banquet_entries_per_member = 5;
        $club_season_start_month_num = 9;
        // Database credentials
        $host = 'localhost';
        $dbname = 'rarit0_data';
        $uname = 'rarit0_data';
        $pw = 'rps';
        // Properties of the logged in user
        $member_id = "";
        $username = "";
        $first_name = "";
        $last_name = "";
        $email = "";
        $active_user = "";
        $digital_admin = "";
        $club_officer = "";
        // Other commonly used globals
        $digital_chair_email = 'chapple@optonline.net';
        $errMsg = "";
        $url_params = "";
        if ($_SERVER['QUERY_STRING'] > "") {
            $url_params = "?" . $_SERVER['QUERY_STRING'];
        }
        
        // Connect to the Database
        try {
            if (! $db = @mysql_connect($host, $uname, $pw))
                throw new Exception(mysql_error());
            if (! mysql_select_db($dbname))
                throw new Exception(mysql_error());
        } catch (Exception $e) {
            REST_Error("Failed to obtain database handle " . $e->getMessage());
            die();
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
            REST_Error("Failed to SELECT list of competitions from database - " . $e->getMessage());
            die();
        }
        echo '<?xml version="1.0" encoding="utf-8" ?>' . "\n";
        echo '<rsp stat="ok">' . "\n";
        while ($recs = mysql_fetch_assoc($rs)) {
            $dateParts = split(" ", $recs['Competition_Date']);
            echo "  <Competition_Date>" . $dateParts[0] . "</Competition_Date>\n";
        }
        echo "</rsp>\n";
        die();
    }

    function REST_Error($errMsg)
    {
        echo '<?xml version="1.0" encoding="utf-8" ?>' . "\n";
        echo '<rsp stat="fail">' . "\n";
        echo '	<err msg="' . $errMsg . '" />' . "\n";
        echo "</rsp>\n";
    }
}