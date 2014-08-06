<?php


namespace RpsCompetition\Common;

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
        $sort_column_array = array();

        // Create multiple arrays using the array $cols. These arrays hold the values of each field that we want to sort on.
        foreach ($cols as $col => $order) {
            $sort_column_array[$col] = array();
            foreach ($array as $key => $row) {
                if (is_object($row)) {
                    $row = (array) $row;
                    $row_is_object = true;
                }
                $sort_column_array[$col][$key] = strtolower($row[$col]);
            }
        }

        $params = array();
        foreach ($cols as $col => $order) {
            $params[] = & $sort_column_array[$col];
            foreach ($order as $order_element) {
                // pass by reference, as required by php 5.3
                $params[] = & $order_element;
                unset($order_element);
            }
        }

        $params[] = & $array;
        call_user_func_array('array_multisort', $params);
        if ($row_is_object) {
            foreach ($array as $key => $row) {
                $array[$key] = (object) $row;
            }
        }

        return $array;
    }

    /**
     * Check if the user is a paid member
     *
     * @param int $user_id UserID to check
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
     * Create a directory if it does not exist.
     *
     * @param string $path
     */
    static public function createDirectory($path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0755);
        }
    }

} 