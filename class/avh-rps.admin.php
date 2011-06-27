<?php
final class AVH_RPS_Admin
{
    /**
     * Message management
     *
     */
    private $_message = '';
    private $_status = '';
    /**
     *
     * @var AVH_RPS_Core
     */
    private $_core;
    /**
     * @var AVH_RPS_Settings
     */
    private $_settings;
    /**
     * @var AVH_RPS_Classes
     */
    private $_classes;
    /**
     * @var AVH_RPS_DB
     */
    private $_db;
    private $_add_disabled_notice = false;
    private $_hooks = array();
    /**
     * @var AVH_RPS_IpcacheList
     */
    private $_ip_cache_list;

    /**
     * PHP5 Constructor
     *
     * @return unknown_type
     */
    public function __construct()
    {
        // The Settings Registery
        $this->_settings = AVH_RPS_Settings::getInstance();
        
        // The Classes Registery
        $this->_classes = AVH_RPS_Classes::getInstance();
        
        // Loads the CORE class
        $this->_core = $this->_classes->load_class( 'Core', 'plugin', true );
        
        // Admin URL and Pagination
        $this->_core->admin_base_url = $this->_settings->siteurl . '/wp-admin/admin.php?page=';
        if ( isset( $_GET['pagination'] ) ) {
            $this->_core->actual_page = (int) $_GET['pagination'];
        }
        
        add_action( 'init', array( &$this, 'actionInit_Roles' ) );
        add_action( 'init', array( &$this, 'actionInit_UserFields' ) );
        
        // Admin menu
        add_action( 'admin_menu', array( &$this, 'actionAdminMenu' ) );
        
        return;
    }

    /**
     * Setup Roles
     *
     * @WordPress Action init
     */
    public function actionInit_Roles()
    {
        /* Get the administrator role. */
        $role = & get_role( 'administrator' );
        
        /* If the administrator role exists, add required capabilities for the plugin. */
        if ( !empty( $role ) ) {
            
            /* Role management capabilities. */
            $role->add_cap( 'edit_competition classification' );
        }
    }

    public function actionInit_UserFields()
    {
        add_action( 'edit_user_profile', array( &$this, 'actionUser_Profile' ) );
        add_action( 'show_user_profile', array( &$this, 'actionUser_Profile' ) );
        add_action( 'personal_options_update', array( &$this, 'actionProfile_Update_Save' ) );
        add_action( 'edit_user_profile_update', array( &$this, 'actionProfile_Update_Save' ) );
    }

    /**
     * Add the Tools and Options to the Management and Options page repectively
     *
     * @WordPress Action admin_menu
     *
     */
    public function actionAdminMenu()
    {
        add_menu_page( 'AVH RPS', 'AVH RPS', '', AVH_RPS_Define::MENU_SLUG, array( &$this, 'menuOverview' ) );
    }

    /**
     * Adds Settings next to the plugin actions
     *
     * @WordPress Filter plugin_action_links_avh-first-defense-against-spam/avh-fdas.php
     * @param array $links
     * @return array
     *
     * @since 1.0
     */
    public function filterPluginActions( $links )
    {
        $folder = AVH_Common::getBaseDirectory( $this->_settings->plugin_basename );
        $settings_link = '<a href="admin.php?page=' . $folder . '">' . __( 'Settings', 'avh-fdas' ) . '</a>';
        array_unshift( $links, $settings_link ); // before other links
        return $links;
    }

    /**
     * Used when we set our own screen options.
     *
     * The filter needs to be set during construct otherwise it's not regonized.
     *
     * @param unknown_type $default
     * @param unknown_type $option
     * @param unknown_type $value
     */
    public function filterSetScreenOption( $error_value, $option, $value )
    {
        $return = $error_value;
        switch ( $option ) {
            case 'ipcachelog_per_page':
                $value = (int) $value;
                $return = $value;
                if ( $value < 1 || $value > 999 ) {
                    $return = $error_value;
                }
                break;
            default:
                $return = $error_value;
                break;
        }
        return $return;
    }

