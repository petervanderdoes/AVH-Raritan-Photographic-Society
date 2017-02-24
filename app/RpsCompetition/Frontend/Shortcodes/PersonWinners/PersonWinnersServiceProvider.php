<?php
namespace RpsCompetition\Frontend\Shortcodes\PersonWinners;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class PersonWinnersServiceProvider
 *
 * @package   RpsCompetition\Frontend\Shortcodes\PersonWinners
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class PersonWinnersServiceProvider extends ServiceProvider
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
        return ['PersonWinnersController'];
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // My Entries Shortcode
        $this->app->bind('PersonWinnersController',
            function(Application $app) {
                return new PersonWinnersController($app->make('ShortcodeView'), $app->make('PersonWinnersModel'));
            });

        $this->app->bind('PersonWinnersModel',
            function(Application $app) {
                return new PersonWinnersModel($app->make('QueryEntries'), $app->make('PhotoHelper'));
            });
    }
}
