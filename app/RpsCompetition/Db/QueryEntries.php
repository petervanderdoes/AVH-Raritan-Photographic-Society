<?php
namespace RpsCompetition\Db;

use RpsCompetition\Entity\Db\Entry;

/**
 * Class QueryEntries
 *
 * @package   RpsCompetition\Db
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 * @property int    ID
 * @property int    Competition_ID
 * @property int    Member_ID
 * @property string Title
 * @property string Client_File_Name
 * @property string Server_File_Name
 * @property string Date_Created
 * @property string Date_Modified
 * @property float  Score
 * @property string Award
 */
class QueryEntries
{
    private $rpsdb;

    /**
     * Constructor
     *
     * @param RpsDb $rpsdb
     */
    public function __construct(RpsDb $rpsdb)
    {
        $this->rpsdb = $rpsdb;
    }

    /**
     * Add an entry
     *
     * @param array $data
     * @param int   $user_id
     *
     * @return bool
     */
    public function addEntry($data, $user_id)
    {
        $data['Member_ID']    = $user_id;
        $data['Date_Created'] = current_time('mysql');
        $return               = $this->rpsdb->insert('entries', $data);

        return $return;
    }

    /**
     * Check if the title already exists for the given competition.
     *
     * @param int    $id
     * @param string $title
     * @param int    $user_id
     *
     * @return bool
     */
    public function checkDuplicateTitle($id, $title, $user_id)
    {
        $sql    = $this->rpsdb->prepare('SELECT ID
            FROM entries
            WHERE Competition_ID = %s
                AND Member_ID = %s
                AND Title = %s',
                                        $id,
                                        $user_id,
                                        $title);
        $return = $this->rpsdb->get_var($sql);
        if ($return > 0) {
            $return = true;
        } else {
            $return = false;
        }

        return $return;
    }

    /**
     * Count the entries for a member on a given competition date
     *
     * @param string $date
     * @param int    $user_id
     *
     * @return int
     */
    public function countEntriesByCompetitionDate($date, $user_id)
    {
        $sql    = $this->rpsdb->prepare('SELECT count(e.ID) AS Total_Entries_Submitted
                    FROM entries e, competitions c
                    WHERE e.Competition_ID = c.ID AND
                    c.Competition_Date = DATE(%s) AND
                    e.Member_ID = %s',
                                        $date,
                                        $user_id);
        $return = $this->rpsdb->get_var($sql);

        return $return;
    }

    /**
     * Get the amount of entries for the given competition.
     *
     * @param int $id
     * @param int $user_id
     *
     * @return int
     */
    public function countEntriesByCompetitionId($id, $user_id)
    {
        $sql    = $this->rpsdb->prepare('SELECT count(ID) FROM entries
            WHERE Competition_ID = %s
                AND Member_ID = %s',
                                        $id,
                                        $user_id);
        $return = $this->rpsdb->get_var($sql);

        return $return;
    }

    /**
     * Count entries submitted by this member for this competition date.
     *
     * @param int    $user_id
     * @param string $competition_date
     *
     * @return int
     */
    public function countEntriesSubmittedByMember($user_id, $competition_date)
    {
        $competition_date = $this->rpsdb->getMysqldate($competition_date);

        $sql    = $this->rpsdb->prepare('SELECT COUNT(entries.ID) AS Total_Submitted
            FROM competitions, entries
            WHERE competitions.ID = entries.Competition_ID
                AND	entries.Member_ID=%s
                AND competitions.Competition_Date = %s ',
                                        $user_id,
                                        $competition_date);
        $return = $this->rpsdb->get_var($sql);

        return $return;
    }

    /**
     * Delete an entry
     *
     * @param int $id
     *
     * @return bool
     */
    public function deleteEntry($id)
    {
        $result = $this->rpsdb->delete('entries', ['ID' => $id]);

        return $result;
    }

    /**
     * Get random entries that scored 8 or higher.
     * The amount of records returned can be set by the $limit argument.
     *
     * @param int $limit Amount of records to return. Default is 5.
     *
     * @return mixed
     */
    public function getEightsAndHigher($limit = 5)
    {
        $sql    = $this->rpsdb->prepare("SELECT
  c.Competition_Date,
  c.Classification,
  if(c.Classification = 'Beginner', 1,
     if(c.Classification = 'Advanced', 2,
        if(c.Classification = 'Salon', 3, 0))) AS \"Class_Code\",
  c.Medium,
  e.Title,
  e.Server_File_Name,
  e.Award,
  e.Member_ID
FROM competitions c, entries e
WHERE c.ID = e.Competition_ID AND
      e.Score >= 8
ORDER BY RAND()
LIMIT %d",
                                        $limit);
        $result = $this->rpsdb->get_results($sql, ARRAY_A);
        $return = $this->mapArrayEntry($result);

        return $return;
    }

