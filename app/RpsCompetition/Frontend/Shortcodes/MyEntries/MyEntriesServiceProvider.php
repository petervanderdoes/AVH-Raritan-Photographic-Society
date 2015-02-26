<?php
namespace RpsCompetition\Frontend\Shortcodes\MyEntries;

/**
 * Class MyEntriesServiceProvider
 *
 * @package RpsCompetition\Frontend\Shortcodes\MyEntries
 */
class MyEntriesServiceProvider
{
    private $container;

    public function __construct($container) {
        $this->container = $container;
    }
    public function register()
    {
        // My Entries Shortcode
        $this->container->bind(
            'MyEntriesController',
            function ($app) {
                return new MyEntries(
                    $app->make('ShortcodeView'), $app->make('MyEntriesModel')
                );
            }
        )
        ;

        $this->container->bind(
            'MyEntriesModel',
            function ($app) {
                return new MyEntriesModel(
                    $app->make('QueryCompetitions'),
                    $app->make('QueryEntries'),
                    $app->make('QueryMiscellaneous'),
                    $app->make('PhotoHelper'),
                    $app->make('SeasonHelper'),
                    $app->make('CompetitionHelper'),
                    $app->make('Session'),
                    $app->make('formFactory'),
                    $app->make('Settings')
                );
            }
        )
        ;
    }
}
