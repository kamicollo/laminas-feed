<?php

/**
 * @see       https://github.com/laminas/laminas-feed for the canonical source repository
 * @copyright https://github.com/laminas/laminas-feed/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-feed/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Feed\PubSubHubbub;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface CallbackInterface
{
    /**
     * Handle any callback from a Hub Server responding to a subscription or
     * unsubscription request. This should be the Hub Server confirming the
     * the request prior to taking action on it.
     *
     * @param null|array $request current Request PSR-7 object
     * @param bool $sendResponseNow Whether to send response now or when asked
     */
    public function handle(ServerRequestInterface $request = null, $sendResponseNow = false);

    /**
     * Send the response, including all headers. You can also use getHTTPResponse() to
     * get PSR-7 Response and pass it to your framework to emit.     
     *
     * @return void
     */
    public function sendResponse();

    /**
     * Sets an instance of a class handling Http Responses. PSR-7.
     *
     * @param ResponseInterface $httpResponse
     */
    public function setHttpResponse(ResponseInterface $httpResponse);

    /**
     * Returns an instance of a class handling Http Responses. PSR-7.
     *
     * @return ResponseInterface
     */
    public function getHttpResponse();
}