    /**
     * Get all photos of the given member_id with a score that is 8 or higher.
     *
     * @param int $member_id
     *
     * @return array
     */
    public function getEightsAndHigherPerson($member_id)
    {
        $sql    = $this->rpsdb->prepare("SELECT
  c.Competition_Date,
  c.Classification,
  if(c.Classification = 'Beginner', 1,
     if(c.Classification = 'Advanced', 2,
        if(c.Classification = 'Salon', 3, 0))) AS \"Class_Code\",
  c.Medium,
  e.Title,
  e.Server_File_Name,
  e.Award,
  e.Member_ID
FROM competitions c, entries e
WHERE c.ID = e.Competition_ID AND
      e.Member_ID = %s AND
      e.Score >= 8
ORDER BY c.Competition_Date, Class_Code, c.Medium, e.Score",
                                        $member_id);
        $result = $this->rpsdb->get_results($sql, ARRAY_A);
        $return = $this->mapArrayEntry($result);

        return $return;
    }

    /**
     * Get entries submitted for the member on the given competition date in the given classification and medium
     *
     * @param int    $user_id
     * @param string $competition_date
     * @param string $classification
     * @param string $medium
     *
     * @return array
     */
    public function getEntriesSubmittedByMember($user_id, $competition_date, $classification, $medium)
    {
        $competition_date = $this->rpsdb->getMysqldate($competition_date);

        $sql    = $this->rpsdb->prepare('SELECT entries.*
            FROM competitions, entries
            WHERE competitions.ID = entries.Competition_ID
                AND entries.Member_ID = %s
                AND competitions.Competition_Date = %s
                AND competitions.Classification = %s
                AND competitions.Medium = %s',
                                        $user_id,
                                        $competition_date,
                                        $classification,
                                        $medium);
        $return = $this->rpsdb->get_results($sql);

        return $return;
    }

    /**
     * Get the Entry Record by Id
     *
     * @param int    $id
     * @param string $output
     *
     * @return Entry
     */
    public function getEntryById($id, $output = OBJECT)
    {
        $sql = $this->rpsdb->prepare('SELECT *
            FROM entries
            WHERE ID = %s',
                                     $id);

        $result = $this->rpsdb->get_row($sql, $output);

        return $this->mapEntry($result);
    }

    /**
     * Get the ID of the last inserted record.
     *
     * @return int
     */
    public function getInsertId()
    {
        return $this->rpsdb->insert_id;
    }

    /**
     * General query method
     *
     * @param array  $query_vars
     * @param string $output
     *
     * @return QueryEntries|array|int
     */
    public function query(array $query_vars, $output = OBJECT)
    {
        /**
         * @var string $join
         * @var string $where
         * @var int    $offset
         * @var int    $number
         * @var string $orderby
         * @var string $order
         * @var bool   $count
         */
        $defaults   = [
            'join'    => '',
            'where'   => '1=1',
            'offset'  => '',
            'number'  => '',
            'orderby' => 'ID',
            'order'   => 'ASC',
            'count'   => false
        ];
        $query_vars = wp_parse_args($query_vars, $defaults);
        extract($query_vars, EXTR_SKIP);

        $order = ('ASC' == strtoupper($order)) ? 'ASC' : 'DESC';

        $number = absint($number);
        $offset = absint($offset);

        if (!empty($number)) {
            if ($offset) {
                $limits = 'LIMIT ' . $offset . ',' . $number;
            } else {
                $limits = 'LIMIT ' . $number;
            }
        } else {
            $limits = '';
        }

        if ($count) {
            $fields  = 'COUNT(*)';
            $orderby = 'ID';
        } else {
            $fields = '*';
        }

        $query = sprintf('SELECT %s FROM entries %s WHERE %s ORDER BY %s %s %s',
                         $fields,
                         $join,
                         $where,
                         $orderby,
                         $order,
                         $limits);

        if ($count) {
            return $this->rpsdb->get_var($query);
        }

        if ($number == 1) {
            return $this->rpsdb->get_row($query, $output);
        }

        return $this->rpsdb->get_results($query, $output);
    }

    /**
     * Update an entry.
     * If the $data parameter has 'ID' set to a value, then entry will be updated.
     *
     * @param array $data
     *
     * @return \WP_Error|bool
     */
    public function updateEntry($data)
    {
        if (!empty($data['ID'])) {
            $entry_ID = (int) $data['ID'];
            $where    = ['ID' => $entry_ID];
            if (!isset($data['Date_Modified'])) {
                $data['Date_Modified'] = current_time('mysql');
            }
            $data = stripslashes_deep($data);
            if (false === $this->rpsdb->update('entries', $data, $where)) {
                return new \WP_Error('db_update_error',
                                     'Could not update entry in the database',
                                     $this->rpsdb->last_error);
            }
        }

        return true;
    }

    /**
     * Convert an array with stdClass to Entry class.
     *
     * The result of rpsdb->get_results return each object as a stdClass.
     * This function converts each array entry to an Entry class.
     *
     * @param array $result
     *
     * @return array
     */
    private function mapArrayEntry($result)
    {
        $return = [];
        foreach ($result as $record) {
            $return[] = $this->mapEntry($record);
        }

        return $return;
    }

    /**
     * Convert a stdClass record to the Entry class
     *
     * @param array|object $record
     *
     * @return Entry
     */
    private function mapEntry($record)
    {
        $entry = new Entry();
        if (is_object($record)) {
            $record = get_object_vars($record);
        }
        $entry->map($record);

        return $entry;
    }
}
