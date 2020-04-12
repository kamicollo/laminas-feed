<?php

/**
 * @see       https://github.com/laminas/laminas-feed for the canonical source repository
 * @copyright https://github.com/laminas/laminas-feed/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-feed/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Feed\PubSubHubbub\TestAsset;

use Laminas\Feed\PubSubHubbub\AbstractCallback;
use Psr\Http\Message\ServerRequestInterface;

class Callback extends AbstractCallback
{
    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request = null, $sendResponseNow = false)
    {
        return false;
    }
}
