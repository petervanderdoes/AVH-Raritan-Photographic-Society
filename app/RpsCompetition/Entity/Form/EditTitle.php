<?php

namespace RpsCompetition\Entity\Form;

/**
 * Class EditTitle
 *
 * @package   RpsCompetition\Entity\Form
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class EditTitle
{
    protected $id;
    protected $m;
    protected $new_title;
    protected $server_file_name;
    protected $title;
    protected $wp_get_referer;

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getM()
    {
        return $this->m;
    }

    /**
     * @param string $m
     */
    public function setM($m)
    {
        $this->m = $m;
    }

    /**
     * @return string
     */
    public function getNewTitle()
    {
        return $this->new_title;
    }

    /**
     * @param string $new_title
     */
    public function setNewTitle($new_title)
    {
        $this->new_title = $new_title;
    }

    /**
     * @return string
     */
    public function getServerFileName()
    {
        return $this->server_file_name;
    }

    /**
     * @param string $server_file_name
     */
    public function setServerFileName($server_file_name)
    {
        $this->server_file_name = $server_file_name;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getWpGetReferer()
    {
        return $this->wp_get_referer;
    }

    /**
     * @param string $wp_get_referer
     */
    public function setWpGetReferer($wp_get_referer)
    {
        $this->wp_get_referer = $wp_get_referer;
    }
}
