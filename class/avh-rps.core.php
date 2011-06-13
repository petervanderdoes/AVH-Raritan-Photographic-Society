<?php
if (! defined('AVH_FRAMEWORK'))
	die('You are not allowed to call this page directly.');

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
	public function __construct ()
	{
		$this->_settings = AVH_RPS_Settings::getInstance();
		$this->_db_version = 0;
		$this->_db_options = 'avhrps_options';
		$this->_db_data = 'avhrps_data';
		$this->_db_nonces = 'avhfdas_nonces';
		/**
		 * Default options - General Purpose
		 */
		$this->_default_options_general = array ( 'version' => AVH_RPS_Define::PLUGIN_VERSION, 'dbversion' => $this->_db_version, 
												);
		$this->_default_options = array ( 'general' => $this->_default_options_general);
		
		//add_action('init', array(&$this,'handleInitializePlugin'),10);
		$this->handleInitializePlugin();
		
		return;
	}

	function handleInitializePlugin ()
	{
		
		/**
		 * Set the options for the program
		 *
		 */
		$this->_loadOptions();
		$this->_loadData();
		$this->_setTables();
		// Check if we have to do upgrades
		if ((! isset($this->_options['general']['dbversion'])) || $this->getOptionElement('general', 'dbversion') < $this->_db_version) {
			$this->_doUpgrade();
		}
		$this->_settings->storeSetting('siteurl', get_option('siteurl'));
		$this->_settings->storeSetting('graphics_url', plugins_url('images', $this->_settings->plugin_basename));
		$this->_settings->storeSetting('js_url', plugins_url('js', $this->_settings->plugin_basename));
		$this->_settings->storeSetting('css_url', plugins_url('css', $this->_settings->plugin_basename));
		
	}

	/**
	 * Setup DB Tables
	 * @return unknown_type
	 */
	private function _setTables ()
	{
		global $wpdb;
		// add DB pointer
		$wpdb->avhfdasipcache = $wpdb->prefix . 'avhfdas_ipcache';
	}

	/**
	 * Checks if running version is newer and do upgrades if necessary
	 *
	 */
	private function _doUpgrade ()
	{
		$options = $this->getOptions();
		$data = $this->getData();
		// Introduced dbversion starting with v2.1
		//if (! isset($options['general']['dbversion']) || $options['general']['dbversion'] < 4) {
		//	list ($options, $data) = $this->_doUpgrade21($options, $data);
		//}
		
		// Add none existing sections and/or elements to the options
		foreach ($this->_default_options as $section => $default_options) {
			if (! array_key_exists($section, $options)) {
				$options[$section] = $default_options;
				continue;
			}
			foreach ($default_options as $element => $default_value) {
				if (! array_key_exists($element, $options[$section])) {
					$options[$section][$element] = $default_value;
				}
			}
		}
		// Add none existing sections and/or elements to the data
		foreach ($this->_default_data as $section => $default_data) {
			if (! array_key_exists($section, $data)) {
				$data[$section] = $default_data;
				continue;
			}
			foreach ($default_data as $element => $default_value) {
				if (! array_key_exists($element, $data[$section])) {
					$data[$section][$element] = $default_value;
				}
			}
		}
		$options['general']['version'] = AVH_RPS_Define::PLUGIN_VERSION;
		$options['general']['dbversion'] = $this->_db_version;
		$this->saveOptions($options);
		$this->saveData($data);
	}


	/**
	 * Upgrade to version 2.1
	 *
	 * @param array $old_options
	 * @param array $old_data
	 * @return array
	 *
	 */
	private function _doUpgrade21 ($old_options, $old_data)
	{
		$new_options = $old_options;
		$new_data = $old_data;
		// Changed Administrative capabilties names
		$role = get_role('administrator');
		if ($role != null && $role->has_cap('avh_fdas')) {
			$role->remove_cap('avh_fdas');
			$role->add_cap('role_avh_fdas');
		}
		if ($role != null && $role->has_cap('admin_avh_fdas')) {
			$role->remove_cap('admin_avh_fdas');
			$role->add_cap('role_admin_avh_fdas');
		}
		return array ( $new_options, $new_data );
	}

	/**
	 * Upgrade to version 2.2
	 *
	 * @param $options
	 * @param $data
	 */
	private function _doUpgrade22 ($old_options, $old_data)
	{
		global $wpdb;
		$new_options = $old_options;
		$new_data = $old_data;
		$sql = 'ALTER TABLE `' . $wpdb->avhfdasipcache . '`
				CHANGE COLUMN `date` `added` DATETIME  NOT null DEFAULT \'0000-00-00 00:00:00\',
				ADD COLUMN `lastseen` DATETIME  NOT null DEFAULT \'0000-00-00 00:00:00\' AFTER `added`,
				DROP INDEX `date`,
				ADD INDEX `added`(`added`),
				ADD INDEX `lastseen`(`lastseen`);';
		$result = $wpdb->query($sql);
		$sql = 'UPDATE ' . $wpdb->avhfdasipcache . ' SET `lastseen` = `added`;';
		$result = $wpdb->query($sql);
		return array ( $new_options, $new_data );
	}

	/**
	 * Upgrade DB 23
	 *
	 * Change: Remove option to email Project Honey Pot info when Stop Forum Spam threshold is reached
	 * @param array $old_options
	 * @param array $old_data
	 * @return array
	 *
	 */
	private function _doUpgrade23 ($old_options, $old_data)
	{
		$new_options = $old_options;
		$new_data = $old_data;
		unset($new_options['general']['emailphp']);
		return array ( $new_options, $new_data );
	}

	/*********************************
	 * *
	 * Methods for variable: options *
	 * *
	 ********************************/
	/**
	 * @param array $data
	 */
	private function _setOptions ($options)
	{
		$this->_options = $options;
	}

	/**
	 * return array
	 */
	public function getOptions ()
	{
		return ($this->_options);
	}

	/**
	 * Save all current options and set the options
	 *
	 */
	public function saveOptions ($options)
	{
		update_option($this->_db_options, $options);
		wp_cache_flush(); // Delete cache
		$this->_setOptions($options);
	}

	/**
	 * Retrieves the plugin options from the WordPress options table and assigns to class variable.
	 * If the options do not exists, like a new installation, the options are set to the default value.
	 *
	 * @return none
	 */
	private function _loadOptions ()
	{
		$options = get_option($this->_db_options);
		if (false === $options) { // New installation
			$this->_resetToDefaultOptions();
		} else {
			$this->_setOptions($options);
		}
	}

	/**
	 * Get the value for an option element. If there's no option is set on the Admin page, return the default value.
	 *
	 * @param string $key
	 * @param string $option
	 * @return mixed
	 */
	public function getOptionElement ($option, $key)
	{
		if (isset($this->_options[$option][$key])) {
			$return = $this->_options[$option][$key]; // From Admin Page
		} else {
			$return = $this->_default_options[$option][$key]; // Default
		}
		return ($return);
	}

	/**
	 * Reset to default options and save in DB
	 *
	 */
	private function _resetToDefaultOptions ()
	{
		$this->_options = $this->_default_options;
		$this->saveOptions($this->_default_options);
	}

	/******************************
	 * *
	 * Methods for variable: data *
	 * *
	 *****************************/
	/**
	 * @param array $data
	 */
	private function _setData ($data)
	{
		$this->_data = $data;
	}

	/**
	 * @return array
	 */
	public function getData ()
	{
		return ($this->_data);
	}

	/**
	 * Save all current data to the DB
	 * @param array $data
	 *
	 */
	public function saveData ($data)
	{
		update_option($this->_db_data, $data);
		wp_cache_flush(); // Delete cache
		$this->_setData($data);
	}

	/**
	 * Retrieve the data from the DB
	 *
	 * @return array
	 */
	private function _loadData ()
	{
		$data = get_option($this->_db_data);
		if (false === $data) { // New installation
			$this->_resetToDefaultData();
		} else {
			$this->_setData($data);
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
	public function getDataElement ($option, $key)
	{
		if ($this->_data[$option][$key]) {
			$return = $this->_data[$option][$key];
		} else {
			$return = false;
		}
		return ($return);
	}

	/**
	 * Reset to default data and save in DB
	 *
	 */
	private function _resetToDefaultData ()
	{
		$this->_data = $this->_default_data;
		$this->saveData($this->_default_data);
	}

	/**
	 * @return string
	 */
	public function getComment ($str = '')
	{
		return $this->_comment . ' ' . trim($str) . ' -->';
	}

	/**
	 * @return the $_db_nonces
	 */
	public function getDbNonces ()
	{
		return $this->_db_nonces;
	}

	/**
	 * @return the $_default_nonces
	 */
	public function getDefaultNonces ()
	{
		return $this->_default_nonces;
	}
} //End Class AVH_RPS_Core