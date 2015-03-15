<?php
namespace RpsCompetition\Frontend\Requests\BanquetEntries;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;
use RpsCompetition\Form\Type\BanquetCurrentUserType as BanquetEntriesType;

/**
 * Class RequestBanquetEntriesServiceProvider
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\RequestBanquetEntries
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

        $this->app->singleton('\RpsCompetition\Entity\Forms\BanquetEntries');
        $this->app->bind(
            '\RpsCompetition\Form\Type\BanquetEntriesType',
            function (Application $app) {
                $entity = $app->make('\RpsCompetition\Entity\Forms\BanquetEntries');

                return new BanquetEntriesType($entity);
            }
        )
        ;
        $this->app->bind(
            '\RpsCompetition\Frontend\Requests\BanquetEntries\RequestBanquetEntriesModel',
            function (Application $app) {
                return new RequestBanquetEntriesModel(
                    $app->make('\RpsCompetition\Entity\Forms\BanquetEntries'),
                    $app->make('IlluminateRequest'),
                    $app->make('QueryEntries'),
                    $app->make('QueryCompetitions'),
                    $app->make('PhotoHelper')
                );
            }
        )
        ;
        $this->app->bind(
            'RequestBanquetEntries',
            function (Application $app) {
                return new RequestBanquetEntries(
                    $app->make('\RpsCompetition\Entity\Forms\BanquetEntries'),
                    $app->make('\RpsCompetition\Form\Type\BanquetEntriesType'),
                    $app->make('\RpsCompetition\Frontend\Requests\BanquetEntries\RequestBanquetEntriesModel'),
                    $app->make('IlluminateRequest'),
                    $app->make('formFactory')
                );
            }
        )
        ;
    }
}
