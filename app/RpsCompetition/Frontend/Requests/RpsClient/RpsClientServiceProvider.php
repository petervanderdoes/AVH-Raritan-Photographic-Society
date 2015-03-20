<?php
namespace RpsCompetition\Frontend\Requests\RpsClient;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class RpsClientServiceProvider
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\RequestRpsClient
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

        $this->app->bind('\RpsCompetition\Api\Client');
        $this->app->bind(
            'RequestRpsClient',
            function (Application $app) {
                return new RequestRpsClient(
                    $app->make('\RpsCompetition\Api\Client'), $app->make('IlluminateRequest')
                );
            }
        )
        ;
    }
}
