<?php
namespace RpsCompetition\Options;

use Avh\Utility\Options;

final class General extends Options

{
    protected $defaults = array(
            'season_start_month_num' => 9,
            'season_end_month_num' => 12
        );

    public function __construct($settings)
    {
        parent::__construct($settings);
        $this->load('rps',$this->defaults);
    }
}
