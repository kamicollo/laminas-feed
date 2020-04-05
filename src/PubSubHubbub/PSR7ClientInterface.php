<?php

namespace Laminas\Feed\PubSubHubbub;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface PSR7ClientInterface
{
    /**
     * Sends a PSR-7 request and returns a PSR-7 response.
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     *
     */
    public function sendRequest(RequestInterface $request): ResponseInterface;

    /**
     * @param string                               $method  HTTP method
     * @param string|UriInterface                  $uri     URI
     * @param array                                $headers Request headers
     * @param string|null|resource|StreamInterface $body    Request body
     * @param string                               $version Protocol version
     */
    public function createRequest(
        $method,
        $uri,
        array $headers = [],
        $body = null,
        $version = '1.1'
    ): RequestInterface;
}
