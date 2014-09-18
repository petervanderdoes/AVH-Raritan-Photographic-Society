<?php
namespace RpsCompetition\Photo;

use Illuminate\Http\Request;
use Intervention\Image\ImageManagerStatic as Image;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Constants;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Settings;

class Helper
{
    private $request;
    private $rpsdb;
    private $settings;

    /**
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
     * Delete the files from server.
     *
     * @param QueryEntries $entry
     */
    public function deleteEntryFromDisk($entry)
    {
        $query_competitions = new QueryCompetitions($this->rpsdb);

        // Remove main file from disk
        if (is_file($this->request->server('DOCUMENT_ROOT') . $entry->Server_File_Name)) {
            unlink($this->request->server('DOCUMENT_ROOT') . $entry->Server_File_Name);
        }

        // Remove thumbnails
        $competition_record = $query_competitions->getCompetitionById($entry->Competition_ID);
        $competition_path = $this->request->server('DOCUMENT_ROOT') . $this->getCompetitionPath($competition_record->Competition_Date, $competition_record->Classification, $competition_record->Medium);
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
     * @param string $ext
     *
     * @return boolean
     */
    public function renameImageFile($path, $old_name, $new_name, $ext)
    {
        $path = $this->request->server('DOCUMENT_ROOT') . $path;
        // Rename the main image file
        $status = rename($path . '/' . $old_name . $ext, $path . '/' . $new_name . $ext);
        if ($status) {
            // Rename any and all thumbnails of this file
            $this->removeThumbnails($path, $old_name);
        }

        return $status;
    }

    /**
     * Get the full URL for the requested thumbnail
     *
     * @param QueryEntries $entry
     * @param string       $size
     *
     * @return string
     */
    public function rpsGetThumbnailUrl($entry, $size)
    {
        $file_parts = pathinfo($entry->Server_File_Name);
        $thumb_dir = $this->request->server('DOCUMENT_ROOT') . '/' . $file_parts['dirname'] . '/thumbnails';
        $thumb_name = $file_parts['filename'] . '_' . $size . '.jpg';

        CommonHelper::createDirectory($thumb_dir);

        if (!file_exists($thumb_dir . '/' . $thumb_name)) {
            $this->rpsResizeImage($this->request->server('DOCUMENT_ROOT') . '/' . $file_parts['dirname'] . '/' . $file_parts['basename'], $thumb_dir, $thumb_name, $size);
        }

        $path_parts = explode('/', $file_parts['dirname']);
        $path = home_url();
        foreach ($path_parts as $part) {
            $path .= rawurlencode($part) . '/';
        }
        $path .= 'thumbnails/';

        return ($path . rawurlencode($file_parts['filename'] . '_' . $size . '.jpg'));
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
    public function rpsResizeImage($image_name, $thumb_path, $thumb_name, $size)
    {
        // Open the original image
        if (!file_exists($image_name)) {
            return false;
        }
        if (file_exists($thumb_path . '/' . $thumb_name)) {
            return true;
        }
        /** @var \Intervention\Image\Image $image */
        $image = Image::make($image_name);
        $new_size = Constants::getImageSize($size);
        if ($new_size['height'] == null) {
            if ($image->getHeight() <= $image->getWidth()) {
                $image->resize($new_size['width'],
                               $new_size['width'],
                    function ($constraint) {
                        $constraint->aspectRatio();
                    });
            } else {
                $image->resize($new_size['width'],
                               null,
                    function ($constraint) {
                        $constraint->aspectRatio();
                    });
            }
        } else {
            $image->resize($new_size['width'],
                           $new_size['height'],
                function ($constraint) {
                    $constraint->aspectRatio();
                });
        }
        $image->save($thumb_path . '/' . $thumb_name, Constants::IMAGE_QUALITY);

        return true;
    }
}
