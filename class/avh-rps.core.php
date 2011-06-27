<?php
if ( !defined( 'AVH_FRAMEWORK' ) ) die( 'You are not allowed to call this page directly.' );
class AVH_RPS_Core
{
    /**
     * Version of AVH First Defense Against Spam
     *
     * @var string
     */
    private $_version;
    private $_db_version;
    /**
     * Comments used in HTML do identify the plugin
     *
     * @var string
     */
    private $_comment;
    /**
     * Paths and URI's of the WordPress information, 'home', 'siteurl', 'install_url', 'install_dir'
     *
     * @var array
     */
    var $info;
    /**
     * Options set for the plugin
     *
     * @var array
     */
    /**
     * Properties used for the plugin options
     *
     */
    private $_db_options;
    private $_default_options;
    private $_default_options_general;
    private $_options;
    /**
     * Properties used for the plugin data
     */
    private $_db_data;
    private $_default_data;
    private $_data;
    /**
     *
     * @var AVH_RPS_Settings
     */
    private $_settings;

    /**
     * PHP5 constructor
     *
     */
    public function __construct()
    {
        $this->_settings = AVH_RPS_Settings::getInstance();
        $this->_db_options = 'avhrps_options';
        $this->_db_version = 0;
        /**
         * Default options - General Purpose
         */
        $this->_default_options = array();
        //add_action('init', array(&$this,'handleInitializePlugin'),10);
        $this->handleInitializePlugin();
        
        return;
    }

    function handleInitializePlugin()
    {
        /**
         * Set the options for the program
         *
         */
        $this->_loadOptions();
        //$this->_loadData();
        //$this->_setTables();
        // Check if we have to do upgrades
        $old_db_version = get_option( 'avhrps_db_version', 0 );
        if ( $old_db_version < $this->_db_version ) {
            $this->_doUpgrade( $old_db_version );
            update_option( avhrps_db_version, $this->_db_version );
        }
        
        $this->_settings->storeSetting( 'club_name', "Raritan Photographic Society" );
        $this->_settings->storeSetting( 'club_short_name', "RPS" );
        $this->_settings->storeSetting( 'club_max_entries_per_member_per_date', 4 );
        $this->_settings->storeSetting( 'club_max_banquet_entries_per_member', 5 );
        $this->_settings->storeSetting( 'club_season_start_month_num', 9 );
        $this->_settings->storeSetting( 'club_season_end_month_num', 12 );
        // Database credentials
        $this->_settings->storeSetting( 'host', 'localhost' );
        $this->_settings->storeSetting( 'dbname', 'rarit0_data' );
        $this->_settings->storeSetting( 'uname', 'rarit0_data' );
        $this->_settings->storeSetting( 'pw', 'rps' );
        $this->_settings->storeSetting( 'digital_chair_email', 'digitalchair@raritanphoto.com' );
        
        $this->_settings->storeSetting( 'siteurl', get_option( 'siteurl' ) );
        $this->_settings->storeSetting( 'graphics_url', plugins_url( 'images', $this->_settings->plugin_basename ) );
        $this->_settings->storeSetting( 'js_url', plugins_url( 'js', $this->_settings->plugin_basename ) );
        $this->_settings->storeSetting( 'css_url', plugins_url( 'css', $this->_settings->plugin_basename ) );
        $this->_settings->storeSetting( 'validComp', '' );
        $this->_settings->storeSetting( 'comp_date', '' );
        $this->_settings->storeSetting( 'classification', '' );
        $this->_settings->storeSetting( 'medium', '' );
    }

    /**
     * Setup DB Tables
     * @return unknown_type
     */
    //    private function _setTables()
    //    {
    //        global $wpdb;
    //        // add DB pointer
    //        $wpdb->avhfdasipcache = $wpdb->prefix . 'avhfdas_ipcache';
    //    }
    

    /**
     * Checks if running version is newer and do upgrades if necessary
     *
     */
    private function _doUpgrade( $old_db_version )
    {
        $options = $this->getOptions();
        // Introduced dbversion starting with v2.1
        //if (! isset($options['general']['dbversion']) || $options['general']['dbversion'] < 4) {
        //	list ($options, $data) = $this->_doUpgrade21($options, $data);
        //}
        // Add none existing sections and/or elements to the options
        foreach ( $this->_default_options as $option => $value ) {
            if ( !array_key_exists( $option, $options ) ) {
                $options[$option] = $value;
                continue;
            }
        }
        $this->saveOptions( $options );
    }

