<?php
namespace RpsCompetition\Frontend\Shortcodes\BanquetCurrentUser;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;
use RpsCompetition\Db\QueryBanquet;

/**
 * Class BanquetCurrentUserServiceProvider
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\BanquetCurrentUser
 */
class BanquetCurrentUserServiceProvider extends ServiceProvider
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
        return ['BanquetCurrentUserController'];
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
            'BanquetCurrentUserController',
            function (Application $app) {
                return new BanquetCurrentUserController(
                    $app->make('ShortcodeView'), $app->make('BanquetCurrentUserModel'), $app->make('Settings')
                );
            }
        )
        ;

        $this->app->bind(
            'QueryBanquet',
            function (Application $app) {
                return new QueryBanquet(
                    $app->make('RpsDb')
                );
            }
        )
        ;
        $this->app->bind(
            'BanquetCurrentUserModel',
            function (Application $app) {
                return new BanquetCurrentUserModel(
                    $app->make('formFactory'),
                    $app->make('SeasonHelper'),
                    $app->make('QueryMiscellaneous'),
                    $app->make('QueryBanquet'),
                    $app->make('QueryEntries'),
                    $app->make('IlluminateRequest'),
                    $app->make('Session')
                );
            }
        )
        ;
    }
}
