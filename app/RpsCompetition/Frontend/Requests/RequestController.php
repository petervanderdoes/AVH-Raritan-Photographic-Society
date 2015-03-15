<?php

namespace RpsCompetition\Frontend\Requests;

use RpsCompetition\Application;

/**
 * Class RequestController
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Requests
 */
class RequestController
{
    private $app;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app) {
        $this->app = $app;
    }

    /**
     * Handle HTTP Requests.
     *
     * @param $wp_query
     *
     * @internal Hook: parse_query
     * @see      FrontEnd::setupRequestHandling
     */
    public function handleParseQuery($wp_query)
    {
        $options = get_option('avh-rps');
        if (isset($wp_query->query['page_id'])) {
            if ($wp_query->query['page_id'] == $options['monthly_entries_post_id']) {
                /** @var \RpsCompetition\Frontend\Requests\ParseQuery\RequestMonthlyEntries $request */
                $request = $this->app->make('RequestMonthlyEntries');
                $request->handleRequestMonthlyEntries();
            }
            if ($wp_query->query['page_id'] == $options['monthly_winners_post_id']) {
                /** @var \RpsCompetition\Frontend\Requests\ParseQuery\RequestMonthlyWinners $request */
                $request = $this->app->make('RequestMonthlyWinners');
                $request->handleRequestMonthlyWinners();
            }
        }
    }

    /**
     * Handle HTTP Requests
     *
     * @internal Hooks: wp
     * @see      FrontEnd::setupRequestHandling
     */
    public function handleWp()
    {
        global $post;

        if (is_object($post)) {
            if ($post->ID == 56 || $post->ID == 58) {
                /** @var \RpsCompetition\Frontend\Requests\MyEntries\RequestMyEntries $request */
                $request = $this->app->make('RequestMyEntries');
                $request->handleRequestMyEntries();
            }

            if ($post->post_title == 'Banquet Entries') {
                /** @var \RpsCompetition\Frontend\Requests\BanquetEntries\RequestBanquetEntries $request */
                $request = $this->app->make('RequestBanquetEntries');
                $request->handleBanquetEntries();
            }

            if ($post->ID == 75) {
                /** @var \RpsCompetition\Frontend\Requests\EditTitle\RequestEditTitle $request */
                $request = $this->app->make('RequestEditTitle');
                $request->handleRequestEditTitle();
            }

            if ($post->ID == 89) {
                /** @var \RpsCompetition\Frontend\Requests\UploadImage\RequestUploadImage $request */
                $request = $this->app->make('RequestUploadImage');
                $request->handleUploadImage();
            }
        }
    }
}
