<?php

namespace RpsCompetition\Frontend\Requests\RpsClient;

use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Api\Client;

/**
 * Class RequestRpsClient
 *
 * @package   RpsCompetition\Frontend\Requests\RpsClient
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class RequestRpsClient
{
    private $client;
    private $request;

    /**
     * @param Client            $client
     * @param IlluminateRequest $request
     */
    public function __construct(Client $client, IlluminateRequest $request)
    {

        $this->client = $client;
        $this->request = $request;
    }

    /**
     * Handles the requests by the RPS Windows Client
     *
     * @internal Hook: template_redirect
     */
    public function handleRpsClient()
    {

        $this->disableCachePlugins();

        status_header(200);
        switch ($this->request->input('rpswinclient')) {
            case 'getcompdate':
                $this->client->sendCompetitionDates($this->request);
                die();
            case 'download':
                $this->client->sendCompetitions($this->request);
                die();
            case 'uploadscore':
                $this->client->receiveScores($this->request);
                die();
            default:
                break;
        }
    }

    /**
     * Disable able all known WordPress cache plugins.
     */
    private function disableCachePlugins()
    {
        define('DONOTCACHEPAGE', true);
        global $hyper_cache_stop;
        $hyper_cache_stop = true;
        add_filter('w3tc_can_print_comment', '__return_false');
    }
}
