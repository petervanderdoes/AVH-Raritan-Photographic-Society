<?php

namespace RpsCompetition\Entity\Db;

/**
 * Class Competition
 *
 * @package   RpsCompetition\Entity\Db
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2016-2016, Peter van der Does
 */
class Competition
{
    /** @var string */
    public $Classification;
    /** @var string */
    public $Close_Date;
    /** @var string */
    public $Closed;
    /** @var string */
    public $Competition_Date;
    /** @var string */
    public $Date_Created;
    /** @var string */
    public $Date_Modified;
    /** @var int */
    public $ID;
    /** @var string */
    public $Image_Size;
    /** @var int */
    public $Max_Entries;
    /** @var string */
    public $Medium;
    /** @var int */
    public $Num_Judges;
    /** @var string */
    public $Scored;
    /** @var string */
    public $Special_Event;
    /** @var string */
    public $Theme;

    /**
     * Map array to entity.
     *
     * @param $record
     */
    public function map($record)
    {
        foreach ($record as $key => $value) {
            $this->$key = $value;
        }
    }
}
