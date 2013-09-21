<?php

/**
 * AVH_UR_PluginController
 *
 * A parent class for WordPress plugins.
 * Author: Peter van der Does
 * Original Author: Emmanuel GEORJON
 */

/**
 * Class AVH2_PluginController
 *
 * Provide some functions to create a WordPress plugin
 */
class AVH2_PluginController
{
    protected $_requirements_error_msg = '';
    protected $_update_notice = '';
    protected $_options_page_id = '';
    protected $_pages = array();
    protected $_hooks = array();
    protected $_tinyMCE_buttons = array();
    protected $_pluginfile = '';
    protected $_textdomain = '';
    protected $_settings;
    protected $_classes;
    protected $_options;
    public static $base_url;

    /**
     * Class contructor
     *
     * @return object
     */
    public function __construct (AVH2_Settings $settings, AVH2_Options $options)
    {
        $this->_settings = $settings;
        $this->_options = $options;

        // Move some of the saved settings to local, this makes things easier to read and probably speed things up as
        // well.
        $this->_pluginfile = $this->_settings->plugin_file;
        $this->_textdomain = $this->_settings->text-domain;
        self::$base_url = $this->_settings->plugin_url;
    }

    /**
     * Class destructor
     *
     * @return boolean true
     */
    public function __destruct ()
    {
        // Nothing
    }

    /**
     * Sets up basic plugin needs.
     *
     * @action init
     */
    public function actionInit ()
    {
        if ( !isset($this->_textdomain) ) {
            load_plugin_textdomain($this->_textdomain, FALSE, $this->_settings->plugin_dir . '/lang');
        }

        // Register Styles and Scripts

        $_style = $this->_getStyleName();
        wp_register_style($_style . '-css', $this->_settings->plugin_url . '/css/' . $_style . '.css', array(), $this->_settings->plugin_version, 'screen');
    }

    /**
     * Runs on register_activation_hook
     */
    public function installPlugin ()
    {}

    /**
     * Gets the style name.
     *
     * @param string $style
     *        If left empty the style name resolves to admin or public, depending on whether the function
     *        is called while in admin
     * @return string
     */
    protected function _getStyleName ($style = '')
    {
        $_minified = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        if ( empty($style) )
            if ( is_admin() ) {
                $_full_style_name = $this->_settings->file_prefix . 'admin' . $_minified;
            } else {
                $_full_style_name = $this->_settings->file_prefix . 'public' . $_minified;
            }
        else {
            $_full_style_name = $this->_settings->file_prefix . $style . $_minified;
        }
        return $_full_style_name;
    }

    /**
     * Gets the javascript name.
     *
     * @param string $style
     *        If left empty the style name resolves to admin or public, depending on whether the function
     *        is called while in admin
     * @return string
     */
    protected function _getJsName ($script = '')
    {
        $_minified = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.closure';
        if ( empty($script) ) {
            if ( is_admin() ) {
                $_full_script_name = $this->_settings->file_prefix . 'admin' . $_minified;
            } else {
                $_full_script_name = $this->_settings->file_prefix . 'public' . $_minified;
            }
        } else {
            $_full_script_name = $this->_settings->file_prefix . $script . $_minified;
        }
        return $_full_script_name;
    }

    public function deactivation ()
    {}

    /**
     * Called to start the plugin.
     */
    public function load ()
    {
        add_action('init', array($this,'actionInit'));

        if ( is_admin() ) {
            register_deactivation_hook($this->_pluginfile, array($this,'deactivation'));
            register_activation_hook($this->_pluginfile, array($this,'installPlugin'));
            add_action('in_plugin_update_message-' . basename($this->_pluginfile), array($this,'actionIn_plugin_update_message'));
        }
    }

    /**
     * set_update_notice
     *
     * @param string $msg
     */
    public function set_update_notice ($msg)
    {
        $this->_update_notice = $msg;
    }

