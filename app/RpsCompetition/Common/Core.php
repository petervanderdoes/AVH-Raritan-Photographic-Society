<?php
namespace RpsCompetition\Common;

use RpsCompetition\Settings;
use Illuminate\Http\Request;
use Intervention\Image\Image;
use RpsCompetition\Constants;

class Core
{

    private $db_version;

    /**
     * Comments used in HTML do identify the plugin
     *
     * @var string
     */
    private $comment;

    /**
     * Options set for the plugin
     *
     * @var array
     */
    /**
     * Properties used for the plugin options
     */
    private $db_options;

    private $default_options;

    /**
     *
     * @var Settings
     */
    private $settings;

    /**
     *
     * @var Request
     */
    private $request;

    /**
     * PHP5 constructor
     */
    public function __construct(Settings $settings, Request $request)
    {
        $this->settings = $settings;
        $this->request = $request;

        $this->db_options = 'avhrps_options';
        $this->db_version = 0;
        /**
         * Default options - General Purpose
         */
        $this->default_options = array();
        // add_action('init', array($this,'handleInitializePlugin'),10);
        $this->handleInitializePlugin();

        return;
    }

    public function handleInitializePlugin()
    {
        $old_db_version = get_option('avhrps_db_version', 0);

        $this->settings->club_name = "Raritan Photographic Society";
        $this->settings->club_short_name = "RPS";
        $this->settings->club_max_entries_per_member_per_date = 4;
        $this->settings->club_max_banquet_entries_per_member = 5;
        $this->settings->club_season_start_month_num = 9;
        $this->settings->club_season_end_month_num = 12;
        $this->settings->digital_chair_email = 'digitalchair@raritanphoto.com';

        $this->settings->siteurl = get_option('siteurl');
        $this->settings->graphics_url = plugins_url('images', $this->settings->plugin_basename);
        $this->settings->js_url = plugins_url('js', $this->settings->plugin_basename);
        $this->settings->css_url = plugins_url('css', $this->settings->plugin_basename);
        $this->settings->validComp = '';
        $this->settings->comp_date = '';
        $this->settings->classification = '';
        $this->settings->medium = '';
        $this->settings->max_width_entry = 1024;
        $this->settings->max_height_entry = 768;
    }

    public function rpsResizeImage($image_name, $thumb_name, $size, $quality, $maker)
    {
        $maker = trim($maker);

        // Open the original image
        if (!file_exists($image_name)) {
            return false;
        }
        if (file_exists($thumb_name)) {
            return true;
        }
        $image=Image::make($image_name);
        $new_size= Constants::get_image_size($size);
        $image->resize($new_size['width'], $new_size['height'], true, false);
        $image->save($thumb_name,$quality);
        return true;
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
            $this->rpsResizeImage($this->request->server('DOCUMENT_ROOT') . '/' . $file_parts['dirname'] . '/' . $file_parts['filename'] . '.jpg', $thumb_dir . '/' . $thumb_name, $size, 80, "");
        }

        $p = explode('/', $file_parts['dirname']);
        $path = home_url();
        foreach ($p as $part) {
            $path .= rawurlencode($part) . '/';
        }
        $path .= 'thumbnails/';

        return ($path . rawurlencode($file_parts['filename'] . '_' . $size . '.jpg'));
    }

    public function renameImageFile($path, $old_name, $new_name, $ext)
    {
        $thumbnails = array();
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

    public function arrayMsort($array, $cols)
    {
        $colarr = array();
        foreach ($cols as $col => $order) {
            $colarr[$col] = array();
            foreach ($array as $k => $row) {
                $colarr[$col][$k] = strtolower($row[$col]);
            }
        }
        $params = array();
        foreach ($cols as $col => $order) {
            $params[] = & $colarr[$col];
            foreach ($order as $order_element) {
                // pass by reference, as required by php 5.3
                $params[] = &$order_element;
                unset($order_element);
            }
        }
        $params[] = &$array;
        call_user_func_array('array_multisort', $params);

        return $array;
    }

    public function getShorthandToBytes($size_str)
    {
        switch (substr($size_str, -1)) {
            case 'M':
            case 'm':
                return (int) $size_str * 1048576;
            case 'K':
            case 'k':
                return (int) $size_str * 1024;
            case 'G':
            case 'g':
                return (int) $size_str * 1073741824;
            default:
                return $size_str;
        }
    }

    /**
     * Check if the user is a paid member
     *
     * @param int $user_id
     *            UserID to check
     * @return boolean true if a paid member, false if non-existing user or non-paid member.`
     */
    public function isPaidMember($user_id = null)
    {
        if (is_numeric($user_id)) {
            $user = get_user_by('id', $user_id);
        } else {
            $user = wp_get_current_user();
        }

        if (empty($user)) {
            return false;
        }

        return in_array('s2member_level4', (array) $user->roles);
    }

    /**
     *
     * @return string
     */
    public function getComment($str = '')
    {
        return $this->comment . ' ' . trim($str) . ' -->';
    }

    /**
     *
     * @return the $_db_nonces
     */
    public function getDbNonces()
    {
        return $this->_db_nonces;
    }

    /**
     *
     * @return the $_default_nonces
     */
    public function getDefaultNonces()
    {
        return $this->_default_nonces;
    }
}
