<?php
namespace RpsCompetition\Frontend\Shortcodes\PersonWinners;

use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Photo\Helper as PhotoHelper;

/**
 * Class PersonWinnersModel
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\PersonWinners
 */
class PersonWinnersModel
{
    /** @var PhotoHelper */
    private $photo_helper;
    /** @var QueryMiscellaneous */
    private $query_miscellaneous;

    /**
     * Constructor
     *
     * @param QueryMiscellaneous $query_miscellaneous
     * @param PhotoHelper        $photo_helper
     */
    public function __construct(QueryMiscellaneous $query_miscellaneous, PhotoHelper $photo_helper)
    {

        $this->query_miscellaneous = $query_miscellaneous;
        $this->photo_helper = $photo_helper;
    }

    /**
     * Get given amount of random images for the given user.
     *
     * @param integer $user_id
     * @param integer $amount_of_images
     *
     * @return array
     */
    public function getPersonWinners($user_id, $amount_of_images)
    {
        $entries = $this->query_miscellaneous->getEightsAndHigherPerson($user_id);
        $entries_id = array_rand($entries, $amount_of_images);
        $data = [];
        $data['thumb_size'] = '150w';
        $data['records'] = [];
        foreach ($entries_id as $key) {
            $data['entries'][] = $entries[$key];
        }
        foreach ($data['entries'] as $entry) {
            $data['images'][] = $this->photo_helper->dataPhotoMasonry($entry, $data['thumb_size']);
        }

        return $data;
    }
}
