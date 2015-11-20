<?php

namespace RpsCompetition\Helpers;

use RpsCompetition\Constants;

/**
 * Class ImageSizeHelper
 *
 * @package   RpsCompetition\Helpers
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2015, AVH Software
 */
final class ImageSizeHelper
{
    /**
     * @var array image_sizes
     * Holds the list of image size. If the height is null the image will resize to the width size and don't care about
     * the height. Usefull for Masonary Layout.
     */
    private static $image_sizes = [
        '75'       => ['width' => 75, 'height' => 75],
        '150'      => ['width' => 150, 'height' => 150],
        '150w'     => ['width' => 150, 'height' => null],
        '200'      => ['width' => 200, 'height' => 200],
        '250'      => ['width' => 250, 'height' => 250],
        '800'      => ['width' => 800, 'height' => 800],
        '1024'     => ['width' => 1024, 'height' => 768],
        '1400'     => ['width' => 1400, 'height' => 1050],
        'fb_thumb' => ['width' => 1200, 'height' => 628],
        'FULL'     => ['width' => Constants::IMAGE_MAX_WIDTH_ENTRY, 'height' => Constants::IMAGE_MAX_HEIGHT_ENTRY],
    ];

    /**
     * Check if the given size is a valid image size
     *
     * @param string $size
     *
     * @return bool
     */
    public static function isImageSize($size)
    {
        return array_key_exists($size, self::$image_sizes);
    }

    /**
     * Returns the width and height for the given image size.
     *
     * @param string $size
     *
     * @return array
     */
    public static function getImageSize($size)
    {
        if (self::isImageSize((string) $size)) {
            return self::$image_sizes[(string) $size];
        }

        return null;
    }
}
