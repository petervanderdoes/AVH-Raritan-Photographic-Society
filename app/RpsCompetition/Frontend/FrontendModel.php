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

    /**
     * @param array $short_code_atts
     * @param int   $id
     *
     * @return array
     */
    public function getPostGalleryAttachments($short_code_atts, $id)
    {
        $attachments = [];
        if (!empty($short_code_atts['include'])) {
            $_attachments = get_posts(
                [
                    'include'        => $short_code_atts['include'],
                    'post_status'    => 'inherit',
                    'post_type'      => 'attachment',
                    'post_mime_type' => 'image',
                    'order'          => $short_code_atts['order'],
                    'orderby'        => $short_code_atts['orderby']
                ]
            );

            foreach ($_attachments as $key => $val) {
                $attachments[$val->ID] = $_attachments[$key];
            }
        } elseif (!empty($short_code_atts['exclude'])) {
            $attachments = get_children(
                [
                    'post_parent'    => $id,
                    'exclude'        => $short_code_atts['exclude'],
                    'post_status'    => 'inherit',
                    'post_type'      => 'attachment',
                    'post_mime_type' => 'image',
                    'order'          => $short_code_atts['order'],
                    'orderby'        => $short_code_atts['orderby']
                ]
            );
        } else {
            $attachments = get_children(
                [
                    'post_parent'    => $id,
                    'post_status'    => 'inherit',
                    'post_type'      => 'attachment',
                    'post_mime_type' => 'image',
                    'order'          => $short_code_atts['order'],
                    'orderby'        => $short_code_atts['orderby']
                ]
            );
        }

        return $attachments;
    }
}
