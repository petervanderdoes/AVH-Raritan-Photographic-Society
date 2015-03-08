<?php
namespace RpsCompetition\Frontend\Shortcodes\CategoryWinners;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class CategoryWinnersServiceProvider
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\CategoryWinners
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
        )
        ;

        $this->app->bind(
            'CategoryWinnersModel',
            function (Application $app) {
                return new CategoryWinnersModel(
                    $app->make('QueryMiscellaneous'),
                    $app->make('PhotoHelper')
                );
            }
        )
        ;
    }
}
