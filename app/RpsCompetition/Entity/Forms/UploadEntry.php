<?php

namespace RpsCompetition\Entity\Forms;

/**
 * Class UploadEntry
 *
 * @package RpsCompetition\Entity
 */
class UploadEntry
{
    protected $file_name;
    protected $medium_subset;
    protected $title;
    protected $wp_get_referer;

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->file_name;
    }

    /**
     * @param mixed $file_name
     */
    public function setFileName($file_name)
    {
        $this->file_name = $file_name;
    }

    /**
     * @return string
     */
    public function getMediumSubset()
    {
        return $this->medium_subset;
    }

    /**
     * @param string $medium_subset
     */
    public function setMediumSubset($medium_subset)
    {
        $this->medium_subset = $medium_subset;
    }

    /**
     * @return string mixed
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
