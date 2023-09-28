<?php

namespace LonghornOpen\CanvasApi;

use GuzzleHttp\Client;
use Iterator;

class ResponseIterator implements Iterator
{
    private $position = 0;
    private $array = [];
    private $client;
    private $next_url;
    private $list_wrapper_element = null;

    public function __construct($response, $client, $list_wrapper_element = null)
    {
        $this->list_wrapper_element = $list_wrapper_element;
        $this->parse_response($response);
        $this->client = $client;
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function current()
    {
        return $this->array[$this->position];
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        if (($this->position + 1) >= count($this->array) && $this->next_url) {
            $response = $this->client->request(
                'GET',
                $this->next_url
            );
            $this->parse_response($response);
        }
        ++$this->position;
    }

    public function valid()
    {
        return isset($this->array[$this->position]);
    }

    protected function parse_response($response)
    {
        $contents = json_decode($response->getBody()->getContents(), false);
        if ($this->list_wrapper_element) {
            $contents = $contents->{$this->list_wrapper_element};
        }
        $this->array = array_merge($this->array, $contents);
        $links = $this->parse_pagination_headers($response->getHeader("link")[0]);
        if (array_key_exists('next', $links)) {
            $this->next_url = $links['next'];
        } else {
            $this->next_url = null;
        }
    }

    // Pagination headers are defined at https://canvas.instructure.com/doc/api/file.pagination.html
    // That format's a bit clunky, though - parse it into a name/value pair list for easier handling
    protected function parse_pagination_headers($link_header)
    {
        $link_header_items = explode(",", $link_header);
        foreach ($link_header_items as $item) {
            $item_parts = explode("; ", $item);
            $url = $item_parts[0];
            // strip off leading < and trailing >
            $url = substr($url, 1, -1);
            $rel = $item_parts[1];
            // strip off leading rel=" and trailing "
            $rel = substr($rel, 5, -1);
            $retval[$rel] = $url;
        }
        return $retval;
    }
}