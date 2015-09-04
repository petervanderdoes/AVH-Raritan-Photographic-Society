<?php
namespace RpsCompetition\Frontend\Shortcodes\MonthlyEntries;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class MonthlyEntriesServiceProvider
 *
 * @package   RpsCompetition\Frontend\Shortcodes\MonthlyEntries
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class MonthlyEntriesServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['MonthlyEntriesController'];
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // My Entries Shortcode
        $this->app->bind(
            'MonthlyEntriesController',
            function (Application $app) {
                return new MonthlyEntriesController(
                    $app->make('ShortcodeView'), $app->make('MonthlyEntriesModel'), $app->make('Settings')
                );
            }
        );

        $this->app->bind(
            'MonthlyEntriesModel',
            function (Application $app) {
                return new MonthlyEntriesModel(
                    $app->make('Session'),
                    $app->make('QueryCompetitions'),
                    $app->make('QueryMiscellaneous'),
                    $app->make('PhotoHelper'),
                    $app->make('SeasonHelper')
                );
            }
        );
    }
}