    /**
     * This function is called when there's an update of the plugin available @ WordPress
     */
    public function actionIn_plugin_update_message ()
    {
        $_response = wp_remote_get($this->_settings->plugin_readme_url, array('user-agent' => 'WordPress/' . AVH2_Common::getWordpressVersion() . ' ' . $this->_settings->plugin_name . '/' . $this->_settings->plugin_version));
        if ( !is_wp_error($_response) || is_array($_response) ) {
            $_data = $_response['body'];
            $_matches = null;
            if ( preg_match('~==\s*Changelog\s*==\s*=\s*Version\s*[0-9.]+\s*=(.*)(=\s*Version\s*[0-9.]+\s*=|$)~Uis', $_data, $_matches) ) {
                $_changelog = (array) preg_split('~[\r\n]+~', trim($_matches[1]));
                $_prev_version = null;
                preg_match('([0-9.]+)', $_matches[2], $_prev_version);
                echo '<div style="color: #f00;">What\'s new in this version:</div><div style="font-weight: normal;">';
                $_ul = false;
                foreach ( $_changelog as $_index => $_line ) {
                    if ( preg_match('~^\s*\*\s*~', $_line) ) {
                        if ( !$_ul ) {
                            echo '<ul style="list-style: disc; margin-left: 20px;">';
                            $_ul = true;
                        }
                        $_line = preg_replace('~^\s*\*\s*~', '', htmlspecialchars($_line));
                        echo '<li style="width: 50%; margin: 0; float: left; ' . ( $_index % 2 == 0 ? 'clear: left;' : '' ) . '">' . $_line . '</li>';
                    } else {
                        if ( $_ul ) {
                            echo '</ul><div style="clear: left;"></div>';
                            $_ul = false;
                        }
                        echo '<p style="margin: 5px 0;">' . htmlspecialchars($_line) . '</p>';
                    }
                }
                if ( $_ul ) {
                    echo '</ul><div style="clear: left;"></div>';
                }
                if ( $_prev_version[0] != $this->_settings->plugin_version ) {
                    echo '<div style="color: #f00; font-weight: bold;">';
                    echo '<br />';
                    echo sprintf(__('The installed version, %s, is more than one version behind.', $this->_textdomain), $this->_settings->plugin_version);
                    echo '<br />';
                    echo __('More changes have been made since the currently installed version, consider checking the changelog.', $this->_textdomain);
                    echo '</div><div style="clear: left;"></div>';
                }
                echo '</div>';
            }
        }
    }

    /**
     * Display a specific message in the plugin update message.
     */
    public function plugin_update_notice ()
    {
        if ( $this->_update_notice != '' ) {
            echo '<span class="spam">' . strip_tags(__($this->_update_notice, $this->_textdomain), '<br><a><b><i><span>') . '</span>';
        }
    }

    /**
     * Add a "settings" link to access to the option page from the plugin list
     *
     * @param string $links
     * @return none
     */
    public function filter_plugin_actions ($links)
    {
        if ( $this->_options_page_id != '' ) {
            $_settings_link = '<a href="' . admin_url('options-general.php?page=' . $this->_options_page_id) . '">' . __('Settings') . '</a>';
            array_unshift($links, $_settings_link);
        }
        return $links;
    }

    /**
     * Display a metabox-like in admin interface.
     *
     * @param string $id
     *        string
     * @param string $title
     * @param string $content
     */
    public function display_box ($id, $title, $content)
    {
        echo '<div id="' . $id . '" class="postbox">';
        echo '<div class="handlediv" title="Click to toggle">';
        echo '<br />';
        echo '</div>';
        echo '<h3 class="hndle">';
        echo '<span>';
        _e($title, $this->_textdomain);
        echo '</span>';
        echo '</h3>';
        echo '<div class="inside">';
        _e($content, $this->_textdomain);
        echo '</div>';
        echo '</div>';
    }

    /**
     * Add a menu and a page
     */
    public function add_page (array $args)
    {

        // @format_off
            $_default_args = array(	 'id' 				=> '',
                                     'parent_id'		=> '',
                                     'type'				=> 'options',
                                     'page_title'		=> $this->_settings->plugin_name.__(' settings', $this->_textdomain),
                                     'menu_title'		=> $this->_settings->plugin_name,
                                     'access_level'		=> 'manage_options',
                                     'display_callback'	=> '',
                                     'option_link' 		=> false,
                                     'load_callback' 	=> false,
                                     'load_scripts'		=> false,
                                     'shortname'		=> null,
                                     'icon_url'			=> null,
                                     'position'         => null
            );
            // @format_on

        $_values = wp_parse_args($args, $_default_args);
        if ( $_values['id'] != '' && $_values['display_callback'] != '' ) {
            $this->_pages[$_values['id']] = $_values;
            if ( $_values['option_link'] ) {
                $this->_options_page_id = $_values['id'];
            }
            return ( $_values['id'] );
        } else {
            return ( false );
        }
    }

