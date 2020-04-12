<?php

/**
 * @see       https://github.com/laminas/laminas-feed for the canonical source repository
 * @copyright https://github.com/laminas/laminas-feed/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-feed/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Feed\PubSubHubbub\Subscriber;

use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Feed\PubSubHubbub;
use Laminas\Feed\PubSubHubbub\Exception;
use Laminas\Diactoros\Stream as DiactorosStream;
use Laminas\Feed\PubSubHubbub\Exception\RuntimeException;
use Laminas\Feed\Uri;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class Callback extends PubSubHubbub\AbstractCallback
{
    /**
     * True if the content sent as updates to the Callback URL is a feed update
     *
     * @var bool
     */
    protected $feedUpdate = false;

    /**
     * Contains the content of any updates sent to the Callback URL
     *
     * @var string
     */
    protected $content;

    /**
     * Holds a manually set subscription key (i.e. identifies a unique
     * subscription) which is typical when it is not passed in the query string
     * but is part of the Callback URL path, requiring manual retrieval e.g.
     * using a route and the \Laminas\Mvc\Router\RouteMatch::getParam() method.
     *
     * @var string
     */
    protected $subscriptionKey;

    /**
     * After verification, this is set to the verified subscription's data.
     *
     * @var array
     */
    protected $currentSubscriptionData;

    /**
     * Request object
     *
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * Set a subscription key to use for the current callback request manually.
     * Required if usePathParameter is enabled for the Subscriber.
     *
     * @param  string $key
     * @return $this
     */
    public function setSubscriptionKey($key)
    {
        $this->subscriptionKey = $key;
        return $this;
    }

    /**
     * Handle any callback from a Hub Server responding to a subscription or
     * unsubscription request. This should be the Hub Server confirming the
     * the request prior to taking action on it.
     *
     * @param  null|ServerRequestInterface $request     Request environment object (if available)
     * @param  bool       $sendResponseNow Whether to send response now or when asked
     * @return void
     */
    public function handle(ServerRequestInterface $request = null, $sendResponseNow = false)
    {
        if ($request !== null) {
            $this->setRequest($request);
        }

        //default response is 404
        $this->setHttpResponse(
            $this->getHttpResponse()->withStatus(404)
        );

        //confirm we can identify associated subscription
        //if yes, proceed with processing
        if ($this->setupSubscription()) {
            if (strtolower($this->getRequest()->getMethod()) === 'post') {
                $this->processFeedUpdate();
            } elseif (
                strtolower($this->getRequest()->getMethod()) === 'get'
            ) {
                $this->processVerification();
            }
        }

        if ($sendResponseNow) {
            $this->sendResponse();
        }
    }
    /**
     * Set current active request object
     * @param ServerRequestInterface $request
     * @return $this
     */

    public function setRequest(ServerRequestInterface $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Returns active request object
     *
     * @return ServerRequestInterface
     */
    public function getRequest(): ServerRequestInterface
    {
        if ($this->request === null) {
            $this->request = ServerRequestFactory::fromGlobals();
        }
        return $this->request;
    }

    protected function processFeedUpdate()
    {
        /**         
         * This DOES NOT attempt to process a feed update. Feed updates
         * SHOULD be validated/processed by an asynchronous process so as
         * to avoid holding up responses to the Hub.
         */

        //always respond with 200
        $this->setHttpResponse(
            $this->getHttpResponse()
                ->withAddedHeader(
                    'X-Hub-On-Behalf-Of',
                    $this->getSubscriberCount()
                )
                ->withStatus(200)
        );
        //save content
        $this->setContent($this->getRequest()->getBody());

        //mark if it is a proper feed update or some other content
        $contentType = $this->getRequest()->getHeaderLine('Content-Type');
        if ((stripos($contentType, 'application/atom+xml') === 0
            || stripos($contentType, 'application/rss+xml') === 0
            || stripos($contentType, 'application/xml') === 0
            || stripos($contentType, 'text/xml') === 0
            || stripos($contentType, 'application/rdf+xml') === 0)) {
            $this->setFeedUpdate(true);
        }
        //TODO
        //1. Validate hub secret        
    }

    protected function processVerification()
    {

        /**
         * Handle any (un)subscribe confirmation requests
         * @todo Replace exception throw with 404 response for invalid hub modes
         * @todo review usage of current subscription data across the class
         */
        if ($this->isValidHubRequest()) {

            /** @TODO - move to the verification function */
            if (!$this->_hasValidVerifyToken()) {
                return false;
            }
            /** @TODO END */

            /**
             * Attempt to retrieve any Verification Token Key attached to Callback
             * URL's path by our Subscriber implementation
             */


            $stream = fopen('php://memory', 'w+');
            fwrite($stream, $this->getRequest()->getQueryParams()['hub_challenge']);

            $this->setHttpResponse(
                $this->getHttpResponse()
                    ->withBody(new DiactorosStream($stream))->withStatus(200)
            );

            switch (strtolower($this->getRequest()->getQueryParams()['hub_mode'])) {
                case 'subscribe':
                    $data                       = $this->currentSubscriptionData;
                    $data['subscription_state'] = PubSubHubbub\PubSubHubbub::SUBSCRIPTION_VERIFIED;
                    if (isset($this->getRequest()->getQueryParams()['hub_lease_seconds'])) {
                        $data['lease_seconds'] = $this->getRequest()->getQueryParams()['hub_lease_seconds'];
                    }
                    $this->getStorage()->setSubscription($data);
                    break;
                case 'unsubscribe':
                    $verifyTokenKey = $this->_detectVerifyTokenKey($this->getRequest()->getQueryParams());
                    $this->getStorage()->deleteSubscription($verifyTokenKey);
                    break;
                default:
                    throw new Exception\RuntimeException(sprintf(
                        'Invalid hub_mode ("%s") provided',
                        $this->getRequest()->getQueryParams()['hub_mode']
                    ));
            }
            /**
             * Hey, C'mon! We tried everything else!
             */
        } else {
            $this->setHttpResponse(
                $this->getHttpResponse()->withStatus(404)
            );
        }
    }


    /**
     * Checks validity of the request simply by making a quick pass and
     * confirming the presence of all REQUIRED parameters.
     *
     * @return bool
     */
    public function isValidHubRequest()
    {
        /**
         * As per the specification, the hub.verify_token is OPTIONAL. This
         * implementation of Pubsubhubbub considers it REQUIRED and will
         * always send a hub.verify_token parameter to be echoed back
         * by the Hub Server. Therefore, its absence is considered invalid.
         */
        $params = $this->getRequest()->getQueryParams();
        $required = [
            'hub_mode',
            'hub_topic',
            'hub_challenge',
            'hub_verify_token',
        ];
        foreach ($required as $key) {
            if (!array_key_exists($key, $params)) {
                return false;
            }
        }
        if (
            $params['hub_mode'] !== 'subscribe'
            && $params['hub_mode'] !== 'unsubscribe'
        ) {
            return false;
        }
        if (
            $params['hub_mode'] === 'subscribe'
            && !array_key_exists('hub_lease_seconds', $params)
        ) {
            return false;
        }
        return true;
    }

    /**
     * Sets a flag if newly received content sent by a Hub as an update to a
     * topic we've subscribed to is of feed (Atom/RSS) format.
     *
     * @param  bool $feed
     * @return $this
     */
    public function setFeedUpdate($feed)
    {
        $this->feedUpdate = $feed;
        return $this;
    }

    /**
     * Sets a newly received content sent by a Hub as an update to a
     * Topic we've subscribed to. This may be not feed content (e.g. HTML/JSON)
     *
     * @param  StreamInterface $content
     * @return $this
     */
    public function setContent(StreamInterface $content)
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Gets a newly received content sent by a Hub as an update to a
     * Topic we've subscribed to. This may be not feed content (e.g. HTML/JSON)
     *     
     * @return StreamInterface
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Check if any newly received feed (Atom/RSS) update was received
     *
     * @return bool
     */
    public function hasFeedUpdate()
    {
        if ($this->feedUpdate === false) {
            return false;
        }
        return true;
    }

    /**
     * Gets a newly received feed (Atom/RSS) sent by a Hub as an update to a
     * Topic we've subscribed to.
     *
     * @return StreamInterface|null
     */
    public function getFeedUpdate()
    {
        if ($this->hasFeedUpdate()) {
            return $this->getContent();
        }
    }

    /**
     * Confirms if the subscription ID is recognized
     *
     * @return bool
     */
    public function setupSubscription()
    {
        $id = $this->_detectSubscriptionKey();
        //if subscription key was not provided
        if (empty($id)) {
            throw new RuntimeException(
                'Subscription key was not set and it was not possible to infer it from URI'
            );
        }
        //does ID exist in DB?
        $idExists = $this->getStorage()->hasSubscription($id);
        if (!$idExists) {
            return false;
        }
        //setup subscription data and confirm existence
        $this->currentSubscriptionData = $this->getStorage()->getSubscription($id);
        return true;
    }

    /**
     * Check for a valid verify_token. By default attempts to compare values
     * with that sent from Hub, otherwise merely ascertains its existence.
     *
     * @param  array $httpGetData
     * @param  bool $checkValue
     * @return bool
     */
    // @codingStandardsIgnoreStart
    protected function _hasValidVerifyToken($checkValue = true)
    {
        if ($checkValue) {
            $verifyToken = $this->currentSubscriptionData['verify_token'];
            if ($verifyToken !== hash('sha256', $this->getRequest()->getQueryParams()['hub_verify_token'])) {
                return false;
            }
            return true;
        }
        return true;
    }

    /**
     * Attempt to detect the subscription key. This would be passed in
     * the Callback URL (which we are handling with this class!) as a URI
     * path part (the last part by convention).
     *
     * @param  null|array $httpGetData
     * @return false|string
     */
    // @codingStandardsIgnoreStart
    protected function _detectSubscriptionKey()
    {
        // @codingStandardsIgnoreEnd
        /**
         * Available when sub keys encoding in Callback URL path
         */
        if (isset($this->subscriptionKey)) {
            return $this->subscriptionKey;
        }

        /**
         * Available only if allowed by PuSH 0.2 Hubs
         */
        $params = $this->getRequest()->getQueryParams();
        if (
            is_array($params)
            && isset($params['xhub_subscription'])
        ) {
            return $params['xhub_subscription'];
        }

        return false;
    }
}
