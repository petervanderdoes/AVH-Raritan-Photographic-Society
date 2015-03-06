<?php
namespace RpsCompetition\Photo;

use Illuminate\Http\Request;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Intervention\Image\ImageManagerStatic as Image;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Constants;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Settings;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class Helper
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Photo
 */
class Helper
{
    private $request;
    private $rpsdb;
    private $settings;

    /**
     * Constructor
     *
     * @param Settings $settings
     * @param Request  $request
     * @param RpsDb    $rpsdb
     */
    public function __construct(Settings $settings, Request $request, RpsDb $rpsdb)
    {
        $this->settings = $settings;
        $this->request = $request;
        $this->rpsdb = $rpsdb;
    }

    /**
     * Create the most commonly used thumbnails.
     *
     * @param QueryEntries $entry
     */
    public function createCommonThumbnails($entry)
    {
        $standard_size = ['75', '150w', '800', 'fb_thumb'];

        foreach ($standard_size as $size) {
            $this->createThumbnail($entry->Server_File_Name, $size);
        }
    }

    /**
     * Create a thumbnail of the given size.
     *
     * @param string $file_path
     * @param string $size
     */
    public function createThumbnail($file_path, $size)
    {
        $file_parts = pathinfo($file_path);
        $thumb_dir = $this->request->server('DOCUMENT_ROOT') . '/' . $file_parts['dirname'] . '/thumbnails';
        $thumb_name = $file_parts['filename'] . '_' . $size . '.' . $file_parts['extension'];

        CommonHelper::createDirectory($thumb_dir);

        if (!file_exists($thumb_dir . '/' . $thumb_name)) {
            $this->doResizeImage(
                $this->request->server('DOCUMENT_ROOT') . '/' . $file_parts['dirname'] . '/' . $file_parts['basename'],
                $thumb_dir,
                $thumb_name,
                $size
            )
            ;
        }
    }

    /**
     * Collect needed data to render a photo in masonry style.
     *
     * @param QueryEntries $record
     * @param string       $thumb_size
     *
     * @return array
     */
    public function dataPhotoMasonry($record, $thumb_size)
    {
        $data = [];
        $user_info = get_userdata($record->Member_ID);
        $title = $record->Title;
        $last_name = $user_info->user_lastname;
        $first_name = $user_info->user_firstname;
        $data['url_large'] = $this->getThumbnailUrl($record->Server_File_Name, '800');
        $data['url_thumb'] = $this->getThumbnailUrl($record->Server_File_Name, $thumb_size);
        $data['dimensions'] = $this->getThumbnailImageSize($record->Server_File_Name, $thumb_size);
        $data['title'] = $title . ' by ' . $first_name . ' ' . $last_name;
        $data['caption'] = $this->dataPhotoCredit($title, $first_name, $last_name);

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
    public function dataPhotoCredit($title, $first_name, $last_name)
    {
        $data = [];
        $data['title'] = $title;
        $data['credit'] = "$first_name $last_name";

        return $data;
    }

    /**
     * Delete the files from server.
     *
     * @param QueryEntries $entry
     */
    public function deleteEntryFromDisk($entry)
    {
        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);

        // Remove main file from disk
        if (is_file($this->request->server('DOCUMENT_ROOT') . $entry->Server_File_Name)) {
            unlink($this->request->server('DOCUMENT_ROOT') . $entry->Server_File_Name);
        }

        // Remove thumbnails
        $competition_record = $query_competitions->getCompetitionById($entry->Competition_ID);
        $competition_path = $this->request->server('DOCUMENT_ROOT') . $this->getCompetitionPath(
                $competition_record->Competition_Date,
                $competition_record->Classification,
                $competition_record->Medium
            )
        ;
        $file_parts = pathinfo($entry->Server_File_Name);
        $thumbnail_path = $competition_path . "/thumbnails";

        if (is_dir($thumbnail_path)) {
            $thumb_base_name = $thumbnail_path . '/' . $file_parts['filename'];
            // Get all the matching thumbnail files
            $thumbnails = glob("$thumb_base_name*");
            // Iterate through the list of matching thumbnails and delete each one
            if (is_array($thumbnails) && count($thumbnails) > 0) {
                foreach ($thumbnails as $thumb) {
                    unlink($thumb);
                }
            }
        }
    }

