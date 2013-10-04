<?php
namespace Avh\Utility;

abstract class AVH_Settings
{

    /**
     * Our array of settings
     *
     * @access protected
     */
    private $_settings = array();

    public function __get($key)
    {
        if ( isset($this->_settings[$key]) ) {
            $_return = $this->_settings[$key];
        } else {
            $_return = null;
        }
        return $_return;
    }

    public function __set($key, $data)
    {
        $this->_settings[$key] = $data;
    }

    public function __unset($key)
    {
        if ( isset($this->_settings[$key]) ) {
            unset($this->_settings[$key]);
        }
    }

    public function __isset($key)
    {
        return isset($this->_settings[$key]);
    }
}