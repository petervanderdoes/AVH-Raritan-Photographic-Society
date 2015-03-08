<?php

namespace RpsCompetition\Frontend\Shortcodes\CategoryWinners;

use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Photo\Helper as PhotoHelper;

/**
 * Class CategoryWinnersModel
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\CategoryWinners
 */
class CategoryWinnersModel
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
     * Collect needed data to render the Category Winners
     *
     * @param string $class
     * @param array  $entries
     * @param string $thumb_size
     *
     * @return array
     */
    public function getAllData($class, $entries, $thumb_size)
    {
        $data = [];
        $data['class'] = $class;
        $data['records'] = $entries;
        $data['thumb_size'] = $thumb_size;
        $data['images'] = [];
        foreach ($data['records'] as $recs) {
            $data['images'][] = $this->photo_helper->dataPhotoGallery($recs, $data['thumb_size']);
        }

        return $data;
    }

    /**
     * Collect needed data to render the Category Winners
     *
     * @param string $class
     * @param array  $entries
     * @param string $thumb_size
     *
     * @return array
     */
    public function getCategoryWinners($class, $entries, $thumb_size)
    {
        $data = [];
        $data['class'] = $class;
        $data['records'] = $entries;
        $data['thumb_size'] = $thumb_size;
        $data['images'] = [];
        foreach ($data['records'] as $recs) {
            $data['images'][] = $this->photo_helper->dataPhotoGallery($recs, $data['thumb_size']);
        }

        return $data;
    }

    /**
     * Get the Facebook thumbs for the Category Winners Page.
     *
     * @param array $entries
     *
     * @return array
     */
    public function getFacebookThumbs($entries)
    {
        $images = [];
        foreach ($entries as $entry) {
            $images[] = $this->photo_helper->getThumbnailUrl($entry->Server_File_Name, 'fb_thumb');
        }

        return ['images' => $images];
    }

    /**
     * Get the winner for the given class, award and date.
     *
     * @param string $class
     * @param string $award
     * @param string $date
     *
     * @return QueryMiscellaneous
     */
    public function getWinner($class, $award, $date)
    {
        $competition_date = date('Y-m-d H:i:s', strtotime($date));
        $award_map = ['1' => '1st', '2' => '2nd', '3' => '3rd', 'H' => 'HM'];

        return $this->query_miscellaneous->getWinner($competition_date, $award_map[$award], $class);
    }
}
