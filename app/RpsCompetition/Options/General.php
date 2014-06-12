<?php
namespace RpsCompetition\Options;

use Avh\Utility\OptionsAbstract;

// ---------- Private methods ----------
final class General extends OptionsAbstract

{

    /**
     * @var  string  option name
     */
    public $option_name = 'avh-rps';

    protected $defaults = array(
        'season_start_month_num' => 9,
        'season_end_month_num'   => 12
    );

    public function __construct()
    {
        parent::__construct();

        /* Clear the cache on update/add */
        add_action('add_option_' . $this->option_name, array('Avh\Utility\Common', 'clear_cache'));
        add_action('update_option_' . $this->option_name, array('Avh\Utility\Common', 'clear_cache'));
    }

// ---------- Public methods ----------
    /**
     * Concrete classes *may* contain a enrich_defaults method to add additional defaults once
     * all post_types and taxonomies have been registered
     */
    public function enrich_defaults()
    {
        // TODO: Implement enrich_defaults() method.
    }

    /**
     * Concrete classes *may* contain a translate_defaults method
     */
    public function translate_defaults()
    {
        // TODO: Implement translate_defaults() method.
    }

// ---------- Protected methods ----------
    /**
     * All concrete classes must contain a validate_option() method which validates all
     * values within the option
     */
    protected function validate_option($dirty, $clean, $old)
    {
        // TODO: Implement validate_option() method.
    }
}
