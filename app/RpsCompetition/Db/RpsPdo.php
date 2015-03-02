<?php
namespace RpsCompetition\Db;

use PDO;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class RpsPdo
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Db
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
     *
     */
    public function __construct()
    {
        $this->engine = 'mysql';
        $this->host = DB_HOST;
        $this->database = RPS_DB_NAME;
        $this->user = RPS_DB_USER;
        $this->pass = RPS_DB_PASSWORD;
        $dns = $this->engine . ':dbname=' . $this->database . ";host=" . $this->host;
        parent::__construct($dns, $this->user, $this->pass);
    }
}
