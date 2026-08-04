<?php

namespace LonghornOpen\CanvasApi;

use RuntimeException;

class CanvasApiException extends RuntimeException
{
    protected string $response_body;

    public function __construct(int $http_status_code, string $response_body)
    {
        $this->response_body = $response_body;
        $message = 'Error ' . $http_status_code . ': ' . $response_body;
        parent::__construct($message, $http_status_code);
    }
}
