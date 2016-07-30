<?php
namespace RpsCompetition\Frontend\Shortcodes\PersonWinners;

use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Helpers\PhotoHelper;

/**
 * Class PersonWinnersModel
 *
 * @package   RpsCompetition\Frontend\Shortcodes\PersonWinners
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class PersonWinnersModel
{
    private $photo_helper;
    private $query_entries;

    /**
     * Constructor
     *
     * @param QueryEntries $query_entries
     * @param PhotoHelper  $photo_helper
     */
    public function __construct(QueryEntries $query_entries, PhotoHelper $photo_helper)
    {

        $this->query_entries = $query_entries;
        $this->photo_helper  = $photo_helper;
    }

    /**
     * Get given amount of random images for the given user.
     *
     * @param int $user_id
     * @param int $amount_of_images
     *
     * @return array
     */
    public function getPersonWinners($user_id, $amount_of_images)
    {
        $entries            = $this->query_entries->getEightsAndHigherPerson($user_id);
        $entries_id         = array_rand($entries, $amount_of_images);
        $data               = [];
        $data['thumb_size'] = '150w';
        $data['records']    = [];
        foreach ($entries_id as $key) {
            $data['entries'][] = $entries[$key];
        }
        foreach ($data['entries'] as $entry) {
            $data['images'][] = $this->photo_helper->dataPhotoMasonry($entry, $data['thumb_size']);
        }

        return $data;
    }
}
