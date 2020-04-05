<?php

namespace Laminas\Feed\PubSubHubbub;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface PSR7ClientInterface extends RequestFactoryInterface, ClientInterface
{
}
