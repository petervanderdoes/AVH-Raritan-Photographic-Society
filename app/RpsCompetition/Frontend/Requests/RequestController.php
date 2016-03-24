<?php

namespace RpsCompetition\Frontend\Requests;

use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Application;

/**
 * Class RequestController
 *
 * @package   RpsCompetition\Frontend\Requests
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class RequestController
{
    private $app;
    /** @var array */
    private $options;
    /** @var IlluminateRequest */
    private $request;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->options = get_option('avh-rps');
        $this->request = $app->make('IlluminateRequest');
    }

    /**
     * Handle HTTP Requests.
     *
     * @param \stdClass|\WP_Query $wp_query
     *
     * @internal Hook: parse_query
     * @see      FrontEnd::setupRequestHandling
     */
    public function handleParseQuery(\stdClass $wp_query)
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
     * Handle the request redirection for the RPS Competition Client
     */
    public function handleTemplateRedirect()
    {
        if ($this->request->has('rpswinclient')) {
            $request = $this->app->make('RequestRpsClient');
            $request->handleRpsClient();
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
}