    public function actionUser_Profile( $user_id )
    {
        $userID = $user_id->ID;
        $_rps_class_bw = get_user_meta( $userID, 'rps_class_bw', true );
        $_rps_class_color = get_user_meta( $userID, 'rps_class_color', true );
        $_rps_class_print_bw = get_user_meta( $userID, 'rps_class_print_bw', true );
        $_rps_class_print_color = get_user_meta( $userID, 'rps_class_print_color', true );
        
        $_classification = array( 'beginner'=>'Beginner', 'advanced'=>'Advanced', 'salon'=>'Salon' );
        echo '<h3 id="rps">Competition Classification</h3>';
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th>Classification Digital B&W</th>';
        echo '<td>';
        if ( current_user_can( 'edit_competition classification' ) ) {
            $p = '';
            $r = '';
            echo '<select name="rps_class_bw" id="rps_class_bw">';
            foreach ( $_classification as $key => $value ) {
                if ( $key === $_rps_class_bw ) {
                    $p = "\n\t<option selected='selected' value='" . esc_attr( $key ) . "'>$value</option>";
                } else {
                    $r .= "\n\t<option value='" . esc_attr( $key ) . "'>$value</option>";
                }
            }
            echo $p . $r;
            echo '</select>';
        } else {
            echo $_classification[$_rps_class_bw];
        }
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>Classification Digital Color</th>';
        echo '<td>';
        if ( current_user_can( 'edit_competition classification' ) ) {
            $p = '';
            $r = '';
            echo '<select name="rps_class_color" id="rps_class_color">';
            foreach ( $_classification as $key => $value ) {
                if ( $key === $_rps_class_color ) {
                    $p = "\n\t<option selected='selected' value='" . esc_attr( $key ) . "'>$value</option>";
                } else {
                    $r .= "\n\t<option value='" . esc_attr( $key ) . "'>$value</option>";
                }
            }
            echo $p . $r;
            echo '</select>';
        } else {
            echo $_classification[$_rps_class_color];
        }
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>Classification Print B&W</th>';
        echo '<td>';
        if ( current_user_can( 'edit_competition classification' ) ) {
            $p = '';
            $r = '';
            echo '<select name="rps_class_print_bw" id="rps_class_print_bw">';
            foreach ( $_classification as $key => $value ) {
                if ( $key === $_rps_class_print_bw ) {
                    $p = "\n\t<option selected='selected' value='" . esc_attr( $key ) . "'>$value</option>";
                } else {
                    $r .= "\n\t<option value='" . esc_attr( $key ) . "'>$value</option>";
                }
            }
            echo $p . $r;
            echo '</select>';
        } else {
            echo $_classification[$_rps_class_print_bw];
        }
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>Classification Print Color</th>';
        echo '<td>';
        if ( current_user_can( 'edit_competition classification' ) ) {
            $p = '';
            $r = '';
            echo '<select name="rps_class_print_color" id="rps_class_print_color">';
            foreach ( $_classification as $key => $value ) {
                if ( $key === $_rps_class_print_color ) {
                    $p = "\n\t<option selected='selected' value='" . esc_attr( $key ) . "'>$value</option>";
                } else {
                    $r .= "\n\t<option value='" . esc_attr( $key ) . "'>$value</option>";
                }
            }
            echo $p . $r;
            echo '</select>';
        } else {
            echo $_classification[$_rps_class_print_color];
        }
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
    }

    public function actionProfile_Update_Save( $user_id )
    {
        $userID = $user_id;
        if ( isset( $_POST['rps_class_bw'] ) ) {
            $_rps_class_bw = $_POST["rps_class_bw"];
        } else {
            $_rps_class_bw = get_user_meta( $userID, 'rps_class_bw', true );
        }
        if ( isset( $_POST['rps_class_color'] ) ) {
            $_rps_class_color = $_POST['rps_class_color'];
        } else {
            $_rps_class_color = get_user_meta( $userID, 'rps_class_color', true );
        }
        if ( isset( $_POST['rps_class_print_bw'] ) ) {
            $_rps_class_print_bw = $_POST["rps_class_print_bw"];
        } else {
            $_rps_class_print_bw = get_user_meta( $userID, 'rps_class_print_bw', true );
        }
        if ( isset( $_POST['rps_class_print_color'] ) ) {
            $_rps_class_print_color = $_POST['rps_class_print_color'];
        } else {
            $_rps_class_print_color = get_user_meta( $userID, 'rps_class_print_color', true );
        }
        
        update_user_meta( $userID, "rps_class_bw", $_rps_class_bw );
        update_user_meta( $userID, "rps_class_color", $_rps_class_color );
        update_user_meta( $userID, "rps_class_print_bw", $_rps_class_print_bw );
        update_user_meta( $userID, "rps_class_print_color", $_rps_class_print_color );
    
    }

    ############## Admin WP Helper ##############
    /**
     * Display plugin Copyright
     *
     */
    private function _printAdminFooter()
    {
        echo '<div class="clear">';
        echo '<p class="footer_avhfdas">';
        printf( '&copy; Copyright 2010 <a href="http://blog.avirtualhome.com/" title="My Thoughts">Peter van der Does</a> | AVH First Defense Against Spam version %s', AVH_RPS_Define::PLUGIN_VERSION );
        echo '</p>';
    }

