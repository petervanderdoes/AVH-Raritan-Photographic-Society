<?php
namespace RpsCompetition\Frontend\Shortcodes\MyEntries;

use Illuminate\Support\ServiceProvider;

/**
 * Class MyEntriesServiceProvider
 *
 * @package RpsCompetition\Frontend\Shortcodes\MyEntries
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
        return ['MyEntries'];
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
            'MyEntries',
            function ($app) {
                return new MyEntries(
                    $app->make('ShortcodeView'), $app->make('MyEntriesModel')
                );
            }
        )
        ;

        $this->app->bind(
            'MyEntriesModel',
            function ($app) {
                return new MyEntriesModel(
                    $app->make('QueryCompetitions'),
                    $app->make('QueryEntries'),
                    $app->make('QueryMiscellaneous'),
                    $app->make('PhotoHelper'),
                    $app->make('SeasonHelper'),
                    $app->make('CompetitionHelper'),
                    $app->make('Session'),
                    $app->make('formFactory'),
                    $app->make('Settings')
                );
            }
        )
        ;
    }
}
