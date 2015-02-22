<?php

namespace RpsCompetition\Entity\Forms;

/**
 * Class MyEntries
 *
 * @package RpsCompetition\Entity\Forms
 */
class MyEntries
{
    protected $_wpnonce;
    protected $classification;
    protected $comp_date;
    protected $medium;
    protected $select_comp = [];
    protected $selected_medium = [];
    protected $submit_control;

    /**
     * @return string
     */
    public function getClassification()
    {
        return $this->classification;
    }

    /**
     * @param string $classification
     */
    public function setClassification($classification)
    {
        $this->classification = $classification;
    }

    /**
     * @return string
     */
    public function getCompDate()
    {
        return $this->comp_date;
    }

    /**
     * @param string $comp_date
     */
    public function setCompDate($comp_date)
    {
        $this->comp_date = $comp_date;
    }

    /**
     * @return string
     */
    public function getMedium()
    {
        return $this->medium;
    }

    /**
     * @param string $medium
     */
    public function setMedium($medium)
    {
        $this->medium = $medium;
    }

    /**
     * @return array
     */
    public function getSelectComp()
    {
        return $this->select_comp;
    }

    /**
     * @param array $select_comp
     */
    public function setSelectComp($select_comp)
    {
        $this->select_comp = $select_comp;
    }

    /**
     * @return array
     */
    public function getSelectedMedium()
    {
        return $this->selected_medium;
    }

    /**
     * @param array $selected_medium
     */
    public function setSelectedMedium($selected_medium)
    {
        $this->selected_medium = $selected_medium;
    }

    /**
     * @return string
     */
    public function getSubmitControl()
    {
        return $this->submit_control;
    }

    /**
     * @param string $submit_control
     */
    public function setSubmitControl($submit_control)
    {
        $this->submit_control = $submit_control;
    }

    /**
     * @return string
     */
    public function getWpnonce()
    {
        return $this->_wpnonce;
    }

    /**
     * @param string $wpnonce
     */
    public function setWpnonce($wpnonce)
    {
        $this->_wpnonce = $wpnonce;
    }
}
