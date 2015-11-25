<?php
namespace RpsCompetition\Frontend;

use Illuminate\Config\Repository as Settings;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Definitions\ViewAbstract;
use RpsCompetition\Helpers\PhotoHelper;

/**
 * Class FrontendView
 *
 * @package   RpsCompetition\Frontend
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class FrontendView extends ViewAbstract
{
    /** @var PhotoHelper */
    private $photo_helper;
    private $settings;

    /**
     * Constructor
     *
     * @param string      $template_dir
     * @param string      $cache_dir
     * @param Settings    $settings
     * @param PhotoHelper $photo_helper
     */
    public function __construct($template_dir, $cache_dir, Settings $settings, PhotoHelper $photo_helper)
    {
        $this->settings = $settings;
        $this->photo_helper = $photo_helper;
        parent::__construct($template_dir, $cache_dir);
    }

    /**
     * Render Facebook
     *
     * @param array $data
     *
     * @return string
     */
    public function renderFacebookThumbs($data)
    {

        return $this->fetch('facebook.html.twig', $data);
    }

    /**
     * Render a Masonry Post Gallery
     *
     * @param array $data
     *
     * @return string
     */
    public function renderGalleryMasonry($data)
    {
        return $this->fetch('gallery-masonry.html.twig', $data);
    }

    /**
     * Render a regular Post Gallery
     *
     * @param array $data
     *
     * @return string
     */
    public function renderPostGallery($data)
    {

        return $this->fetch('post-gallery.html.twig', $data);
    }

    /**
     * Render the feed of attachements.
     *
     * @param array  $attachments
     * @param string $size
     *
     * @see \RpsCompetition\Frontend\Frontend::filterPostGallery
     * @return string
     */
    public function renderPostGalleryFeed($attachments, $size)
    {
        $output = "\n";
        $attachments_id = array_keys($attachments);
        foreach ($attachments_id as $id) {
            $output .= wp_get_attachment_link($id, $size, true) . "\n";
        }

        return $output;
    }

    /**
     * Render the Showcase competition thumbnails
     *
     * @param array $data
     *
     * @see Frontend::actionShowcaseCompetitionThumbnails
     * @return string
     */
    public function renderShowcaseCompetitionThumbnails($data)
    {
        $data['images'] = [];
        foreach ($data['records'] as $recs) {
            $data['images'][] = $this->dataPhotoGallery($recs, $data['thumb_size']);
        }
        unset ($data['records']);

        return $this->fetch('showcase.html.twig', $data);
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
        $data = [];
        $data['title'] = $title;
        $data['credit'] = $first_name . ' ' . $last_name;

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

        $data = [];
        $user_info = get_userdata($record->Member_ID);
        $title = $record->Title;
        $last_name = $user_info->user_lastname;
        $first_name = $user_info->user_firstname;
        $data['url_large'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, '800');
        $data['url_thumb'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, $thumb_size);
        $data['dimensions'] = $this->photo_helper->getThumbnailImageSize($record->Server_File_Name, $thumb_size);
        $data['title'] = $title . ' by ' . $first_name . ' ' . $last_name;
        $data['caption'] = $this->dataPhotoCredit($title, $first_name, $last_name);

        return $data;
    }
}
