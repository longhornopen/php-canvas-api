<?php

namespace LonghornOpen\CanvasApi;

use GuzzleHttp\ClientInterface;
use Iterator;
use Psr\Http\Message\ResponseInterface;

/** @implements Iterator<int, mixed> */
class ResponseIterator implements Iterator
{
    private int $position = 0;

    /** @var list<mixed> */
    private array $array = [];

    private ClientInterface $client;
    private ?string $next_url = null;
    private ?string $list_wrapper_element;

    public function __construct(
        ResponseInterface $response,
        ClientInterface $client,
        ?string $list_wrapper_element = null
    )
    {
        $this->list_wrapper_element = $list_wrapper_element;
        $this->parse_response($response);
        $this->client = $client;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function current(): mixed
    {
        return $this->array[$this->position];
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
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

    public function valid(): bool
    {
        return isset($this->array[$this->position]);
    }

    protected function parse_response(ResponseInterface $response): void
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
    /** @return array<string, string> */
    protected function parse_pagination_headers(string $link_header): array
    {
        $retval = [];
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