    function rpsCreateThumbnail( $row, $size )
    {
        if ( $size >= 400 ) {
            $maker = $row['FirstName'] . " " . $row['LastName'];
        }
        $dateParts = explode( " ", $row['Competition_Date'] );
        $path = $_SERVER['DOCUMENT_ROOT'] . '/Digital_Competitions/' . $dateParts[0] . '_' . $row['Classification'] . '_' . $row['Medium'];
        $file_name = $row['Title'] . '+' . $row['Username'];
        
        if ( !is_dir( "$path/thumbnails" ) ) mkdir( "$path/thumbnails", 0755 );
        
        if ( !file_exists( "$path/thumbnails/$file_name" . "_$size.jpg" ) ) {
            $this->rpsResizeImage( $row['Server_File_Name'], "$path/thumbnails/$file_name" . "_$size.jpg", $size, 75, $maker );
        }
    
    }

    function rpsResizeImage( $image_name, $thumb_name, $size, $quality, $maker )
    {
        GLOBAL $comp_date;
        
        // Open the original image
        if ( !file_exists( $image_name ) ) {
            return;
        }
        $original_img = imagecreatefromjpeg( $image_name );
        // Calculate the height and width of the resized image
        if ( $dimensions = GetImageSize( $image_name ) ) {
            $w = $dimensions[0];
            $h = $dimensions[1];
            if ( $w > $h ) { // Landscape image
                $nw = $size;
                $nh = $h * $size / $w;
            } else { // Portrait image
                $nh = $size;
                $nw = $w * $size / $h;
            }
        } else {
            //set new size
            $nw = "0";
            $nh = "0";
        }
        // Downsize the original image
        $thumb_img = imagecreatetruecolor( $nw, $nh );
        imagecopyresampled( $thumb_img, $original_img, 0, 0, 0, 0, $nw, $nh, $w, $h );
        
        // If this is the 400px image, write the copyright notice onto the image
        if ( $maker > "" ) {
            $dateParts = split( "-", $comp_date );
            $year = $dateParts[0];
            $black = imagecolorallocate( $thumb_img, 0, 0, 0 );
            $white = imagecolorallocate( $thumb_img, 255, 255, 255 );
            $font = 5;
            $text = "Copyright " . substr( $comp_date, 0, 4 ) . " $maker";
            $width = imagesx( $thumb_img );
            $height = imagesy( $thumb_img );
            $textLength = imagefontwidth( $font ) * strlen( $text );
            $textHeight = imagefontwidth( $font );
            
            //imagestring($img, $font, 5, $height/2, $text, $red);
            imagestring( $thumb_img, $font, 7, $height - ( $textHeight * 2 ), $text, $black );
            imagestring( $thumb_img, $font, 5, $height - ( $textHeight * 2 ) - 2, $text, $white );
        }
        // Write the downsized image back to disk
        imagejpeg( $thumb_img, $thumb_name, $quality );
    }

    function rpsGetThumbnailUrl( $row, $size )
    {
        
        $file_parts = pathinfo( str_replace( '/home/rarit0/public_html/', '', $row['Server_File_Name'] ) );
        $thumb_dir = ABSPATH . '/' . $file_parts['dirname'] . '/thumbnails';
        if ( !is_dir( $thumb_dir ) ) mkdir( $thumb_dir, 0755 );
        
        if ( !file_exists( $thumb_dir . '/' . $file_parts['filename'] . '_' . $size . '.jpg' ) ) {
            $this->rpsResizeImage( ABSPATH . '/' . $file_parts['dirname'] . '/' . $file_parts['filename'] . '.jpg', $thumb_dir . '/' . $file_parts['filename'] . '_' . $size . '.jpg', $size, 80, "" );
        }
        
        $p = explode( '/', $file_parts['dirname'] );
        $path = site_url() . '/';
        foreach ( $p as $part ) {
            $path .= rawurlencode( $part ) . '/';
        }
        $path .= 'thumbnails/';
        
        return ( $path . rawurlencode( $file_parts['filename'] . '_' . $size . '.jpg' ) );
    }

    public function rps_rename_image_file( $path, $old_name, $new_name, $ext )
    {
        $thumbnails = array();
        $path = ABSPATH . $path;
        // Rename the main image file
        $status = rename( $path . '/' . $old_name . $ext, $path . '/' . $new_name . $ext );
        if ( $status ) {
            // Rename any and all thumbnails of this file
            if ( is_dir( $path . "/thumbnails" ) ) {
                $thumb_base_name = $path . "/thumbnails/" . $old_name;
                // Get all the matching thumbnail files
                $thumbnails = glob( "$thumb_base_name*" );
                // Iterate through the list of matching thumbnails and rename each one
                if ( is_array( $thumbnails ) && count( $thumbnails ) > 0 ) {
                    foreach ( $thumbnails as $thumb ) {
                        $start = strlen( $thumb_base_name );
                        $length = strpos( $thumb, $ext ) - $start;
                        $suffix = substr( $thumb, $start, $length );
                        rename( $thumb, $path . "/thumbnails/" . $new_name . $suffix . $ext );
                    }
                }
            }
        }
        return $status;
    }

