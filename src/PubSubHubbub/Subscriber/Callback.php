<?php

/**
 * @see       https://github.com/laminas/laminas-feed for the canonical source repository
 * @copyright https://github.com/laminas/laminas-feed/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-feed/blob/master/LICENSE.md New BSD License
 */

namespace ForkedLaminas\Feed\PubSubHubbub\Subscriber;

use DateTimeImmutable;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Stream as DiactorosStream;
use ForkedLaminas\Feed\PubSubHubbub\Exception\RuntimeException;
use ForkedLaminas\Feed\PubSubHubbub\PubSubHubbub as PubSubHubbub;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class Callback extends \ForkedLaminas\Feed\PubSubHubbub\AbstractCallback
{
    const InvalidHubRequest = 'InvalidHubRequest';
    const VerificationRequest = 'VerificationRequest';
    const ContentUpdate = 'ContentUpdate';

    const UnknownSubscription = 'Subscription not identified';
    const BadHTTPRequest = 'Request missing mandatory parameters';
    const TokenMismatch = 'Verify token mismatch';
    const StateMismatch = 'Subscription state mismatch';
    const SubscriptionConfirmed = 'Subscription confirmed';
    const SubscriptionDenied = 'Subscription denied';
    const UnsubscriptionConfirmed = 'Unsubscription confirmed';

    protected $responseStatus = 'Not found';

    /**
     * True if the content sent as updates to the Callback URL is a feed update
     *
     * @var bool
     */
    protected $feedUpdate = false;

    /**
     * Contains the content stream of any updates sent to the Callback URL
     *
     * @var StreamInterface
     */
    protected $content;

    /**
     * Contains the content (as string) of any updates sent to the Callback URL
     *
     * @var string
     */
    protected $content_as_string;

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
     * For testing only
     *
     * @var DateTimeImmutable
     */
    protected $now;

    /**
     * State of Authenticated content distribution
     *
     * @var bool|null
     */
    protected $authenticatedUpdate;

    /**
     * Set a subscription key to use for the current callback request manually.
     * Required if usePathParameter is enabled for the Subscriber.
     *
     * @param  string $key
     * @return $this
     */

    protected $status_state = 'Request not made';
    protected $status_detail = 'Request not made';

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
            $this->getHttpResponse()->withStatus(404, $this->responseStatus)
        );

        $this->status_state = self::InvalidHubRequest;
        $this->status_detail = self::BadHTTPRequest;

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
        } else {
            $this->responseStatus = 'Subscription key not identified';
            $this->status_state = self::InvalidHubRequest;
            $this->status_detail = self::UnknownSubscription;
        }

        //update to the latest response status
        $this->setHttpResponse(
            $this->getHttpResponse()
                ->withStatus(
                    $this->getHttpResponse()->getStatusCode(),
                    $this->responseStatus
                )
        );

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
        $this->responseStatus = 'OK';
        //always respond with 200
        $this->setHttpResponse(
            $this->getHttpResponse()
                ->withAddedHeader(
                    'X-Hub-On-Behalf-Of',
                    $this->getSubscriberCount()
                )
                ->withStatus(200, $this->responseStatus)
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
        $this->status_state = self::ContentUpdate;
        $this->status_detail = '';
    }

    protected function processVerification()
    {

        if (!$this->isValidHubRequest()) {
            $this->responseStatus = 'Hub request not valid';
            $this->status_state = self::InvalidHubRequest;
            $this->status_detail = self::BadHTTPRequest;
            return;
        }
        if (!$this->_hasValidVerifyToken()) {
            $this->responseStatus = 'Verification token match failed';
            $this->status_state = self::InvalidHubRequest;
            $this->status_detail = self::TokenMismatch;
            return;
        }
        $mode = strtolower($this->getRequest()->getQueryParams()['hub_mode']);
        if (!$this->confirmSubscriptionState($mode)) {
            $this->responseStatus = 'Subscription state not aligned with confirmation needed';
            $this->status_state = self::InvalidHubRequest;
            $this->status_detail = self::StateMismatch;
            return;
        }
        $this->saveSubscriptionState($mode);

        $params = $this->getRequest()->getQueryParams();
        $stream = fopen('php://memory', 'w+');
        if (array_key_exists('hub_challenge', $params)) {
            fwrite($stream, $params['hub_challenge']);
        }

        $this->responseStatus = 'OK';
        $this->setHttpResponse(
            $this->getHttpResponse()
                ->withStatus(200, $this->responseStatus)
                ->withBody(new DiactorosStream($stream))
        );
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

        //check if denial request 
        if (
            array_key_exists('hub_mode', $params) &&
            ($params['hub_mode'] == 'denied') &&
            ($this->currentSubscriptionData['hub_protocol'] == PubSubHubbub::PROTOCOL04)
        ) {
            return array_key_exists('hub_topic', $params);
        }

        $required = [
            'hub_mode',
            'hub_topic',
            'hub_challenge',
        ];
        if ($this->currentSubscriptionData['hub_protocol'] == PubSubHubbub::PROTOCOL03) {
            $required[] = 'hub_verify_token';
        }
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

    protected function confirmSubscriptionState($mode)
    {
        $subscription_state = $this->currentSubscriptionData['subscription_state'];
        if ($mode == 'subscribe' || $mode == 'denied') {
            return ($subscription_state == PubSubHubbub::SUBSCRIPTION_NOTVERIFIED) ||
                ($subscription_state == PubSubHubbub::SUBSCRIPTION_VERIFIED);
        } elseif ($mode == 'unsubscribe') {
            return $subscription_state == PubSubHubbub::SUBSCRIPTION_TODELETE;
        }
    }

    protected function saveSubscriptionState($mode)
    {
        if ($mode == 'subscribe') {
            $data = $this->currentSubscriptionData;
            $prior_state = $data['subscription_state'];
            $data['subscription_state'] = PubSubHubbub::SUBSCRIPTION_VERIFIED;
            $params = $this->getRequest()->getQueryParams();
            //if lease seconds provided, save them
            if ($params['hub_lease_seconds'] !== null) {
                $data['lease_seconds'] = $params['hub_lease_seconds'];
                $created_time = date_create_from_format(
                    'Y-m-d H:i:s',
                    $data['created_time']
                );
                //expiration based on created time for new subscriptions
                if ($prior_state == PubSubHubbub::SUBSCRIPTION_NOTVERIFIED) {
                    $expires = $created_time->add(
                        new \DateInterval('PT' . $data['lease_seconds'] . 'S')
                    )->format('Y-m-d H:i:s');
                } else {
                    //expiration based on current time for automatic resubscriptions
                    $expires = $this->getTimeNow()->add(
                        new \DateInterval('PT' . $data['lease_seconds'] . 'S')
                    )->format('Y-m-d H:i:s');
                }

                $data['expiration_time'] = $expires;
            } else {
                $data['lease_seconds'] = null;
                $data['expiration_time'] = null;
            }
            $this->getStorage()->setSubscription($data);
            $this->status_state = self::VerificationRequest;
            $this->status_detail = self::SubscriptionConfirmed;
        } elseif ($mode == 'unsubscribe') {
            $this->getStorage()->deleteSubscription($this->subscriptionKey);
            $this->status_state = self::VerificationRequest;
            $this->status_detail = self::UnsubscriptionConfirmed;
        } elseif ($mode == 'denied') {
            $data = $this->currentSubscriptionData;
            $data['subscription_state'] = PubSubHubbub::SUBSCRIPTION_DENIED;
            $this->getStorage()->setSubscription($data);
            $this->status_state = self::VerificationRequest;
            $this->status_detail = self::SubscriptionDenied;
        }
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
     * Gets a newly received content sent by a Hub as an update to a
     * Topic we've subscribed to. This may be not feed content (e.g. HTML/JSON)
     *     
     * @return string
     */
    public function getContentString()
    {
        if ($this->content_as_string === null) {
            $content = $this->getContent();
            if ($content instanceof StreamInterface) {
                $this->content_as_string = $content->__toString();
            }
        }
        return $this->content_as_string;
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
     * @return string
     */
    public function getFeedUpdate()
    {
        if ($this->hasFeedUpdate()) {
            return $this->getContentString();
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
     * Check for a valid verify_token. Only performed for PSHB 0.3 protocol version.
     *
     * @param  array $httpGetData
     * @param  bool $checkValue
     * @return bool
     */
    // @codingStandardsIgnoreStart
    protected function _hasValidVerifyToken()
    {
        if ($this->currentSubscriptionData['hub_protocol'] == PubSubHubbub::PROTOCOL04) {
            return true;
        }
        $verifyToken = $this->currentSubscriptionData['verify_token'];
        return ($verifyToken
            ==
            hash('sha256', $this->getRequest()->getQueryParams()['hub_verify_token']));
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

    protected function getTimeNow()
    {
        if ($this->now !== null) {
            return $this->now;
        } else {
            return new DateTimeImmutable();
        }
    }

    public function setTimeNow(DateTimeImmutable $now)
    {
        $this->now = $now;
    }

    /**
     * Returns the state whether an update was authenticated
     * True - yes/not required; false - authentication failed; 
     *
     * @return bool|null
     */
    public function authenticateContent()
    {
        if (!$this->needsAuthentication()) {
            return true;
        }
        $sha1 = $this->getRequest()->getHeaderLine('X-Hub-Signature');
        $check = 'sha1='
            . hash_hmac(
                'sha1',
                $this->getContentString(),
                $this->currentSubscriptionData['secret']
            );
        return $sha1 === $check;
    }

    /**
     * Returns if content authentication using HMAC is required
     *
     * @return bool
     */
    public function needsAuthentication()
    {
        if ($this->currentSubscriptionData == null) {
            throw new RuntimeException('Request not yet handled, subscription data not yet retrieved');
        } else {
            return $this->currentSubscriptionData['secret'] !== null;
        }
    }

    public function status()
    {
        return [
            'state' => $this->status_state,
            'details' => $this->status_detail
        ];
    }

    public function getSubscriptionData()
    {
        return $this->currentSubscriptionData;
    }

    public function __sleep()
    {
        //make sure to capture the string content of stream
        $this->getContentString();
        return array_keys(get_object_vars($this));
    }

    public function __wakeup()
    {
    }
}
