<?php

namespace RpsCompetition\Db;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class DbServiceProvider
 *
 * @package   RpsCompetition\Db
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class DbServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['QueryEntries', 'QueryCompetitions', 'QueryMiscellaneous', 'QueryBanquet'];
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('RpsDb', 'RpsCompetition\Db\RpsDb');

        $this->app->bind(
            'QueryEntries',
            function (Application $app) {
                return new QueryEntries($app->make('RpsDb'));
            }
        );
        $this->app->bind(
            'QueryCompetitions',
            function (Application $app) {
                return new QueryCompetitions($app->make('Settings'), $app->make('RpsDb'));
            }
        );
        $this->app->bind(
            'QueryMiscellaneous',
            function (Application $app) {
                return new QueryMiscellaneous($app->make('RpsDb'));
            }
        );
        $this->app->bind(
            'QueryBanquet',
            function (Application $app) {
                return new QueryBanquet($app->make('RpsDb'));
            }
        );
    }
}
