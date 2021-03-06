<?php
namespace RpsCompetition\Frontend\Shortcodes\UploadImage;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;

/**
 * Class UploadImageServiceProvider
 *
 * @package   RpsCompetition\Frontend\Shortcodes\UploadImage
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class UploadImageServiceProvider extends ServiceProvider
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
        return ['UploadImageController'];
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // My Entries Shortcode
        $this->app->bind('UploadImageController',
            function(Application $app) {
                return new UploadImageController($app->make('ShortcodeView'),
                                                 $app->make('UploadImageModel'),
                                                 $app->make('Settings'));
            });

        $this->app->bind('UploadImageModel',
            function(Application $app) {
                return new UploadImageModel($app->make('formFactory'),
                                            $app->make('Settings'),
                                            $app->make('IlluminateRequest'));
            });
    }
}