    /**
     * Run the admin menu hook
     */
    public function admin_menu ()
    {

        // @format_off
            $_page_list = array ( 'dashboard' => 'index.php',
                                   'posts'	   => 'edit.php',
                                   'options'   => 'options-general.php',
                                   'settings'  => 'options-general.php',
                                   'tools'	   => 'tools.php',
                                   'theme'	   => 'themes.php',
                                   'users'	   => 'users.php',
                                   'media'	   => 'upload.php',
                                   'links'	   => 'link-manager.php',
                                   'pages'	   => 'edit.php?post_type=page',
                                   'comments'  => 'edit-comments.php');
            // @format_on

        // Add a new submenu under Options:
        if ( sizeof($this->_pages) > 0 ) {
            foreach ( $this->_pages as $_id => $_page ) {

                // Create the menu
                if ( $_page['type'] == 'menu' ) {
                    //@format_off
                        $_hook = add_menu_page(	__($_page['page_title'], $this->_textdomain),
                                                __($_page['menu_title'], $this->_textdomain),
                                                $_page['access_level'],
                                                $_id,
                                                array($this, $_page['display_callback']),
                                                $_page['icon_url'],
                                                $_page['position'] );
                        // @format_on
                } else {
                    if ( $_page['type'] != 'submenu' )
                        $_page['parent_id'] = $_page_list[$_page['type']];

                        //@format_off
                        $_hook = add_submenu_page( $_page['parent_id'],
                                                   __($_page['page_title'], $this->_textdomain),
                                                   __($_page['menu_title'], $this->_textdomain),
                                                   $_page['access_level'],
                                                   $_id,
                                                   array($this, $_page['display_callback']) );
                        // @format_on

                    if ( isset($this->_pages[$_page['parent_id']]) && $this->_pages[$_page['parent_id']]['shortname'] != '' ) {
                        global $submenu;
                        $submenu[$_page['parent_id']][0][0] = $this->_pages[$_page['parent_id']]['shortname'];
                        $this->_pages[$_page['parent_id']]['shortname'] = '';
                    }
                }

                // Get the hook of the page
                $this->_hooks[$_page['display_callback']][$_id] = $_hook;

                // Add load, and print_scripts functions (attached to the hook)
                if ( $_page['load_callback'] !== false ) {
                    add_action('load-' . $_hook, array($this,$_page['load_callback']));
                }
                if ( $_page['load_scripts'] !== false ) {
                    add_action('admin_print_scripts-' . $_hook, array($this,$_page['load_scripts']));
                }

                // Add the link into the plugin page
                if ( $this->_options_page_id == $_id ) {
                    add_filter('plugin_action_links_' . plugin_basename($this->_pluginfile), array($this,'filter_plugin_actions'));
                }
            }
            unset($this->_pages);
        }
    }

    /**
     * Returns the pagehook name
     *
     * @param string $page_id
     * @param string $function
     * @return mixed If pagehook does not exists return false other return the page hook name
     */
    protected function _getPageHook ($page_id = '', $function = '')
    {
        if ( $page_id == '' || $function == '' ) {
            return false;
        } else {
            return ( isset($this->_hooks[$function][$page_id]) ? $this->_hooks[$function][$page_id] : false );
        }
    }

    /**
     * Adds the given capability to the given role
     *
     * @param string $capability
     * @param string $role
     */
    protected function _addCapability ($capability, $role)
    {
        $_role_object = get_role($role);
        if ( $_role_object != null && !$_role_object->has_cap($capability) ) {
            $_role_object->add_cap($capability);
        }
    }

    /**
     * Add a TinyMCE button
     *
     * @param string $button_name
     */
    public function add_tinyMCE_button ($button_name, $tinymce_plugin_path, $js_file_name = 'editor_plugin.js')
    {
        $_index = sizeof($this->_tinyMCE_buttons);
        $this->_tinyMCE_buttons[$_index]->name = $button_name;
        $this->_tinyMCE_buttons[$_index]->js_file = $js_file_name;
        $this->_tinyMCE_buttons[$_index]->path = $tinymce_plugin_path;
    }

    /**
     * Insert button in wordpress post editor
     *
     * @param array $buttons
     * @return array
     */
    public function register_button ($buttons)
    {
        foreach ( $this->_tinyMCE_buttons as $_value ) {
            array_push($buttons, $_value->name);
        }
        return $buttons;
    }

    /**
     * Load the TinyMCE plugin : editor_plugin.js
     *
     * @param array $plugin_array
     * @return $plugin_array
     */
    public function add_tinymce_plugin (array $plugin_array)
    {
        foreach ( $this->_tinyMCE_buttons as $_value ) {
            $plugin_array[$_value->name] = $this->_settings->plugin_url . $_value->path . '/' . $_value->js_file;
        }
        return $plugin_array;
    }

    public function tiny_mce_version ($version)
    {
        return ++$version;
    }
} // End of class
