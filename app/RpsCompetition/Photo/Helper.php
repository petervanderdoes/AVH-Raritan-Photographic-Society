<?php
namespace RpsCompetition\Photo;

use Illuminate\Http\Request;
use Intervention\Image\ImageManagerStatic as Image;
use RpsCompetition\Constants;
use RpsCompetition\Settings;

class Helper
{
    private $request;
    private $settings;

    /**
     * @param Settings $settings
     * @param Request  $request
     */
    public function __construct(Settings $settings, Request $request)
    {
        $this->settings = $settings;
        $this->request = $request;
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
     * @return bool
     */
    public function renameImageFile($path, $old_name, $new_name, $ext)
    {
        $path = $this->request->server('DOCUMENT_ROOT') . $path;
        // Rename the main image file
        $status = rename($path . '/' . $old_name . $ext, $path . '/' . $new_name . $ext);
        if ($status) {
            // Rename any and all thumbnails of this file
            if (is_dir($path . "/thumbnails")) {
                $thumb_base_name = $path . "/thumbnails/" . $old_name;
                // Get all the matching thumbnail files
                $thumbnails = glob("$thumb_base_name*");
                // Iterate through the list of matching thumbnails and rename each one
                if (is_array($thumbnails) && count($thumbnails) > 0) {
                    foreach ($thumbnails as $thumb) {
                        $start = strlen($thumb_base_name);
                        $length = strpos($thumb, $ext) - $start;
                        $suffix = substr($thumb, $start, $length);
                        rename($thumb, $path . "/thumbnails/" . $new_name . $suffix . $ext);
                    }
                }
            }
        }

        return $status;
    }

    public function rpsGetThumbnailUrl($record, $size)
    {
        $file_parts = pathinfo($record->Server_File_Name);
        $thumb_dir = $this->request->server('DOCUMENT_ROOT') . '/' . $file_parts['dirname'] . '/thumbnails';
        $thumb_name = $file_parts['filename'] . '_' . $size . '.jpg';

        if (!is_dir($thumb_dir)) {
            mkdir($thumb_dir, 0755);
        }

        if (!file_exists($thumb_dir . '/' . $thumb_name)) {
            $this->rpsResizeImage($this->request->server('DOCUMENT_ROOT') . '/' . $file_parts['dirname'] . '/' . $file_parts['basename'], $thumb_dir . '/' . $thumb_name, $size);
        }

        $p = explode('/', $file_parts['dirname']);
        $path = home_url();
        foreach ($p as $part) {
            $path .= rawurlencode($part) . '/';
        }
        $path .= 'thumbnails/';

        return ($path . rawurlencode($file_parts['filename'] . '_' . $size . '.jpg'));
    }

    public function rpsResizeImage($image_name, $thumb_name, $size)
    {
        // Open the original image
        if (!file_exists($image_name)) {
            return false;
        }
        if (file_exists($thumb_name)) {
            return true;
        }
        /** @var Image $image */
        $image = Image::make($image_name);
        $new_size = Constants::get_image_size($size);
        if ($new_size['height'] == null) {
            if ($image->height <= $image->width) {
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
        $image->save($thumb_name, Constants::IMAGE_QUALITY);

        return true;
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
}
