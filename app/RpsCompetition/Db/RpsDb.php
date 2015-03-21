<?php
namespace RpsCompetition\Db;

/**
 * Class RpsDb
 *
 * @package   RpsCompetition\Db
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class RpsDb extends \wpdb
{
    /**
     * Constructor
     *
     */
    public function __construct()
    {
        parent::__construct(RPS_DB_USER, RPS_DB_PASSWORD, RPS_DB_NAME, DB_HOST);
        $this->show_errors(true);
        $this->user_id = get_current_user_id();
    }

    /**
     * Return a given date in the mysql format.
     *
     * @param string $date
     *
     * @return string
     */
    public function getMysqldate($date)
    {
        $date = new \DateTime($date);

        return $date->format('Y-m-d H:i:s');
    }
}
