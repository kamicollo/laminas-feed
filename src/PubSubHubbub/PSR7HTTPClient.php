<?php

namespace Laminas\Feed\PubSubHubbub;

use Laminas\Http\Client;
use Laminas\Http\Request as HttpRequest;
use Laminas\Psr7Bridge\Psr7Response;
use Laminas\Psr7Bridge\Psr7ServerRequest;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Feed\PubSubHubbub\PSR7ClientInterface;

class PSR7HTTPClient implements PSR7ClientInterface
{
    /**
     * Laminas HTTP client
     *
     * @var Client
     */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $response = $this->client->send(Psr7ServerRequest::toLaminas($request));
        return Psr7Response::fromLaminas($response);
    }

    public function createRequest(
        $method,
        $uri
    ): RequestInterface {
        $request = new HttpRequest();
        $request->setMethod($method)->setUri($uri);
        $psr7request =  Psr7ServerRequest::fromLaminas($request);
        return $psr7request;
    }
}
