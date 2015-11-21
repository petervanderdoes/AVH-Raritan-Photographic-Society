<?php
namespace RpsCompetition\Db;

use PDO;

/**
 * Class RpsPdo
 *
 * @package   RpsCompetition\Db
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class RpsPdo extends \PDO
{
    private $database;
    private $engine;
    private $host;
    private $pass;
    private $user;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->engine = 'mysql';
        $this->host = DB_HOST;
        $this->database = RPS_DB_NAME;
        $this->user = RPS_DB_USER;
        $this->pass = RPS_DB_PASSWORD;
        $dns = $this->engine . ':dbname=' . $this->database . ';host=' . $this->host;
        parent::__construct($dns, $this->user, $this->pass);
    }
}
