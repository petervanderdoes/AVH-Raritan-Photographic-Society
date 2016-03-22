<?php
/**
 * Copyright 2016 Peter van der Does
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace RpsCompetition\Entity\Db;

/**
 * Class Entry
 *
 * @package   RpsCompetition\Entity\Db
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2016-2016, Peter van der Does
 */
class Entry
{
    /** @var string */
    public $Award;
    /** @var string */
    public $Client_File_Name;
    /** @var int */
    public $Competition_ID;
    /** @var string */
    public $Date_Created;
    /** @var string */
    public $Date_Modified;
    /** @var int */
    public $ID;
    /** @var int */
    public $Member_ID;
    /** @var float */
    public $Score;
    /** @var string */
    public $Server_File_Name;
    /** @var string */
    public $Title;

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
