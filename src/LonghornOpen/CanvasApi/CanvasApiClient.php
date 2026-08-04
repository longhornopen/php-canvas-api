<?php

namespace LonghornOpen\CanvasApi;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CanvasApiClient
{
    protected string $api_host;
    protected string $access_key;
    protected ClientInterface $client;

    /**
     * CanvasApi constructor.
     * @param string $api_host The hostname of the Canvas instance you want to connect to.  ex: 'utexas.instructure.com', 'http://local.canvas/'
     * @param string $access_key The access key you're using to authenticate yourself to Canvas.
     */
    public function __construct(string $api_host, string $access_key)
    {
        $this->api_host = $api_host;
        if (!str_contains($api_host, '://')) {
            $this->api_host = 'https://' . $this->api_host;
        }
        $this->access_key = $access_key;

        $stack = HandlerStack::create();
        $stack->push(
            Middleware::mapRequest(
                function (RequestInterface $request): RequestInterface {
                    return $request->withHeader('Authorization', 'Bearer ' . $this->access_key);
                }
            ),
            'add_auth_header'
        );
        $stack->push(
            Middleware::mapResponse(
                function (ResponseInterface $response): ResponseInterface {
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
     * @param ?string $wrapper_element If this API returns a list of items wrapped in an element (such as the Enrollment Terms API), the name of that element.
     * @return array|object|null An object or an Iterator, depending on whether the API endpoint is for a single object or a list.
     * @throws GuzzleException
     */
    public function get_iterator(string $api_url, ?string $wrapper_element = null): array|object|null
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
     * @param ?string $wrapper_element If this API returns a list of items wrapped in an element (such as the Enrollment Terms API), the name of that element.
     * @return array|object|null An object or an array, depending on whether the API endpoint is for a single object or a list.
     * @throws GuzzleException
     */
    public function get(string $api_url, ?string $wrapper_element = null): array|object|null
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

    protected function getFullUrl(string $api_url): string
    {
        $api_v1_prefix = '/api/v1';
        if (!str_starts_with($api_url, '/')) {
            $api_v1_prefix .= '/';
        }
        return $this->api_host . $api_v1_prefix . $api_url;
    }

    /**
     * @param string $api_url
     * @param array<string, mixed> $data
     * @return array|object|null
     * @throws GuzzleException
     */
    public function post(string $api_url, array $data): array|object|null
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
     * @param array<string, mixed> $data
     * @return array|object|null
     * @throws GuzzleException
     */
    public function put(string $api_url, array $data): array|object|null
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
     * @param array<string, mixed> $data
     * @return array|object|null
     * @throws GuzzleException
     */
    public function delete(string $api_url, array $data = []): array|object|null
    {
        if (empty($data)) {
            $data = [];
        }

        $response = $this->client->request(
            'DELETE',
            $this->getFullUrl($api_url),
            [
                'json' => $this->cleanDataForJSON($data)
            ]
        );
        return json_decode($response->getBody()->getContents(), false);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function cleanDataForJSON(array $data): array
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
            } elseif (str_contains($key, '[')) {
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
