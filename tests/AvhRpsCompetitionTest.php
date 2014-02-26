<?php

class AvhRpsCompetitiontest extends WP_UnitTestCase
{
    /**
     * @var PDO
     */
    private $pdo;

    /**
     * Setup the test environment.
     */
    public function setUp()
    {   $this->pdo = new PDO($GLOBALS['db_dsn'], $GLOBALS['db_username'], $GLOBALS['db_password']);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->query("
        CREATE TABLE IF NOT EXISTS `competitions` (
        `ID` int(11) NOT NULL AUTO_INCREMENT,
        `Competition_Date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        `Medium` varchar(32) NOT NULL DEFAULT '',
        `Classification` varchar(10) NOT NULL DEFAULT '',
        `Theme` varchar(64) NOT NULL DEFAULT '',
        `Date_Created` datetime DEFAULT NULL,
        `Date_Modified` datetime DEFAULT NULL,
        `Closed` char(1) NOT NULL DEFAULT '',
        `Scored` char(1) NOT NULL DEFAULT '',
        `Close_Date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        `Max_Entries` int(11) NOT NULL DEFAULT '2',
        `Num_Judges` int(11) NOT NULL DEFAULT '1',
        `Special_Event` char(1) NOT NULL DEFAULT 'N',
        PRIMARY KEY (`ID`)
        )");

        $this->pdo->query("CREATE TABLE IF NOT EXISTS `entries` (
        `ID` int(11) NOT NULL AUTO_INCREMENT,
        `Competition_ID` int(11) NOT NULL DEFAULT '0',
        `Member_ID` int(11) NOT NULL DEFAULT '0',
        `Title` varchar(128) NOT NULL DEFAULT '',
        `Client_File_Name` varchar(128) NOT NULL DEFAULT '',
        `Server_File_Name` varchar(255) DEFAULT NULL,
        `Date_Created` datetime DEFAULT NULL,
        `Date_Modified` datetime DEFAULT NULL,
        `Score` decimal(10,0) DEFAULT NULL,
        `Award` varchar(4) DEFAULT NULL,
        PRIMARY KEY (`ID`)
        )");

        $this->pdo->query("CREATE TABLE IF NOT EXISTS `events` (
        `ID` int(11) NOT NULL AUTO_INCREMENT,
        `Event_Name` varchar(128) NOT NULL DEFAULT '',
        `Event_Date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        `Coordinator_ID` int(11) NOT NULL DEFAULT '0',
        `Registration_Required` char(1) NOT NULL DEFAULT 'Y',
        `Max_Attendees` int(11) NOT NULL DEFAULT '0',
        `Cost_per_Attendee` decimal(6,2) NOT NULL DEFAULT '0.00',
        `Description` text,
        `Location` text,
        `Guest` varchar(64) DEFAULT NULL,
        PRIMARY KEY (`ID`)
        )");

        $this->pdo->query("CREATE TABLE IF NOT EXISTS `event_attendees` (
        `ID` int(11) NOT NULL AUTO_INCREMENT,
        `Event_ID` int(11) NOT NULL DEFAULT '0',
        `Member_ID` int(11) NOT NULL DEFAULT '0',
        `Num_Attendees` int(11) NOT NULL DEFAULT '0',
        PRIMARY KEY (`ID`)
        )");

        $this->pdo->query("CREATE TABLE IF NOT EXISTS `members` (
        `ID` int(11) NOT NULL AUTO_INCREMENT,
        `Username` varchar(32) NOT NULL DEFAULT '',
        `Password` varchar(32) NOT NULL DEFAULT '',
        `LastName` varchar(32) NOT NULL DEFAULT '',
        `FirstName` varchar(32) NOT NULL DEFAULT '',
        `EmailAddress` varchar(128) NOT NULL DEFAULT '',
        `Security_Question` varchar(128) NOT NULL DEFAULT '',
        `Security_Answer` varchar(32) NOT NULL DEFAULT '',
        `Digital_Admin` char(1) DEFAULT NULL,
        `Date_Created` datetime DEFAULT NULL,
        `Date_Modified` datetime DEFAULT NULL,
        `Active` char(1) NOT NULL DEFAULT '',
        `Club_Officer` char(1) NOT NULL DEFAULT 'N',
        PRIMARY KEY (`ID`)
        )");

        $this->pdo->query("CREATE TABLE IF NOT EXISTS `member_classifications` (
        `Member_ID` int(11) NOT NULL DEFAULT '0',
        `Medium` varchar(32) NOT NULL DEFAULT '',
        `Classification` varchar(10) DEFAULT NULL,
        `Date_Created` datetime DEFAULT NULL,
        `Date_Modified` datetime DEFAULT NULL,
        KEY `Member_ID` (`Member_ID`)
        )");

        parent::setUp();
    }

    /**
     * Run a simple test to ensure that the tests are running
     */
    public function testAlwaysTrue()
    {
        $this->assertTrue(true);
    }

    /**
     * Verify that WordPress is installed and is the version that we requested
     */
    function testWpVersion()
    {
        if (!getenv('TRAVIS_PHP_VERSION')) {
            $this->markTestSkipped('Not running on Travis CI');
        }

        // grab the requested version
        $requested_version = getenv('WP_VERSION');

        // trunk is always "master" in github terms, but WordPress has a specific way of describing it
        // grab the exact version number to verify that we're on trunk
        if ($requested_version == 'master') {
            $file = file_get_contents('https://raw.github.com/WordPress/WordPress/master/wp-includes/version.php');
            preg_match('#\$wp_version = \'([^\']+)\';#', $file, $matches);
            $requested_version = $matches[1];
        }

        $this->assertEquals(get_bloginfo('version'), $requested_version);
    }
}
