<?php

namespace RpsCompetition\Frontend\Requests\RpsClient;

use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Api\Client;

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
                $this->client->sendXmlCompetitionDates($this->request);
                break;
            case 'download':
                $this->client->sendCompetitions($this->request);
                break;
            case 'uploadscore':
                $this->client->doUploadScore($this->request);
                break;
            default:
                break;
        }
    }

    /**
     * Disable able all known WordPress cache plugins.
     *
     */
    private function disableCachePlugins()
    {
        define('DONOTCACHEPAGE', true);
        global $hyper_cache_stop;
        $hyper_cache_stop = true;
        add_filter('w3tc_can_print_comment', '__return_false');
    }
}
