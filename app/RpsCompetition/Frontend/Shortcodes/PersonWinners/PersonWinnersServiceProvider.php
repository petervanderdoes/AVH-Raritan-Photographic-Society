<?php
namespace RpsCompetition\Frontend\Shortcodes\PersonWinners;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class PersonWinnersServiceProvider
 *
 * @package RpsCompetition\Frontend\Shortcodes\PersonWinners
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
        return ['PersonWinners'];
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
            'PersonWinners',
            function (Application $app) {
                return new PersonWinners(
                    $app->make('ShortcodeView'), $app->make('PersonWinnersModel')
                );
            }
        )
        ;

        $this->app->bind(
            'PersonWinnersModel',
            function (Application $app) {
                return new PersonWinnersModel(
                    $app->make('QueryMiscellaneous'), $app->make('PhotoHelper')
                );
            }
        )
        ;
    }
}
