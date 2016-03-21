<?php
namespace RpsCompetition\Frontend\Shortcodes\MyEntries;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class MyEntriesServiceProvider
 *
 * @package   RpsCompetition\Frontend\Shortcodes\MyEntries
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class MyEntriesServiceProvider extends ServiceProvider
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
        return ['MyEntriesController'];
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // My Entries Shortcode
        $this->app->bind('MyEntriesController',
            function (Application $app) {
                return new MyEntriesController($app->make('ShortcodeView'), $app->make('MyEntriesModel'));
            });

        $this->app->bind('MyEntriesModel',
            function (Application $app) {
                return new MyEntriesModel($app->make('QueryCompetitions'),
                                          $app->make('QueryEntries'),
                                          $app->make('PhotoHelper'),
                                          $app->make('CompetitionHelper'),
                                          $app->make('Session'),
                                          $app->make('formFactory'),
                                          $app->make('Settings'),
                                          $app->make('IlluminateRequest'));
            });
    }
}
