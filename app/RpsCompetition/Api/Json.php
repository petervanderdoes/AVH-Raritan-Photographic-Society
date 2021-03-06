<?php

namespace RpsCompetition\Api;

/**
 * Class Json
 *
 * @package   RpsCompetition\Api
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2015-2016, AVH Software
 */
class Json
{
    const CONTENT_TYPE_OFFICIAL = 'application/json';
    /**
     * JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE = 320
     */
    const JSON_ENCODE_DEFAULT = 320;
    const JSON_STATUS_ERROR   = 'error';
    const JSON_STATUS_FAIL    = 'fail';
    const JSON_STATUS_SUCCESS = 'success';
    /**
     * internal data containers
     */
    private $data   = [];
    private $errors = [];
    private $status = null;

    /**
     * Constructor
     */
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
        $error           = [];
        $error['detail'] = $error_message;
        $this->errors[]  = $error;
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
     * Remove all error messages.
     */
    public function cleanErrors()
    {
        $this->errors = [];
    }

    /**
     * returns the whole response body as json
     * it generates the response via ->getArray()
     *
     * @param int|null $encode_options     optional, $options for json_encode()
     *                                     defaults to ::ENCODE_DEFAULT or ::ENCODE_DEBUG, @see ::$debug
     *
     * @return string
     */
    public function getJson($encode_options = null)
    {
        if (is_int($encode_options) === false) {
            $encode_options = self::JSON_ENCODE_DEFAULT;
        }

        $response = $this->getArray();
        $json     = json_encode($response, $encode_options);

        return $json;
    }

    /**
     * Get the JSON status
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set the JSON status
     *
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * Send out the json response to the browser
     *
     * @param  string|null $content_type    optional, defaults to ::CONTENT_TYPE_OFFICIAL (the official IANA registered
     *                                      one)
     * @param  int|null    $encode_options  optional, $options for json_encode()
     *                                      defaults to ::JSON_ENCODE_DEFAULT
     * @param  string|null $response        optional, defaults to ::getJson()
     *
     * @return void
     */
    public function sendResponse($content_type = null, $encode_options = null, $response = null)
    {
        if (is_null($response)) {
            $response = $this->getJson($encode_options);
        }

        if (empty($content_type)) {
            $content_type = self::CONTENT_TYPE_OFFICIAL;
        }

        header('Content-Type: ' . $content_type . '; charset=utf-8');

        echo $response;
    }

    /**
     * Set JSON Status to Error
     */
    public function setStatusError()
    {
        $this->setStatus(self::JSON_STATUS_ERROR);
    }

    /**
     * Set JSON Status to Fail
     */
    public function setStatusFail()
    {
        $this->setStatus(self::JSON_STATUS_FAIL);
    }

    /**
     * Set JSON Status to Success
     */
    public function setStatusSuccess()
    {
        $this->setStatus(self::JSON_STATUS_SUCCESS);
    }

    /**
     * Generate an array for the whole response body.
     *
     * @return array
     */
    private function getArray()
    {
        $response = [];
        if ($this->validateJsonStatus()) {
            $response['status'] = $this->status;
            if ($this->status !== self::JSON_STATUS_ERROR) {
                $response['data'] = $this->data;
            }
            if ($this->errors !== []) {
                $response['errors'] = $this->errors;
            }
        } else {
            $response['status'] = self::JSON_STATUS_ERROR;
            $this->cleanErrors();
            $this->addError('Invalid status code given: ' . html_entity_decode($this->status));
            $response['errors'] = $this->errors;
        }

        return $response;
    }

    /**
     * Validate the JSON status code.
     *
     * @return bool
     */
    private function validateJsonStatus()
    {
        $valid_response                            = [];
        $valid_response[self::JSON_STATUS_ERROR]   = true;
        $valid_response[self::JSON_STATUS_FAIL]    = true;
        $valid_response[self::JSON_STATUS_SUCCESS] = true;

        return array_key_exists($this->status, $valid_response);
    }
}
