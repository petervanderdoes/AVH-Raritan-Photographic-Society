<?php
namespace RpsCompetition\Common;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class Helper
 *
 * @package RpsCompetition\Common
 */
class Helper
{
    /**
     * Sort an array on multiple columns
     *
     * @param array $array
     * @param array $cols
     *
     * @return array
     */
    static public function arrayMsort($array, $cols)
    {
        $row_is_object = false;
        $sort_column_array = [];

        // Create multiple arrays using the array $cols. These arrays hold the values of each field that we want to sort on.
        foreach ($cols as $col => $order) {
            $sort_column_array[$col] = [];
            foreach ($array as $key => $row) {
                if (is_object($row)) {
                    $row = (array) $row;
                    $row_is_object = true;
                }
                $sort_column_array[$col][$key] = strtolower($row[$col]);
            }
        }

        $params = [];
        foreach ($cols as $col => $order) {
            $params[] = &$sort_column_array[$col];
            foreach ($order as $order_element) {
                // pass by reference, as required by php 5.3
                $params[] = &$order_element;
                unset($order_element);
            }
        }

        $params[] = &$array;
        call_user_func_array('array_multisort', $params);
        if ($row_is_object) {
            foreach ($array as $key => $row) {
                $array[$key] = (object) $row;
            }
        }

        return $array;
    }

    /**
     * Create a directory if it does not exist.
     *
     * @param string $path
     */
    static public function createDirectory($path)
    {
        if (!file_exists($path)) { // Create the directory if it is missing
            wp_mkdir_p($path);
        }
    }

    /**
     * Get the Dynamic Pages.
     *
     * These are the pages where we implement javascript to get different competitions and seasons within the page.
     *
     * @return array
     */
    static public function getDynamicPages()
    {
        $options = get_option('avh-rps');
        $pages_array = [$options['monthly_entries_post_id'] => true, $options['monthly_winners_post_id'] => true];

        return $pages_array;
    }

    /**
     * Improve the default WordPress plugins_url.
     * The standard function requires a file at the end of the 2nd parameter.
     *
     * @param string $file
     * @param string $directory
     *
     * @return string
     */
    static public function getPluginUrl($file, $directory)
    {
        if (is_dir($directory)) {
            $directory .= '/foo';
        }

        return plugins_url($file, $directory);
    }

    /**
     * Get the user classification based on the medium
     *
     * @param integer $userID
     * @param string  $medium
     *
     * @return string
     */
    static public function getUserClassification($userID, $medium)
    {
        switch ($medium) {
            case 'B&W Digital':
                $index = get_user_meta($userID, 'rps_class_bw', true);
                break;
            case 'Color Digital':
                $index = get_user_meta($userID, 'rps_class_color', true);
                break;
            case 'B&W Prints':
                $index = get_user_meta($userID, 'rps_class_print_bw', true);
                break;
            case 'Color Prints':
                $index = get_user_meta($userID, 'rps_class_print_color', true);
                break;
            default:
                $index = '';
        }

        return ucfirst($index);
    }

    /**
     * Check if the user is a paid member
     *
     * @param integer|null $user_id UserID to check
     *
     * @return boolean true if a paid member, false if non-existing user or non-paid member.`
     */
    static public function isPaidMember($user_id = null)
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
     * Check if the given date in the given format is valid.
     *
     * @param  string $date
     * @param string  $format
     *
     * @return bool
     */
    static public function isValidDate($date, $format = 'Y-m-d H:i:s')
    {
        $d = \DateTime::createFromFormat($format, $date);

        return $d && $d->format($format) == $date;
    }
}