    public function avh_array_msort( $array, $cols )
    {
        $colarr = array();
        foreach ( $cols as $col => $order ) {
            $colarr[$col] = array();
            foreach ( $array as $k => $row ) {
                $colarr[$col]['_' . $k] = strtolower( $row[$col] );
            }
        }
        $params = array();
        foreach ( $cols as $col => $order ) {
            $params[] = & $colarr[$col];
            foreach ( $order as $order_element ) {
                //pass by reference, as required by php 5.3
                $params[] = &$order_element;
                unset( $order_element );
            }
        }
        call_user_func_array( 'array_multisort', $params );
        $ret = array();
        $keys = array();
        $first = true;
        foreach ( $colarr as $col => $arr ) {
            foreach ( $arr as $k => $v ) {
                if ( $first ) {
                    $keys[$k] = substr( $k, 1 );
                }
                $k = $keys[$k];
                if ( !isset( $ret[$k] ) ) $ret[$k] = $array[$k];
                $ret[$k][$col] = $array[$k][$col];
            }
            $first = false;
        }
        return $ret;
    
    }

    /*********************************
     * *
     * Methods for variable: options *
     * *
     ********************************/
    /**
     * @param array $data
     */
    private function _setOptions( $options )
    {
        $this->_options = $options;
    }

    /**
     * return array
     */
    public function getOptions()
    {
        return ( $this->_options );
    }

    /**
     * Save all current options and set the options
     *
     */
    public function saveOptions( $options )
    {
        update_option( $this->_db_options, $options );
        wp_cache_flush(); // Delete cache
        $this->_setOptions( $options );
    }

    /**
     * Retrieves the plugin options from the WordPress options table and assigns to class variable.
     * If the options do not exists, like a new installation, the options are set to the default value.
     *
     * @return none
     */
    private function _loadOptions()
    {
        
        $options = get_option( $this->_db_options );
        if ( false === $options ) { // New installation
            add_option( $this->_db_options, $this->_default_options, '', 'yes' );
            $options = $this->_default_options;
        }
        $this->_setOptions( $options );
    }

    /**
     * Get the value for an option element.
     *
     * @param string $option
     * @return mixed
     */
    public function getOption( $option )
    {
        if ( !$option ) return false;
        
        if ( !isset( $this->_options ) ) $this->_loadOptions();
        
        if ( !is_array( $this->_options ) || empty( $this->_options[$option] ) ) return false;
        
        return $this->_options[$option];
    }

    /**
     * Reset to default options and save in DB
     *
     */
    private function _resetToDefaultOptions()
    {

    }

    /******************************
     * *
     * Methods for variable: data *
     * *
     *****************************/
    /**
     * @param array $data
     */
    private function _setData( $data )
    {
        $this->_data = $data;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return ( $this->_data );
    }

    /**
     * Save all current data to the DB
     * @param array $data
     *
     */
    public function saveData( $data )
    {
        update_option( $this->_db_data, $data );
        wp_cache_flush(); // Delete cache
        $this->_setData( $data );
    }

    /**
     * Retrieve the data from the DB
     *
     * @return array
     */
    private function _loadData()
    {
        $data = get_option( $this->_db_data );
        if ( false === $data ) { // New installation
            $this->_resetToDefaultData();
        } else {
            $this->_setData( $data );
        }
        return;
    }

    /**
     * Get the value of a data element. If there is no value return false
     *
     * @param string $option
     * @param string $key
     * @return mixed
     * @since 0.1
     */
    public function getDataElement( $option, $key )
    {
        if ( $this->_data[$option][$key] ) {
            $return = $this->_data[$option][$key];
        } else {
            $return = false;
        }
        return ( $return );
    }

    /**
     * Reset to default data and save in DB
     *
     */
    private function _resetToDefaultData()
    {
        $this->_data = $this->_default_data;
        $this->saveData( $this->_default_data );
    }

    /**
     * @return string
     */
    public function getComment( $str = '' )
    {
        return $this->_comment . ' ' . trim( $str ) . ' -->';
    }

    /**
     * @return the $_db_nonces
     */
    public function getDbNonces()
    {
        return $this->_db_nonces;
    }

    /**
     * @return the $_default_nonces
     */
    public function getDefaultNonces()
    {
        return $this->_default_nonces;
    }
} //End Class AVH_RPS_Core