    /**
     * Display WP alert
     *
     */
    private function _displayMessage()
    {
        if ( $this->_message != '' ) {
            $message = $this->_message;
            $status = $this->_status;
            $this->_message = $this->_status = ''; // Reset
        }
        if ( isset( $message ) ) {
            $status = ( $status != '' ) ? $status : 'updated fade';
            echo '<div id="message"	class="' . $status . '">';
            echo '<p><strong>' . $message . '</strong></p></div>';
        }
    }

    /**
     * Displays the icon needed. Using this instead of core in case we ever want to show our own icons
     * @param $icon strings
     * @return string
     */
    private function _displayIcon( $icon )
    {
        return ( '<div class="icon32" id="icon-' . $icon . '"><br/></div>' );
    }

    /**
     * Ouput formatted options
     *
     * @param array $option_data
     * @return string
     */
    private function _printOptions( $option_data, $option_actual )
    {
        // Generate output
        $output = '';
        $output .= "\n" . '<table class="form-table avhfdas-options">' . "\n";
        foreach ( $option_data as $option ) {
            $section = substr( $option[0], strpos( $option[0], '[' ) + 1 );
            $section = substr( $section, 0, strpos( $section, '][' ) );
            $option_key = rtrim( $option[0], ']' );
            $option_key = substr( $option_key, strpos( $option_key, '][' ) + 2 );
            // Helper
            if ( $option[2] == 'helper' ) {
                $output .= '<tr style="vertical-align: top;"><td class="helper" colspan="2">' . $option[4] . '</td></tr>' . "\n";
                continue;
            }
            switch ( $option[2] ) {
                case 'checkbox':
                    $input_type = '<input type="checkbox" id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr( $option[3] ) . '" ' . checked( '1', $option_actual[$section][$option_key], false ) . ' />' . "\n";
                    $explanation = $option[4];
                    break;
                case 'dropdown':
                    $selvalue = explode( '/', $option[3] );
                    $seltext = explode( '/', $option[4] );
                    $seldata = '';
                    foreach ( (array) $selvalue as $key => $sel ) {
                        $seldata .= '<option value="' . $sel . '" ' . selected( $sel, $option_actual[$section][$option_key], false ) . ' >' . ucfirst( $seltext[$key] ) . '</option>' . "\n";
                    }
                    $input_type = '<select id="' . $option[0] . '" name="' . $option[0] . '">' . $seldata . '</select>' . "\n";
                    $explanation = $option[5];
                    break;
                case 'text-color':
                    $input_type = '<input type="text" ' . ( ( $option[3] > 50 ) ? ' style="width: 95%" ' : '' ) . 'id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr( stripcslashes( $option_actual[$section][$option_key] ) ) . '" size="' . $option[3] . '" /><div class="box_color ' . $option[0] . '"></div>' . "\n";
                    $explanation = $option[4];
                    break;
                case 'textarea':
                    $input_type = '<textarea rows="' . $option[5] . '" ' . ( ( $option[3] > 50 ) ? ' style="width: 95%" ' : '' ) . 'id="' . $option[0] . '" name="' . $option[0] . '" size="' . $option[3] . '" />' . esc_attr( stripcslashes( $option_actual[$section][$option_key] ) ) . '</textarea>';
                    $explanation = $option[4];
                    break;
                case 'text':
                default:
                    $input_type = '<input type="text" ' . ( ( $option[3] > 50 ) ? ' style="width: 95%" ' : '' ) . 'id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr( stripcslashes( $option_actual[$section][$option_key] ) ) . '" size="' . $option[3] . '" />' . "\n";
                    $explanation = $option[4];
                    break;
            }
            // Additional Information
            $extra = '';
            if ( $explanation ) {
                $extra = '<br /><span class="description">' . __( $explanation ) . '</span>' . "\n";
            }
            // Output
            $output .= '<tr style="vertical-align: top;"><th align="left" scope="row"><label for="' . $option[0] . '">' . __( $option[1] ) . '</label></th><td>' . $input_type . '	' . $extra . '</td></tr>' . "\n";
        }
        $output .= '</table>' . "\n";
        return $output;
    }

    /**
     * Display error message at bottom of comments.
     *
     * @param string $msg Error Message. Assumed to contain HTML and be sanitized.
     */
    private function _comment_footer_die( $msg )
    {
        echo "<div class='wrap'><p>$msg</p></div>";
        die();
    }
}