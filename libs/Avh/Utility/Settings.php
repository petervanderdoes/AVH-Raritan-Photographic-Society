<?php
if ( !defined('AVH_FRAMEWORK') )
	die('You are not allowed to call this page directly.');

if ( !class_exists('AVH2_Settings') ) {

	final class AVH2_Settings
	{
		/**
		 * Our array of settings
		 *
		 * @access protected
		 */
		private $_settings = array();

		/**
		 * Stores settings in the registry
		 *
		 * @param string $data
		 * @param string $key
		 *        The key for the array
		 * @return void
		 */
		public function storeSetting ($key, $data)
		{
			$this->_settings[$key] = $data;
		}

		/**
		 * Gets a setting from the registry
		 *
		 * @param string $key
		 *        The key in the array
		 * @param mixed $default
		 *        If the requested key doesn't exists this value will be returned
		 * @return mixed
		 */
		public function getSetting ($key, $default = NULL)
		{
			if ( isset($this->_settings[$key]) ) {
				$_return = $this->_settings[$key];
			} else {
				$_return = $default;
			}
			return $_return;
		}

		/**
		 * Removes a setting from the registry
		 *
		 * @param string $key
		 *        The key for the array
		 */
		public function removeSetting ($key)
		{
			if ( isset($this->_settings[$key]) ) {
				unset($this->_settings[$key]);
			}
		}
	}
}