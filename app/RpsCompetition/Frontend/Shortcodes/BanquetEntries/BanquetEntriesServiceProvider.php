<?php
namespace RpsCompetition\Frontend\Shortcodes\BanquetEntries;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;
use RpsCompetition\Db\QueryBanquet;

/**
 * Class BanquetEntriesServiceProvider
 *
 * @package   RpsCompetition\Frontend\Shortcodes\BanquetEntries
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class BanquetEntriesServiceProvider extends ServiceProvider
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
        return ['BanquetEntriesController'];
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // My Entries Shortcode
        $this->app->bind('BanquetEntriesController',
            function(Application $app) {
                return new BanquetEntriesController($app->make('ShortcodeView'), $app->make('BanquetEntriesModel'));
            });

        $this->app->bind('QueryBanquet',
            function(Application $app) {
                return new QueryBanquet($app->make('RpsDb'));
            });
        $this->app->bind('BanquetEntriesModel',
            function(Application $app) {
                return new BanquetEntriesModel($app->make('formFactory'),
                                               $app->make('SeasonHelper'),
                                               $app->make('QueryMiscellaneous'),
                                               $app->make('QueryBanquet'),
                                               $app->make('QueryEntries'),
                                               $app->make('IlluminateRequest'),
                                               $app->make('Session'));
            });
    }
}
