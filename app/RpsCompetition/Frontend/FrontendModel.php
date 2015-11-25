<?php

namespace RpsCompetition\Frontend;

use RpsCompetition\Helpers\PhotoHelper;

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
     * @var PhotoHelper
     */
    private $photo_helper;

    /**
     * FrontendModel constructor.
     *
     * @param PhotoHelper $photo_helper
     */
    public function __construct(PhotoHelper $photo_helper)
    {
        $this->photo_helper = $photo_helper;
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

    /**
     * Get the date for a Post Gallery Masonary Layout
     *
     * @param array $attachments
     *
     * @return array
     */
    public function getPostGalleryMasonryData($attachments)
    {
        $data = [];
        $data['images'] = $this->getAttachmentsData($attachments, '150w');

        return $data;
    }

    /**
     * Get the data for a Post Gallery Regular Layout
     *
     * @param array $short_code_atts
     * @param int   $id
     * @param int   $instance
     * @param array $attachments
     *
     * @return array
     */
    public function getPostGalleryData($short_code_atts, $id, $instance, $attachments)
    {
        $data = [];

        $columns = (int) $short_code_atts['columns'];

        $layout = strtolower($short_code_atts['layout']);

        $size_class = sanitize_html_class($short_code_atts['size']);

        $data['general']['instance'] = $instance;
        $data['general']['id'] = $id;
        $data['general']['columns'] = $columns;
        $data['general']['size_class'] = $size_class;
        $data['general']['layout'] = $layout;

        $data['images'] = $this->getAttachmentsData($attachments, '150w');

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
    private function getPhotoCreditData($title, $first_name, $last_name)
    {
        $data = [];
        $data['title'] = $title;
        $data['credit'] = $first_name . ' ' . $last_name;

        return $data;
    }

    /**
     * Collect image data
     *
     * @param \RpsCompetition\Db\QueryEntries $record
     * @param string                          $thumb_size
     * @param array                           $caption
     *
     * @return array<string,string|array>
     */
    private function getImageData($record, $thumb_size, $caption)
    {
        $data = [];
        $data['url_large'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, '800');
        $data['url_thumb'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, $thumb_size);
        $data['dimensions'] = $this->photo_helper->getThumbnailImageSize($record->Server_File_Name, $thumb_size);
        $data['caption'] = $this->getPhotoCreditData($caption['title'], $caption['first_name'], $caption['last_name']);

        return $data;
    }

    /**
     * Collect data of all the attachmenets
     *
     * @param array $attachments
     * @param       $thumb_size
     *
     * @return array
     */
    private function getAttachmentsData($attachments, $thumb_size)
    {
        $data = [];

        foreach ($attachments as $id => $attachment) {
            $img_url = wp_get_attachment_url($id);
            $home_url = home_url();
            if (substr($img_url, 0, strlen($home_url)) == $home_url) {
                /** @var \RpsCompetition\Db\QueryEntries $entry */
                $entry = new \stdClass;
                $img_relative_path = substr($img_url, strlen($home_url));
                $entry->Server_File_Name = $img_relative_path;
                $entry->ID = $attachment->ID;

                $caption_data = [];
                if (trim($attachment->post_excerpt)) {
                    $caption_data['title'] = $attachment->post_excerpt;
                } else {
                    $caption_data['title'] = $attachment->post_title;
                }
                $caption_data['first_name'] = get_post_meta($attachment->ID, '_rps_photographer_name', true);
                $caption_data['last_name'] = '';

                $data[] = $this->getImageData($entry, $thumb_size, $caption_data);
            }
        }

        return $data;
    }
}
