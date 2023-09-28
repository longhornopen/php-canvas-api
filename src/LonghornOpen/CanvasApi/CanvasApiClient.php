<?php

namespace LonghornOpen\CanvasApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CanvasApiClient
{
    protected $api_host;
    protected $access_key;
    protected $client;

    /**
     * CanvasApi constructor.
     * @param string $api_host The hostname of the Canvas instance you want to connect to.  ex: 'utexas.instructure.com', 'http://local.canvas/'
     * @param string $access_key The access key you're using to authenticate yourself to Canvas.
     */
    public function __construct($api_host, $access_key)
    {
        $this->api_host = $api_host;
        if (strpos($api_host, '://') === false) {
            $this->api_host = 'https://' . $this->api_host;
        }
        $this->access_key = $access_key;

        $stack = HandlerStack::create();
        $stack->push(
            Middleware::mapRequest(
                function (RequestInterface $r) {
                    return $r->withHeader('Authorization', 'Bearer ' . $this->access_key);
                }
            ),
            'add_auth_header'
        );
        $stack->push(
            Middleware::mapResponse(
                function (ResponseInterface $response) {
                    if ($response->getStatusCode() >= 400) {
                        throw new CanvasApiException($response->getStatusCode(), $response->getBody()->getContents());
                    }
                    return $response;
                }
            ),
            'throw_custom_errors'
        );
        $this->client = new Client(
            [
                'handler' => $stack,
            ]
        );
    }

    /**
     * @param string $api_url The Canvas API URL you want to make a GET request for.  ex: 'courses/1', '/users/123?per_page=100'
     * @param string $wrapper_element If this API returns a list of items wrapped in an element (such as the Enrollment Terms API), the name of that element.
     * @return ResponseIterator|mixed An object or an Iterator, depending on whether the API endpoint is for a single object or a list.
     * @throws GuzzleException
     */
    public function get_iterator($api_url, $wrapper_element=null)
    {
        $response = $this->client->request(
            'GET',
            $this->getFullUrl($api_url)
        );
        if ($response->hasHeader('link')) {
            return new ResponseIterator($response, $this->client, $wrapper_element);
        }
        return json_decode($response->getBody()->getContents(), false);
    }

    /**
     * @param string $api_url The Canvas API URL you want to make a GET request for.  ex: '/courses/1', '/users/123?per_page=100'
     * @param string $wrapper_element If this API returns a list of items wrapped in an element (such as the Enrollment Terms API), the name of that element.
     * @return array|mixed An object or an array, depending on whether the API endpoint is for a single object or a list.
     * @throws GuzzleException
     */
    public function get($api_url, $wrapper_element)
    {
        $response = $this->client->request(
            'GET',
            $this->getFullUrl($api_url)
        );
        if ($response->hasHeader('link')) {
            return iterator_to_array(new ResponseIterator($response, $this->client, $wrapper_element));
        }
        return json_decode($response->getBody()->getContents(), false);
    }

    protected function getFullUrl($api_url)
    {
        $api_v1_prefix = '/api/v1';
        if (strpos($api_url, '/') !== 0) {
            $api_v1_prefix .= '/';
        }
        return $this->api_host . $api_v1_prefix . $api_url;
    }

    /**
     * @param string $api_url
     * @param array $data
     * @return object|null
     * @throws GuzzleException
     */
    public function post($api_url, $data)
    {
        $response = $this->client->request(
            'POST',
            $this->getFullUrl($api_url),
            [
                'json' => $this->cleanDataForJSON($data)
            ]
        );
        return json_decode($response->getBody()->getContents(), false);
    }

    /**
     * @param string $api_url
     * @param array $data
     * @return object|null
     * @throws GuzzleException
     */
    public function put($api_url, $data)
    {
        $response = $this->client->request(
            'PUT',
            $this->getFullUrl($api_url),
            [
                'json' => $this->cleanDataForJSON($data)
            ]
        );
        return json_decode($response->getBody()->getContents(), false);
    }

    /**
     * @param string $api_url
     * @return object|null
     * @throws GuzzleException
     */
    public function delete($api_url)
    {
        $response = $this->client->request(
            'DELETE',
            $this->getFullUrl($api_url)
        );
        return json_decode($response->getBody()->getContents(), false);
    }

    public function cleanDataForJSON($data)
    {
        // JSON-style data is preferred, but the API docs list everything as form-encoded style name/value pairs.
        // If somebody is just copying verbatim from the API docs, try to convert form-encoded complex objects
        // into JSON objects.
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value) && substr_compare($key, '[]', -strlen('[]')) === 0) {
                // 'foo[]' as a key with an array value?  Drop the '[]'.
                $k = substr($key, 0, -strlen('[]'));
                $result[$k] = $value;
            } elseif (strpos($key, '[') !== false) {
                // 'assignment[name]' as key?  Convert into 'assignment' array with 'name' key.
                $open_square_posn = strpos($key, '[');
                $close_square_posn = strpos($key, ']');
                $obj_name = substr($key, 0, $open_square_posn);
                $obj_prop = substr($key, $open_square_posn + 1, $close_square_posn - $open_square_posn - 1);
                if (!array_key_exists($obj_name, $result)) {
                    $result[$obj_name] = [];
                }
                $result[$obj_name][$obj_prop] = $value;
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}