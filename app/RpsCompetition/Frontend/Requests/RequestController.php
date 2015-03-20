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
    /** @var array */
    private $options;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->options = get_option('avh-rps');
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
        if (isset($wp_query->query['page_id'])) {
            if ($wp_query->query['page_id'] == $this->options['monthly_entries_post_id']) {
                /** @var \RpsCompetition\Frontend\Requests\ParseQuery\RequestMonthlyEntries $request */
                $request = $this->app->make('RequestMonthlyEntries');
                $request->handleRequestMonthlyEntries();
            }
            if ($wp_query->query['page_id'] == $this->options['monthly_winners_post_id']) {
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
            if ($post->ID == $this->options['my_digital_entries'] || $post->ID == $this->options['my_print_entries']) {
                /** @var \RpsCompetition\Frontend\Requests\MyEntries\RequestMyEntries $request */
                $request = $this->app->make('RequestMyEntries');
                $request->handleRequestMyEntries();
            }

            if ($post->ID == $this->options['banquet_entries']) {
                /** @var \RpsCompetition\Frontend\Requests\BanquetEntries\RequestBanquetEntries $request */
                $request = $this->app->make('RequestBanquetEntries');
                $request->handleBanquetEntries();
            }

            if ($post->ID == $this->options['edit_title']) {
                /** @var \RpsCompetition\Frontend\Requests\EditTitle\RequestEditTitle $request */
                $request = $this->app->make('RequestEditTitle');
                $request->handleRequestEditTitle();
            }

            if ($post->ID == $this->options['upload_image']) {
                /** @var \RpsCompetition\Frontend\Requests\UploadImage\RequestUploadImage $request */
                $request = $this->app->make('RequestUploadImage');
                $request->handleUploadImage();
            }
        }
    }

    public function handleTemplateRedirect(){
        if ($this->request->has('rpswinclient')) {
            $request = $this->app('RequestRpsClient');
            $request->handleRpsClient();

        }
    }
}
