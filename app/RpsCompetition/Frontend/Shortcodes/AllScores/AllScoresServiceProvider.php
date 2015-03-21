<?php

namespace RpsCompetition\Frontend\Shortcodes\AllScores;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class AllScoresServiceProvider
 *
 * @package   RpsCompetition\Frontend\Shortcodes\AllScores
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class AllScoresServiceProvider extends ServiceProvider
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
        return ['AllScoresController'];
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            'AllScoresController',
            function (Application $app) {
                return new AllScoresController(
                    $app->make('ShortcodeView'), $app->make('AllScoresModel')
                );
            }
        )
        ;

        $this->app->bind(
            'AllScoresModel',
            function (Application $app) {
                return new AllScoresModel(
                    $app->make('QueryCompetitions'),
                    $app->make('QueryMiscellaneous'),
                    $app->make('SeasonHelper'),
                    $app->make('IlluminateRequest'),
                    $app->make('formFactory')
                );
            }
        )
        ;
    }
}
