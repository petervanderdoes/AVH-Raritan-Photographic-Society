<?php
namespace RpsCompetition\Frontend\Requests\EditTitle;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;
use RpsCompetition\Form\Type\EditTitleType;

/**
 * Class RequestEditTitleServiceProvider
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\RequestEditTitle
 */
class RequestEditTitleServiceProvider extends ServiceProvider
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
        return ['RequestEditTitle'];
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('\RpsCompetition\Entity\Forms\EditTitle');
        $this->app->bind(
            '\RpsCompetition\Form\Type\EditTitleType',
            function (Application $app) {
                $entity = $app->make('\RpsCompetition\Entity\Forms\EditTitle');

                return new EditTitleType($entity);
            }
        )
        ;
        $this->app->bind('\RpsCompetition\Frontend\Requests\EditTitle\RequestMyTitleModel');
        $this->app->bind(
            'RequestEditTitle',
            function (Application $app) {
                return new RequestEditTitle(
                    $app->make('\RpsCompetition\Entity\Forms\EditTitle'),
                    $app->make('\RpsCompetition\Form\Type\EditTitleType'),
                    $app->make('\RpsCompetition\Frontend\Requests\EditTitle\RequestMyTitleModel'),
                    $app->make('QueryCompetitions'),
                    $app->make('QueryEntries'),
                    $app->make('PhotoHelper'),
                    $app->make('IlluminateRequest'),
                    $app->make('formFactory'),
                    $app->make('Session'),
                    $app->make('Settings')
                );
            }
        )
        ;
    }
}
