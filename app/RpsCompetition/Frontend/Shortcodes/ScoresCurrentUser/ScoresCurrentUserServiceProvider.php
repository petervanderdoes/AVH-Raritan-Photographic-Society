<?php
namespace RpsCompetition\Frontend\Shortcodes\ScoresCurrentUser;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class ScoresCurrentUserServiceProvider
 *
 * @package   RpsCompetition\Frontend\Shortcodes\ScoresCurrentUser
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class ScoresCurrentUserServiceProvider extends ServiceProvider
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
        return ['ScoresCurrentUserController'];
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
            'ScoresCurrentUserController',
            function (Application $app) {
                return new ScoresCurrentUserController(
                    $app->make('ShortcodeView'), $app->make('ScoresCurrentUserModel')
                );
            }
        );

        $this->app->bind(
            'ScoresCurrentUserModel',
            function (Application $app) {
                return new ScoresCurrentUserModel(
                    $app->make('formFactory'),
                    $app->make('QueryMiscellaneous'),
                    $app->make('SeasonHelper'),
                    $app->make('IlluminateRequest')
                );
            }
        );
    }
}
