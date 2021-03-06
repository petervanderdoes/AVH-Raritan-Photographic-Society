<?php

namespace RpsCompetition\Frontend\Shortcodes\CategoryWinners;

use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Helpers\PhotoHelper;

/**
 * Class CategoryWinnersModel
 *
 * @package   RpsCompetition\Frontend\Shortcodes\CategoryWinners
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class CategoryWinnersModel
{
    private $photo_helper;
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
        $this->photo_helper        = $photo_helper;
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
        $data               = [];
        $data['class']      = $class;
        $data['records']    = $entries;
        $data['thumb_size'] = $thumb_size;
        $data['images']     = [];
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
    public function getFacebookData($entries)
    {
        return $this->photo_helper->getFacebookThumbs($entries);
    }

    /**
     * Get the winner for the given class, award and date.
     *
     * @param string $class
     * @param string $award
     * @param string $date
     *
     * @return array
     */
    public function getWinner($class, $award, $date)
    {
        $competition_date = date('Y-m-d H:i:s', strtotime($date));
        $award_map        = ['1' => '1st', '2' => '2nd', '3' => '3rd', 'H' => 'HM'];

        return $this->query_miscellaneous->getWinner($competition_date, $award_map[$award], $class);
    }
}
