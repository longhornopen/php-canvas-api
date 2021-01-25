<?php

namespace LonghornOpen\CanvasApi;

use RuntimeException;

class CanvasApiException extends RuntimeException
{
    protected $response_body;

    public function __construct($http_status_code, $response_body)
    {
        $this->response_body = $response_body;
        $message = 'Error ' . $http_status_code . ': ' . $response_body;
        parent::__construct($message, $http_status_code);
    }
}