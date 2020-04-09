<?php

namespace Laminas\Feed\PubSubHubbub;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

interface PSR7ClientInterface extends RequestFactoryInterface, ClientInterface
{
}
