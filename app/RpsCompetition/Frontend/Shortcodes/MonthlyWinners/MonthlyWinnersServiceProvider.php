<?php
namespace RpsCompetition\Frontend\Shortcodes\MonthlyWinners;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class MonthlyWinnersServiceProvider
 *
 * @package   RpsCompetition\Frontend\Shortcodes\MonthlyWinners
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class MonthlyWinnersServiceProvider extends ServiceProvider
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
        return ['MonthlyWinnersController'];
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('MonthlyWinnersController',
            function(Application $app) {
                return new MonthlyWinnersController($app->make('ShortcodeView'),
                                                    $app->make('MonthlyWinnersModel'),
                                                    $app->make('Settings'));
            });

        $this->app->bind('MonthlyWinnersModel',
            function(Application $app) {
                return new MonthlyWinnersModel($app->make('Session'),
                                               $app->make('QueryCompetitions'),
                                               $app->make('QueryMiscellaneous'),
                                               $app->make('PhotoHelper'),
                                               $app->make('SeasonHelper'));
            });
    }
}
