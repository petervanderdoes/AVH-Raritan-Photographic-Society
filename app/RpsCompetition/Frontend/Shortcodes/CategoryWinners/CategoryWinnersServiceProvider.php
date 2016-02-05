<?php
namespace RpsCompetition\Frontend\Shortcodes\CategoryWinners;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class CategoryWinnersServiceProvider
 *
 * @package   RpsCompetition\Frontend\Shortcodes\CategoryWinners
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class CategoryWinnersServiceProvider extends ServiceProvider
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
        return ['CategoryWinnersController'];
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
            'CategoryWinnersController',
            function (Application $app) {
                return new CategoryWinnersController(
                    $app->make('ShortcodeView'), $app->make('CategoryWinnersModel'), $app->make('Settings')
                );
            }
        );

        $this->app->bind(
            'CategoryWinnersModel',
            function (Application $app) {
                return new CategoryWinnersModel(
                    $app->make('QueryMiscellaneous'), $app->make('PhotoHelper')
                );
            }
        );
    }
}
