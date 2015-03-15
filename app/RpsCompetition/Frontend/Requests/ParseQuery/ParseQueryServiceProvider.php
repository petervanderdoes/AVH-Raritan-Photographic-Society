<?php
namespace RpsCompetition\Frontend\Requests\ParseQuery;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class ParseQueryServiceProvider
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\ParseQuery
 */
class ParseQueryServiceProvider extends ServiceProvider
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
        return ['RequestMonthlyEntries', 'RequestMonthlyWinners'];
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

        $this->app->bind(
            'RequestMonthlyEntries',
            function (Application $app) {
                return new RequestMonthlyEntries(
                    $app->make('QueryCompetitions'),
                    $app->make('SeasonHelper'),
                    $app->make('CompetitionHelper'),
                    $app->make('IlluminateRequest'),
                    $app->make('Session')
                );
            }
        )
        ;
        $this->app->bind(
            'RequestMonthlyWinners',
            function (Application $app) {
                return new RequestMonthlyWinners(
                    $app->make('QueryCompetitions'),
                    $app->make('SeasonHelper'),
                    $app->make('CompetitionHelper'),
                    $app->make('IlluminateRequest'),
                    $app->make('Session')
                );
            }
        )
        ;
    }
}
