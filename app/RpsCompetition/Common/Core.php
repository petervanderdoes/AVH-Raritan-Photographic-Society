<?php
namespace RpsCompetition\Common;

use RpsCompetition\Settings;
use \Illuminate\Http\Request;

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
     * @var \Illuminate\Http\Request;
     */
    private $request;

    /**
     * PHP5 constructor
     */
    public function __construct(\RpsCompetition\Settings $settings, Request $request)
    {
        $this->settings = $settings;
        $this->request = $request;

        //dd($request);
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
        // Database credentials
        $this->settings->host = 'localhost';
        $this->settings->dbname = 'avirtu2_raritdata';
        $this->settings->uname = 'avirtu2_rarit1';
        $this->settings->pw = '1Hallo@Done#';
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

    /**
     * Setup DB Tables
     *
     * @return unknown_type
     */
    // private function _setTables()
    // {
    // global $wpdb;
    // // add DB pointer
    // $wpdb->avhfdasipcache = $wpdb->prefix . 'avhfdas_ipcache';
    // }
    public function rpsCreateThumbnail($row, $size, $show_maker = true)
    {
        if ($size >= 400 && $show_maker) {
            $maker = $row['FirstName'] . " " . $row['LastName'];
        }
        $dateParts = explode(" ", $row['Competition_Date']);
        $path = $_SERVER['DOCUMENT_ROOT'] . '/Digital_Competitions/' . $dateParts[0] . '_' . $row['Classification'] . '_' . $row['Medium'];
        $file_name = $row['Title'] . '+' . $row['Username'];

        if (!is_dir("$path/thumbnails")) {
            mkdir("$path/thumbnails", 0755);
        }

        if (!file_exists("$path/thumbnails/$file_name" . "_$size.jpg")) {
            $name = $_SERVER['DOCUMENT_ROOT'] . str_replace('/home/rarit0/public_html', '', $row['Server_File_Name']);
            $this->rpsResizeImage($name, "$path/thumbnails/$file_name" . "_$size.jpg", $size, 75, $maker);
        }
    }

    public function rpsResizeImage($image_name, $thumb_name, $size, $quality, $maker)
    {
        $maker = trim($maker);

        // Open the original image
        if (!file_exists($image_name)) {
            return false;
        }
        $original_img = imagecreatefromjpeg($image_name);
        // Calculate the height and width of the resized image
        $dimensions = GetImageSize($image_name);
        if (!(false === $dimensions)) {
            $w = $dimensions[0];
            $h = $dimensions[1];
            if ($w > $h) { // Landscape image
                $nw = $size;
                $nh = $h * $size / $w;
            } else { // Portrait image
                $nh = $size;
                $nw = $w * $size / $h;
            }
        } else {
            // set new size
            $nw = "0";
            $nh = "0";
        }
        // Downsize the original image
        $thumb_img = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($thumb_img, $original_img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        // If this is the 400px image, write the copyright notice onto the image
        if (!(empty($maker))) {
            $dateParts = explode("-", $this->settings->comp_date);
            $year = $dateParts[0];
            $black = imagecolorallocate($thumb_img, 0, 0, 0);
            $white = imagecolorallocate($thumb_img, 255, 255, 255);
            $font = 5;
            $text = "Copyright " . substr($this->settings->comp_date, 0, 4) . " $maker";
            $width = imagesx($thumb_img);
            $height = imagesy($thumb_img);
            $textLength = imagefontwidth($font) * strlen($text);
            $textHeight = imagefontwidth($font);

            // imagestring($img, $font, 5, $height/2, $text, $red);
            imagestring($thumb_img, $font, 7, $height - ($textHeight * 2), $text, $black);
            imagestring($thumb_img, $font, 5, $height - ($textHeight * 2) - 2, $text, $white);
        }
        // Write the downsized image back to disk
        imagejpeg($thumb_img, $thumb_name, $quality);

        // Free up memory
        imagedestroy($thumb_img);

        return true;
    }

    public function rpsGetThumbnailUrl($row, $size)
    {
        $file_parts = pathinfo(str_replace('/home/rarit0/public_html', '', $row['Server_File_Name']));
        $thumb_dir = $_SERVER['DOCUMENT_ROOT'] . '/' . $file_parts['dirname'] . '/thumbnails';

        if (!is_dir($thumb_dir)) {
            mkdir($thumb_dir, 0755);
        }

        if (!file_exists($thumb_dir . '/' . $file_parts['filename'] . '_' . $size . '.jpg')) {
            $this->rpsResizeImage($_SERVER['DOCUMENT_ROOT'] . '/' . $file_parts['dirname'] . '/' . $file_parts['filename'] . '.jpg', $thumb_dir . '/' . $file_parts['filename'] . '_' . $size . '.jpg', $size, 80, "");
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
        $path = $_SERVER['DOCUMENT_ROOT'] . $path;
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
