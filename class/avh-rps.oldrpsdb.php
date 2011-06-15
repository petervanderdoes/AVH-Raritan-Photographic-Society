<?php
if ( !defined( 'AVH_FRAMEWORK' ) ) die( 'You are not allowed to call this page directly.' );
class AVH_RPS_OldRpsDb
{

    private $_rpsdb;

    /**
     * PHP5 constructor
     *
     */
    public function __construct()
    {
        
        $this->_rpsdb = new wpdb('rarit0_data', 'rps', 'rarit0_data','localhost');
        $this->_rpsdb->show_errors();
    }

} //End Class AVH_RPS_OldRpsDb