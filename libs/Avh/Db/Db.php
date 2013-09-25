<?php

final class AVH_DB
{

    /**
     * Fetch MySQL Field Names
     *
     * @access public
     * @param string $table
     *        table name
     * @return array
     */
    public function getFieldNames ($table = '')
    {
        global $wpdb;

        $_retval = wp_cache_get('field_names_' . $table, 'avhec');
        if ( false === $_retval ) {
            $sql = $this->_getQueryShowColumns($table);

            $_result = $wpdb->get_results($sql, ARRAY_A);

            $_retval = array();
            foreach ( $_result as $row ) {
                if ( isset($row['Field']) ) {
                    $_retval[] = $row['Field'];
                }
            }
            wp_cache_set('field_names_' . $table, $_retval, 'avhec', 3600);
        }

        return $_retval;
    }

    /**
     * Determine if a particular field exists
     *
     * @access public
     * @param string $field_name
     * @param string $table_name
     * @return boolean
     */
    public function field_exists ($field_name, $table_name)
    {
        return ( in_array($field_name, $this->getFieldNames($table_name)) );
    }

    /**
     * Show column query
     *
     * Generates a platform-specific query string so that the column names can be fetched
     *
     * @access public
     * @param string $table
     *        The table name
     * @return string
     */
    private function _getQueryShowColumns ($table = '')
    {
        global $wpdb;
        return $wpdb->prepare('SHOW COLUMNS FROM ' . $table);
    }
}