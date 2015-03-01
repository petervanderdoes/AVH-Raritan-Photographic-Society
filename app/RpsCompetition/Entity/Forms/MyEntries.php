<?php

namespace RpsCompetition\Entity\Forms;

/**
 * Class MyEntries
 *
 * @package RpsCompetition\Entity\Form
 */
class MyEntries
{
    protected $_wpnonce;
    protected $classification;
    protected $comp_date;
    protected $medium;
    protected $select_comp;
    protected $selected_medium;
    protected $submit_control;

    private $selected_comp_choices = [];
    private $selected_medium_choices = [];
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
    public function getSelectedCompChoices()
    {
        return $this->selected_comp_choices;
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
     * @return array
     */
    public function getSelectedMediumChoices()
    {
        return $this->selected_medium_choices;
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

    /**
     * @param array $selected_comp_choices
     */
    public function setSelectedCompChoices($selected_comp_choices)
    {
        $this->selected_comp_choices = $selected_comp_choices;
    }

    /**
     * @param array $selected_medium_choices
     */
    public function setSelectedMediumChoices($selected_medium_choices)
    {
        $this->selected_medium_choices = $selected_medium_choices;
    }
}
