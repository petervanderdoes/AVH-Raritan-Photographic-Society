<?php
namespace RpsCompetition\Frontend\Requests\MyEntries;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;
use RpsCompetition\Form\Type\MyEntriesType;

/**
 * Class RequestMyEntriesServiceProvider
 *
 * @package   RpsCompetition\Frontend\Requests\MyEntries
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class RequestMyEntriesServiceProvider extends ServiceProvider
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
        return ['RequestMyEntries'];
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('\RpsCompetition\Entity\Form\MyEntries');
        $this->app->bind(
            '\RpsCompetition\Form\Type\MyEntriesType',
            function (Application $app) {
                $entity = $app->make('\RpsCompetition\Entity\Form\MyEntries');

                return new MyEntriesType($entity);
            }
        );
        $this->app->bind('\RpsCompetition\Frontend\Requests\MyEntries\RequestMyEntriesModel');
        $this->app->bind(
            'RequestMyEntries',
            function (Application $app) {
                return new RequestMyEntries(
                    $app->make('\RpsCompetition\Entity\Form\MyEntries'),
                    $app->make('\RpsCompetition\Form\Type\MyEntriesType'),
                    $app->make('\RpsCompetition\Frontend\Requests\MyEntries\RequestMyEntriesModel'),
                    $app->make('QueryCompetitions'),
                    $app->make('IlluminateRequest'),
                    $app->make('formFactory'),
                    $app->make('Session')
                );
            }
        );
    }
}
