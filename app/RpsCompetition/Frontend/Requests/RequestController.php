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
     * @see      FrontEnd::setupRequestsHandler
     */
    public function handleParseQuery($wp_query)
    {
        $options = get_option('avh-rps');
        if (isset($wp_query->query['page_id'])) {
            if ($wp_query->query['page_id'] == $options['monthly_entries_post_id']) {
                $this->handleRequestMonthlyEntries();
            }
            if ($wp_query->query['page_id'] == $options['monthly_winners_post_id']) {
                $this->handleRequestMonthlyWinners();
            }
        }
    }

    /**
     * Handle HTTP Requests
     *
     * @internal Hooks: wp
     * @see      FrontEnd::setupRequestsHandler
     */
    public function handleWp()
    {
        global $post;

        if (is_object($post)) {
            if ($post->ID == 56 || $post->ID == 58) {
                $this->handlePostRpsMyEntries();
            }

            if ($post->post_title == 'Banquet Entries') {
                $this->handlePostRpsBanquetEntries();
            }

            if ($post->ID == 75) {
                $this->handlePostEditTitle();
            }

            if ($post->ID == 89) {
                $this->handlePostUploadEntry();
            }
        }
    }
}
