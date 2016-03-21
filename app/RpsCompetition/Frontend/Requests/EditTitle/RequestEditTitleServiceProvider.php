<?php
namespace RpsCompetition\Frontend\Requests\EditTitle;

use Illuminate\Support\ServiceProvider;
use RpsCompetition\Application;
use RpsCompetition\Form\Type\EditTitleType;

/**
 * Class RequestEditTitleServiceProvider
 *
 * @package   RpsCompetition\Frontend\Requests\EditTitle
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
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

        $this->app->singleton('\RpsCompetition\Entity\Form\EditTitle');
        $this->app->bind('\RpsCompetition\Form\Type\EditTitleType',
            function(Application $app) {
                $entity = $app->make('\RpsCompetition\Entity\Form\EditTitle');

                return new EditTitleType($entity);
            });
        $this->app->bind('\RpsCompetition\Frontend\Requests\EditTitle\RequestEditTitleModel',
            function(Application $app) {
                return new RequestEditTitleModel($app->make('\RpsCompetition\Entity\Form\EditTitle'),
                                                 $app->make('QueryCompetitions'),
                                                 $app->make('QueryEntries'),
                                                 $app->make('PhotoHelper'),
                                                 $app->make('IlluminateRequest'));
            });
        $this->app->bind('RequestEditTitle',
            function(Application $app) {
                return new RequestEditTitle($app->make('\RpsCompetition\Entity\Form\EditTitle'),
                                            $app->make('\RpsCompetition\Form\Type\EditTitleType'),
                                            $app->make('\RpsCompetition\Frontend\Requests\EditTitle\RequestEditTitleModel'),
                                            $app->make('IlluminateRequest'),
                                            $app->make('formFactory'),
                                            $app->make('Settings'));
            });
    }
}
