<?php

namespace RpsCompetition\Frontend;

/**
 * Class FrontendModel
 *
 * @package   RpsCompetition\Frontend
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2015, AVH Software
 */
class FrontendModel
{
    /**
     * FrontendModel constructor.
     */
    public function __construct()
    {
    }

    /**
     * Get all the entries for Facebook Thumbs from the given attachments.
     *
     * @param array $attachments
     *
     * @return array
     */
    public function getFacebookThumbEntries($attachments)
    {
        $entries = [];
        $attachments_key = array_keys($attachments);
        foreach ($attachments_key as $id) {
            $img_url = wp_get_attachment_url($id);
            $home_url = home_url();
            if (substr($img_url, 0, strlen($home_url)) == $home_url) {
                $entry = new \stdClass;
                $img_relative_path = substr($img_url, strlen($home_url));
                $entry->Server_File_Name = $img_relative_path;
                $entries[] = $entry;
            }
        }

        return $entries;
    }
}
