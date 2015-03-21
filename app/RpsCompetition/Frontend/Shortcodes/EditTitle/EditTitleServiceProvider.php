<?php
namespace RpsCompetition\Frontend\Shortcodes\EditTitle;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class EditTitleServiceProvider
 *
 * @package   RpsCompetition\Frontend\Shortcodes\EditTitle
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class EditTitleServiceProvider extends ServiceProvider
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
        return ['EditTitleController'];
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
            'EditTitleController',
            function (Application $app) {
                return new EditTitleController(
                    $app->make('ShortcodeView'), $app->make('EditTitleModel'), $app->make('Settings')
                );
            }
        )
        ;

        $this->app->bind(
            'EditTitleModel',
            function (Application $app) {
                return new EditTitleModel(
                    $app->make('QueryEntries'),
                    $app->make('PhotoHelper'),
                    $app->make('formFactory'),
                    $app->make('Settings'),
                    $app->make('IlluminateRequest')
                );
            }
        )
        ;
    }
}
