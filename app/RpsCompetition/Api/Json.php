<?php

namespace RpsCompetition\Api;

/**
 * Class Json
 *
 * @package   RpsCompetition\Api
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2015, AVH Software
 */
class Json
{
    const CONTENT_TYPE_DEBUG = 'application/json';
    /**
     * content type headers
     */
    const CONTENT_TYPE_OFFICIAL = 'application/vnd.api+json';
    const ENCODE_DEBUG          = 448;
    /**
     * json encode options
     * default is JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
     * in debug mode (@see ::$debug) JSON_PRETTY_PRINT is added
     */
    const ENCODE_DEFAULT               = 320;
    const STATUS_BAD_REQUEST           = 400;
    const STATUS_CREATED               = 201;
    const STATUS_FORBIDDEN             = 403;
    const STATUS_INTERNAL_SERVER_ERROR = 500;
    const STATUS_METHOD_NOT_ALLOWED    = 405;
    const STATUS_NOT_FOUND             = 404;
    const STATUS_NOT_MODIFIED          = 304;
    const STATUS_NO_CONTENT            = 204;
    /**
     * advised http status codes
     */
    const STATUS_OK                   = 200;
    const STATUS_PERMANENT_REDIRECT   = 308;
    const STATUS_SERVICE_UNAVAILABLE  = 503;
    const STATUS_TEMPORARY_REDIRECT   = 307;
    const STATUS_UNAUTHORIZED         = 401;
    const STATUS_UNPROCESSABLE_ENTITY = 422;
    /**
     * whether or not ->send_response() sends out basic status headers
     * if set to true, it sends the status code and the location header
     */
    public static $send_status_headers = true;
    protected     $http_status         = self::STATUS_OK;
    protected     $included_resources  = [];
    /**
     * internal data containers
     */
    protected $links             = [];
    protected $meta_data         = [];
    protected $redirect_location = null;
    private   $data              = [];
    private   $errors            = [];

    public function __construct()
    {
    }

    /**
     * Add error message
     *
     * @param string $error_message
     */
    public function addError($error_message)
    {
        $error_detail['detail'] = $error_message;
        $this->errors[] = $error_detail;
    }

    /**
     * Add data resource
     *
     * @param string $resource_key
     * @param mixed  $resource_value
     */
    public function addResource($resource_key, $resource_value)
    {
        $this->data[$resource_key] = $resource_value;
    }

    /**
     * Generate an array for the whole response body.
     *
     * @return array
     */
    private function get_array()
    {
        $response = [];
        $response['data'] = $this->data;
        if ($this->errors !== []) {
            $response['errors'] = $this->errors;
        }

        return $response;
    }

    /**
     * sends out the json response to the browser
     * this will fetch the response from ->get_json() if not given via $response
     *
     * @note this also sets the needed http headers (status, location and content-type)
     *
     * @param  string $content_type   optional, defaults to ::CONTENT_TYPE_OFFICIAL (the official IANA registered one)
     *                                ..
     *                                .. or to ::CONTENT_TYPE_DEBUG, @see ::$debug
     * @param  int    $encode_options optional, $options for json_encode()
     *                                defaults to ::ENCODE_DEFAULT or ::ENCODE_DEBUG, @see ::$debug
     * @param  json   $response       optional, defaults to ::get_json()
     *
     * @return void                   however, a string will be echo'd to the browser
     */
    public function send_response($content_type = null, $encode_options = null, $response = null)
    {
        if (is_null($response) && $this->http_status != self::STATUS_NO_CONTENT) {
            $response = $this->get_json($encode_options);
        }

        if (empty($content_type)) {
            $content_type = self::CONTENT_TYPE_OFFICIAL;
        }

        if (self::$send_status_headers) {
            $this->send_status_headers();
        }

        header('Content-Type: ' . $content_type . '; charset=utf-8');

        if ($this->http_status == self::STATUS_NO_CONTENT) {
            return;
        }

        echo $response;
        die();
    }

    /**
     * sets the http status code for this response
     *
     * @param int $http_status any will do, you can easily pass one of the predefined ones in ::STATUS_*
     */
    public function set_http_status($http_status)
    {
        $this->http_status = $http_status;
    }

    /**
     * returns the whole response body as json
     * it generates the response via ->get_array()
     *
     * @see ->get_array() for the structure
     * @see json_encode() options
     *
     * @param  int $encode_options  optional, $options for json_encode()
     *                              defaults to ::ENCODE_DEFAULT or ::ENCODE_DEBUG, @see ::$debug
     *
     * @return json
     */
    public function get_json($encode_options = null)
    {
        if (is_int($encode_options) == false) {
            $encode_options = self::ENCODE_DEFAULT;
        }

        $response = $this->get_array();
        $json = json_encode($response, $encode_options);

        return $json;
    }

    /**
     * sends out the http status code and optional redirect location
     * defaults to ::STATUS_OK, or ::STATUS_INTERNAL_SERVER_ERROR for an errors response
     *
     * @return void
     */
    private function send_status_headers()
    {
        if ($this->redirect_location) {
            if ($this->http_status == self::STATUS_OK) {
                $this->set_http_status(self::STATUS_TEMPORARY_REDIRECT);
            }

            header('Location: ' . $this->redirect_location, true, $this->http_status);

            return;
        }

        http_response_code($this->http_status);
    }
}
