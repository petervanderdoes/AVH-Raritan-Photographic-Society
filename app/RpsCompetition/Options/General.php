<?php
namespace RpsCompetition\Options;

use Avh\Utility\OptionsAbstract;

final class General extends OptionsAbstract
{
    /**
     * @var  string  option name
     */
    public $option_name = 'avh-rps';
    protected $defaults = array(
        'season_start_month_num'  => 9,
        'season_end_month_num'    => 12,
        'monthly_entries_post_id' => 1005,
        'monthly_winners_post_id' => 61
    );

    public function __construct()
    {
        parent::__construct();

        /* Clear the cache on update/add */
        add_action('add_option_' . $this->option_name, array('Avh\Utility\Common', 'clearCache'));
        add_action('update_option_' . $this->option_name, array('Avh\Utility\Common', 'clearCache'));
    }

    /**
     * Clean out old/renamed values within the option
     *
     * @param mixed $option_value
     * @param mixed $current_version
     * @param mixed $all_old_option_values
     *
     * @return mixed|void
     */
    public function cleanOption($option_value, $current_version = null, $all_old_option_values = null)
    {
        // TODO: Implement cleanOption() method.
    }

    /**
     * Add additional defaults once all post_types and taxonomies have been registered
     */
    public function handleEnrichDefaults()
    {
        // TODO: Implement handleEnrichDefaults() method.
    }

    /**
     * Translate default values if needed.
     */
    public function handleTranslateDefaults()
    {
        // TODO: Implement handleTranslateDefaults() method.
    }

    /**
     * Validate all values within the option
     *
     * @param mixed $dirty
     * @param mixed $clean
     * @param mixed $old
     */
    protected function validateOption($dirty, $clean, $old)
    {
        // TODO: Implement validateOption() method.
    }
}
