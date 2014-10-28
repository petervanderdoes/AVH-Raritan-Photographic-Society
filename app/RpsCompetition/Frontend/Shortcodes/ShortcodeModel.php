<?php

namespace RpsCompetition\Frontend\Shortcodes;

use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Photo\Helper;

class ShortcodeModel
{
    private $photo_helper;
    private $query_miscellaneous;

    public function __construct(Helper $photo_helper, QueryMiscellaneous $query_miscellaneous)
    {
        $this->photo_helper = $photo_helper;
        $this->query_miscellaneous = $query_miscellaneous;
    }

    /**
     * Display the Facebook thumbs for the Category Winners Page.
     *
     * @param array $entries
     *
     * @return string
     */
    public function getCategoryWinnersFacebookThumbs($entries)
    {
        $images = array();
        foreach ($entries as $entry) {
            $images[] = $this->photo_helper->getThumbnailUrl($entry->Server_File_Name, 'fb_thumb');
        }

        return array('images' => $images);
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
        $award_map = array('1' => '1st', '2' => '2nd', '3' => '3rd', 'H' => 'HM');

        return $this->query_miscellaneous->getWinner($competition_date, $award_map[$award], $class);
    }

    /**
     * Collect needed data to render the Category Winners
     *
     * @param string $class
     * @param string $entries
     * @param string $thumb_size
     *
     * @return array
     */
    public function getCategoryWinners($class, $entries, $thumb_size)
    {
        $data = array();
        $data['class'] = $class;
        $data['records'] = $entries;
        $data['thumb_size'] = $thumb_size;
        $data['images'] = array();
        foreach ($data['records'] as $recs) {
            $data['images'][] = $this->dataPhotoGallery($recs, $data['thumb_size']);
        }

        return $data;
    }

    /**
     * Collect needed data to render the photo credit
     *
     * @param string $title
     * @param string $first_name
     * @param string $last_name
     *
     * @return array
     */
    private function dataPhotoCredit($title, $first_name, $last_name)
    {
        $data = array();
        $data['title'] = $title;
        $data['credit'] = "$first_name $last_name";

        return $data;
    }

    /**
     * Collect needed data to render a photo in masonry style.
     *
     * @param QueryEntries $record
     * @param string       $thumb_size
     *
     * @return array<string,string|array>
     */
    private function dataPhotoGallery($record, $thumb_size)
    {

        $data = array();
        $user_info = get_userdata($record->Member_ID);
        $title = $record->Title;
        $last_name = $user_info->user_lastname;
        $first_name = $user_info->user_firstname;
        $data['url_800'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, '800');
        $data['url_thumb'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, $thumb_size);
        $data['dimensions'] = $this->photo_helper->getThumbnailImageSize($record->Server_File_Name, $thumb_size);
        $data['title'] = $title . ' by ' . $first_name . ' ' . $last_name;
        $data['caption'] = $this->dataPhotoCredit($title, $first_name, $last_name);

        return $data;
    }
} 