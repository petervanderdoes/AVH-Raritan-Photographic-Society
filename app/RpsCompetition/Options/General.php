<?php
namespace RpsCompetition\Options;

use Avh\Framework\Utility\Common;
use Avh\Framework\Utility\OptionsAbstract;
use RpsCompetition\Helpers\ImageSizeHelper;

/**
 * Class General
 *
 * @package   RpsCompetition\Options
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 * @todo      Make the options avaiable through the admin interface
 */
final class General extends OptionsAbstract
{
    /**
     * @var  string  option name
     */
    public    $option_name = 'avh-rps';
    protected $defaults    = [
        'season_start_month_num'  => 9,
        'season_end_month_num'    => 12,
        'monthly_entries_post_id' => 1005,
        'monthly_winners_post_id' => 61,
        'members_page'            => 54,
        'my_digital_entries'      => 56,
        'my_print_entries'        => 58,
        'banquet_entries'         => 984,
        'edit_title'              => 75,
        'upload_image'            => 89,
        'db_version'              => 0,
        'default_image_size'      => '1400'
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        /* Clear the cache on update/add */
        add_action('add_option_' . $this->option_name, ['Avh\Framework\Utility\Common', 'clearCache']);
        add_action('update_option_' . $this->option_name, ['Avh\Framework\Utility\Common', 'clearCache']);
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
     *  Validate all values within the option
     *
     * @param array $dirty
     * @param array $clean
     * @param array $old
     *
     * @return array
     */
    protected function validateOption($dirty, $clean, $old)
    {
        $keys = array_keys($this->defaults);
        foreach ($keys as $key) {
            switch ($key) {
                case 'season_start_month_num':
                case 'season_end_month_num':
                case 'monthly_entries_post_id':
                case 'monthly_winners_post_id':
                case 'members_page':
                case 'my_digital_entries':
                case 'my_print_entries':
                case 'banquet_entries':
                case 'edit_title':
                case 'upload_image':
                case 'db_version':
                    $clean[$key] = filter_var($dirty[$key], FILTER_VALIDATE_INT);
                    break;

                case 'default_image_size':
                    if (ImageSizeHelper::isImageSize($dirty[$key])) {
                        $clean[$key] = $dirty[$key];
                    }
                    break;
                /**
                 * Boolean  fields
                 */
                default:
                    wp_die(
                        'Error while updating option. Probably missing validation. See \RpsCompetition\Options\General validateOption'
                    );
                    break;
            }
        }

        return $clean;
    }
}
