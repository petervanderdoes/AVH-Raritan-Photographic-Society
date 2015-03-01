<?php
namespace RpsCompetition\Frontend\Shortcodes\MonthlyWinners;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class MonthlyWinnersServiceProvider
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\MonthlyWinners
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
        return ['MonthlyWinners'];
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            'MonthlyWinners',
            function (Application $app) {
                return new MonthlyWinners(
                    $app->make('ShortcodeView'), $app->make('MonthlyWinnersModel'), $app->make('Settings')
                );
            }
        )
        ;

        $this->app->bind(
            'MonthlyWinnersModel',
            function (Application $app) {
                return new MonthlyWinnersModel(
                    $app->make('Session'),
                    $app->make('QueryCompetitions'),
                    $app->make('QueryMiscellaneous'),
                    $app->make('PhotoHelper'),
                    $app->make('SeasonHelper')
                );
            }
        )
        ;
    }
}
