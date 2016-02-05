<?php
namespace RpsCompetition\Frontend\Requests\BanquetEntries;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;
use RpsCompetition\Form\Type\BanquetEntriesType as BanquetEntriesType;

/**
 * Class RequestBanquetEntriesServiceProvider
 *
 * @package   RpsCompetition\Frontend\Requests\BanquetEntries
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class RequestBanquetEntriesServiceProvider extends ServiceProvider
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
        return ['RequestBanquetEntries'];
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('\RpsCompetition\Entity\Form\BanquetEntries');
        $this->app->bind(
            '\RpsCompetition\Form\Type\BanquetEntriesType',
            function (Application $app) {
                $entity = $app->make('\RpsCompetition\Entity\Form\BanquetEntries');

                return new BanquetEntriesType($entity);
            }
        );
        $this->app->bind(
            '\RpsCompetition\Frontend\Requests\BanquetEntries\RequestBanquetEntriesModel',
            function (Application $app) {
                return new RequestBanquetEntriesModel(
                    $app->make('\RpsCompetition\Entity\Form\BanquetEntries'),
                    $app->make('IlluminateRequest'),
                    $app->make('QueryEntries'),
                    $app->make('QueryCompetitions'),
                    $app->make('PhotoHelper'),
                    $app->make('Session')
                );
            }
        );
        $this->app->bind(
            'RequestBanquetEntries',
            function (Application $app) {
                return new RequestBanquetEntries(
                    $app->make('\RpsCompetition\Entity\Form\BanquetEntries'),
                    $app->make('\RpsCompetition\Form\Type\BanquetEntriesType'),
                    $app->make('\RpsCompetition\Frontend\Requests\BanquetEntries\RequestBanquetEntriesModel'),
                    $app->make('IlluminateRequest'),
                    $app->make('formFactory')
                );
            }
        );
    }
}
