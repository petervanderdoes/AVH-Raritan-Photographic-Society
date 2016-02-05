<?php
namespace RpsCompetition\Frontend\Requests\ParseQuery;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class ParseQueryServiceProvider
 *
 * @package   RpsCompetition\Frontend\Requests\ParseQuery
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
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
            '\RpsCompetition\Frontend\Requests\ParseQuery\ParseQueryHelper',
            function (Application $app) {
                return new ParseQueryHelper(
                    $app->make('QueryCompetitions'), $app->make('SeasonHelper'), $app->make('CompetitionHelper')
                );
            }
        );
        $this->app->bind(
            'RequestMonthlyEntries',
            function (Application $app) {
                return new RequestMonthlyEntries(
                    $app->make('\RpsCompetition\Frontend\Requests\ParseQuery\ParseQueryHelper'),
                    $app->make('QueryCompetitions'),
                    $app->make('SeasonHelper'),
                    $app->make('IlluminateRequest'),
                    $app->make('Session')
                );
            }
        );
        $this->app->bind(
            'RequestMonthlyWinners',
            function (Application $app) {
                return new RequestMonthlyWinners(
                    $app->make('\RpsCompetition\Frontend\Requests\ParseQuery\ParseQueryHelper'),
                    $app->make('QueryCompetitions'),
                    $app->make('SeasonHelper'),
                    $app->make('IlluminateRequest'),
                    $app->make('Session')
                );
            }
        );
    }
}
