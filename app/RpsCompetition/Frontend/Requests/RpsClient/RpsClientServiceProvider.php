<?php
namespace RpsCompetition\Frontend\Requests\RpsClient;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Api\Client;
use RpsCompetition\Api\Json;
use RpsCompetition\Application;

/**
 * Class RpsClientServiceProvider
 *
 * @package   RpsCompetition\Frontend\Requests\RpsClient
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class RpsClientServiceProvider extends ServiceProvider
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
        return ['RequestRpsClient'];
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('Json',
            function() {
                return new Json();
            });

        $this->app->bind('ApiClient',
            function(Application $app) {
                return new Client($app->make('PhotoHelper'), $app->make('Json'));
            });
        $this->app->bind('RequestRpsClient',
            function(Application $app) {
                return new RequestRpsClient($app->make('ApiClient'), $app->make('IlluminateRequest'));
            });
    }
}
