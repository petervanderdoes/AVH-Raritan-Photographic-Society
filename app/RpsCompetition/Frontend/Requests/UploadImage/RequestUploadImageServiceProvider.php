<?php
namespace RpsCompetition\Frontend\Requests\UploadImage;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;
use RpsCompetition\Form\Type\UploadImageType;

/**
 * Class RequestUploadImageServiceProvider
 *
 * @package   RpsCompetition\Frontend\Requests\UploadImage
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class RequestUploadImageServiceProvider extends ServiceProvider
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
        return ['RequestUploadImage'];
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('\RpsCompetition\Entity\Form\UploadImage');
        $this->app->bind(
            '\RpsCompetition\Form\Type\UploadImageType',
            function (Application $app) {
                $entity = $app->make('\RpsCompetition\Entity\Form\UploadImage');

                return new UploadImageType($entity);
            }
        );
        $this->app->bind(
            '\RpsCompetition\Frontend\Requests\UploadImage\RequestUploadImageModel',
            function (Application $app) {
                return new RequestUploadImageModel(
                    $app->make('\RpsCompetition\Entity\Form\UploadImage'),
                    $app->make('Session'),
                    $app->make('IlluminateRequest'),
                    $app->make('Settings'),
                    $app->make('QueryCompetitions'),
                    $app->make('QueryEntries'),
                    $app->make('PhotoHelper')

                );
            }
        );
        $this->app->bind(
            'RequestUploadImage',
            function (Application $app) {
                return new RequestUploadImage(
                    $app->make('\RpsCompetition\Entity\Form\UploadImage'),
                    $app->make('\RpsCompetition\Form\Type\UploadImageType'),
                    $app->make('\RpsCompetition\Frontend\Requests\UploadImage\RequestUploadImageModel'),
                    $app->make('IlluminateRequest'),
                    $app->make('formFactory'),
                    $app->make('Settings')
                );
            }
        );
    }
}