    /**
     * Resize an image.
     * Resize a given image to the given size
     *
     * @param string $image_name
     * @param string $thumb_path
     * @param string $thumb_name
     * @param string $size
     *
     * @return boolean
     */
    public function doResizeImage($image_name, $thumb_path, $thumb_name, $size)
    {
        // Open the original image
        if (!file_exists($image_name)) {
            return false;
        }
        if (file_exists($thumb_path . '/' . $thumb_name)) {
            return true;
        }

        $imagine = new Imagine();
        $image = $imagine->open($image_name);

        $new_size = Constants::getImageSize($size);

        if ($new_size['height'] == null) {
            $box = $image->getSize()->widen($new_size['width']);
            $image->resize($box);
        } else {
            $box = new Box($new_size['width'], $new_size['height']);
            $image->keepAspectRatio()->resize($box);
            ;
        }
        $image->save($thumb_path . '/' . $thumb_name, array('jpeg_quality' =>Constants::IMAGE_QUALITY));

        return true;
    }

    /**
     * Collect needed data to render a photo in masonry style.
     *
     * @param QueryEntries $record
     * @param string       $thumb_size
     *
     * @return array<string,string|array>
     */
    public function dataPhotoGallery($record, $thumb_size)
    {

        $data = [];
        $user_info = get_userdata($record->Member_ID);
        $title = $record->Title;
        $last_name = $user_info->user_lastname;
        $first_name = $user_info->user_firstname;
        $data['award'] = $record->Award;
        $data['url_large'] = $this->getThumbnailUrl($record->Server_File_Name, '800');
        $data['url_thumb'] = $this->getThumbnailUrl($record->Server_File_Name, $thumb_size);
        $data['dimensions'] = $this->getThumbnailImageSize($record->Server_File_Name, $thumb_size);
        $data['title'] = $title . ' by ' . $first_name . ' ' . $last_name;
        $data['caption'] = $this->dataPhotoCredit($title, $first_name, $last_name);

        return $data;
    }

    /**
     * Get the path to the competition
     * Returns the path to the competition where we store the photo entries.
     *
     * @param string $competition_date
     * @param string $classification
     * @param string $medium
     *
     * @return string
     */
    public function getCompetitionPath($competition_date, $classification, $medium)
    {
        $date = new \DateTime($competition_date);

        return '/Digital_Competitions/' . $date->format('Y-m-d') . '_' . $classification . '_' . $medium;
    }

    /**
     * Get the image size of the given thumbnail file
     *
     * @param string $file_path
     * @param string $size
     *
     * @return array
     */
    public function getThumbnailImageSize($file_path, $size)
    {

        $file_parts = pathinfo($file_path);
        $thumb_dir = $this->request->server('DOCUMENT_ROOT') . '/' . $file_parts['dirname'] . '/thumbnails';
        $thumb_name = $file_parts['filename'] . '_' . $size . '.' . $file_parts['extension'];

        if (!file_exists($thumb_dir . '/' . $thumb_name)) {
            $this->createThumbnail($file_path, $size);
        }
        $data = getimagesize($thumb_dir . '/' . $thumb_name);

        return ['width' => $data[0], 'height' => $data[1]];
    }

    /**
     * Get the full URL for the requested thumbnail
     *
     * @param string $file_path
     * @param string $size
     *
     * @return string
     */
    public function getThumbnailUrl($file_path, $size)
    {
        $this->createThumbnail($file_path, $size);
        $file_parts = pathinfo($file_path);
        $path_parts = explode('/', $file_parts['dirname']);
        $path = home_url();
        foreach ($path_parts as $part) {
            $path .= rawurlencode($part) . '/';
        }
        $path .= 'thumbnails/';

        $path .= rawurlencode($file_parts['filename']) . '_' . $size . '.' . $file_parts['extension'];

        return ($path);
    }

    /**
     * Remove the thumbnails of the given entry.
     *
     * @param string $path Path to original file. The thumbnails directory is located in this directory.
     * @param string $name
     */
    public function removeThumbnails($path, $name)
    {
        if (is_dir($path . "/thumbnails")) {
            $thumb_base_name = $path . "/thumbnails/" . $name;
            // Get all the matching thumbnail files
            $thumbnails = glob("$thumb_base_name*");
            // Iterate through the list of matching thumbnails and rename each one
            if (is_array($thumbnails) && count($thumbnails) > 0) {
                foreach ($thumbnails as $thumb) {
                    unlink($thumb);
                }
            }
        }
    }

    /**
     * Rename an already uploaded entry.
     * Besides renaming the original upload, we also rename all the thumbnails.
     *
     * @param string $path
     * @param string $old_name
     * @param string $new_name
     *
     * @return boolean
     */
    public function renameImageFile($path, $old_name, $new_name)
    {
        $path = $this->request->server('DOCUMENT_ROOT') . $path;
        // Rename the main image file
        $status = rename($path . '/' . $old_name, $path . '/' . $new_name);
        if ($status) {
            $parts = pathinfo($old_name);
            $name = $parts['filename'];
            // Rename any and all thumbnails of this file
            $this->removeThumbnails($path, $name);
        }

        return $status;
    }
}
