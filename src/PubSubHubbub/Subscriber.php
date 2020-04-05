<?php

/**
 * @see       https://github.com/laminas/laminas-feed for the canonical source repository
 * @copyright https://github.com/laminas/laminas-feed/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-feed/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Feed\PubSubHubbub;

use DateInterval;
use DateTime;
use Laminas\Feed\Uri;
use Laminas\Http\Request as HttpRequest;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

class Subscriber
{
    /**
     * An array of URLs for all Hub Servers to subscribe/unsubscribe.
     *
     * @var array
     */
    protected $hubUrls = [];

    /**
     * An array of optional parameters to be included in any
     * (un)subscribe requests.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * An array of optional parameters to be included in hub specific
     * (un)subscribe requests.
     *
     * @var array
     */
    protected $hub_parameters = [];

    /**
     * The URL of the topic (Rss or Atom feed) which is the subject of
     * our current intent to subscribe to/unsubscribe from updates from
     * the currently configured Hub Servers.
     *
     * @var string
     */
    protected $topicUrl = '';

    /**
     * The URL Hub Servers must use when communicating with this Subscriber
     *
     * @var string
     */
    protected $callbackUrl = '';

    /**
     * The number of seconds for which the subscriber would like to have the
     * subscription active. Defaults to null, i.e. not sent, to setup a
     * permanent subscription if possible.
     *
     * @var int
     */
    protected $leaseSeconds;

    /**
     * The preferred verification mode (sync or async). By default, this
     * Subscriber prefers synchronous verification, but is considered
     * desirable to support asynchronous verification if possible.
     *
     * Laminas\Feed\Pubsubhubbub\Subscriber will always send both modes, whose
     * order of occurrence in the parameter list determines this preference.
     *
     * @var string
     */
    protected $preferredVerificationMode = PubSubHubbub::VERIFICATION_MODE_SYNC;

    /**
     * An array of any errors including keys for 'response', 'hubUrl'.
     * The response is the actual Laminas\Http\Response object.
     *
     * @var array
     */
    protected $errors = [];

    /**
     * An array of Hub Server URLs for Hubs operating at this time in
     * asynchronous verification mode.
     *
     * @var array
     */
    protected $asyncHubs = [];

    /**
     * An instance of Laminas\Feed\Pubsubhubbub\Model\SubscriptionPersistence used to background
     * save any verification tokens associated with a subscription or other.
     *
     * @var Model\SubscriptionPersistenceInterface
     */
    protected $storage;

    /**
     * Hub-specific list of headers to be applied to each request
     *
     * @var array
     */
    protected $hub_headers = [];

    /**
     * List of headers to be applied to each request for every hub
     *
     * @var array
     */
    protected $headers = [];

    /**
     * Tells the Subscriber to append any subscription identifier to the path
     * of the base Callback URL. E.g. an identifier "subkey1" would be added
     * to the callback URL "http://www.example.com/callback" to create a subscription
     * specific Callback URL of "http://www.example.com/callback/subkey1".
     *
     * This is required for all Hubs using the Pubsubhubbub 0.1 Specification.
     * It should be manually intercepted and passed to the Callback class using
     * Laminas\Feed\Pubsubhubbub\Subscriber\Callback::setSubscriptionKey(). Will
     * require a route in the form "callback/:subkey" to allow the parameter be
     * retrieved from an action using the Laminas\Controller\Action::\getParam()
     * method.
     *
     * @var string
     */
    protected $usePathParameter = false;

    /**
     * Constructor; accepts an array or Traversable instance to preset
     * options for the Subscriber without calling all supported setter
     * methods in turn.
     *
     * @param null|array|Traversable $options
     */
    public function __construct($options = null)
    {
        if ($options !== null) {
            $this->setOptions($options);
        }
    }

    /**
     * Process any injected configuration options
     *
     * @param  array|Traversable $options
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        }

        if (!is_array($options)) {
            throw new Exception\InvalidArgumentException(
                'Array or Traversable object expected, got ' . gettype($options)
            );
        }
        if (array_key_exists('hubUrls', $options)) {
            $this->addHubUrls($options['hubUrls']);
        }
        if (array_key_exists('callbackUrl', $options)) {
            $this->setCallbackUrl($options['callbackUrl']);
        }
        if (array_key_exists('topicUrl', $options)) {
            $this->setTopicUrl($options['topicUrl']);
        }
        if (array_key_exists('storage', $options)) {
            $this->setStorage($options['storage']);
        }
        if (array_key_exists('leaseSeconds', $options)) {
            $this->setLeaseSeconds($options['leaseSeconds']);
        }
        if (array_key_exists('parameters', $options)) {
            $this->setParameters($options['parameters']);
        }
        if (array_key_exists('headers', $options)) {
            $this->setHeaders($options['headers']);
        }
        if (array_key_exists('authentications', $options)) {
            $this->addAuthentications($options['authentications']);
        }
        if (array_key_exists('usePathParameter', $options)) {
            $this->usePathParameter($options['usePathParameter']);
        }
        if (array_key_exists('preferredVerificationMode', $options)) {
            $this->setPreferredVerificationMode(
                $options['preferredVerificationMode']
            );
        }
        return $this;
    }

    /**
     * Set the topic URL (RSS or Atom feed) to which the intended (un)subscribe
     * event will relate
     *
     * @param  string $url
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setTopicUrl($url)
    {
        $this->_validateUrl($url, "url");
        $this->topicUrl = $url;
        return $this;
    }

    /**
     * Validates a given variable is a URL
     *
     * @param mixed $url String to validate
     * @param string $name Name of the parameter to generate message for
     * @throws Exception\InvalidArgumentException
     * @return bool
     */
    protected function _validateUrl($url, $name)
    {
        if (empty($url) || !is_string($url) || !Uri::factory($url)->isValid()) {
            throw new Exception\InvalidArgumentException(
                'Invalid parameter "' . $name . '" of "' . $url .
                    '" must be a non-empty string and a valid URL'
            );
        }
        return true;
    }

    /**
     * Validates a given variable is a non-empty string
     *
     * @param mixed $url String to validate
     * @param string $name Name of the parameter to generate message for
     * @throws Exception\InvalidArgumentException
     * @return bool
     */
    protected function _validateString($string, $name)
    {
        if (empty($string) || !is_string($string)) {
            throw new Exception\InvalidArgumentException(
                'Invalid parameter "' . $name . '" of "' . $string .
                    '" must be a non-empty string'
            );
        }
        return true;
    }

    /**
     * Set the topic URL (RSS or Atom feed) to which the intended (un)subscribe
     * event will relate
     *
     * @return string
     * @throws Exception\RuntimeException
     */
    public function getTopicUrl()
    {
        if (empty($this->topicUrl)) {
            throw new Exception\RuntimeException(
                'A valid Topic (RSS or Atom feed) URL MUST be set before attempting any operation'
            );
        }
        return $this->topicUrl;
    }

    /**
     * Set the number of seconds for which any subscription will remain valid
     *
     * @param  int|null $seconds
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setLeaseSeconds($seconds)
    {
        if ($seconds === null) {
            $this->leaseSeconds = $seconds;
            return $this;
        }

        $seconds = intval($seconds);
        if ($seconds <= 0) {
            throw new Exception\InvalidArgumentException(
                'Expected lease seconds must be an integer greater than zero'
            );
        }
        $this->leaseSeconds = $seconds;
        return $this;
    }

    /**
     * Get the number of lease seconds on subscriptions
     *
     * @return int
     */
    public function getLeaseSeconds()
    {
        return $this->leaseSeconds;
    }

    /**
     * Set the callback URL to be used by Hub Servers when communicating with
     * this Subscriber
     *
     * @param  string $url
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setCallbackUrl($url)
    {
        $this->_validateUrl($url, "url");
        $this->callbackUrl = $url;
        return $this;
    }

    /**
     * Get the callback URL to be used by Hub Servers when communicating with
     * this Subscriber
     *
     * @return string
     * @throws Exception\RuntimeException
     */
    public function getCallbackUrl()
    {
        if (empty($this->callbackUrl)) {
            throw new Exception\RuntimeException(
                'A valid Callback URL MUST be set before attempting any operation'
            );
        }
        return $this->callbackUrl;
    }

    /**
     * Set preferred verification mode (sync or async). By default, this
     * Subscriber prefers synchronous verification, but does support
     * asynchronous if that's the Hub Server's utilised mode.
     *
     * Laminas\Feed\Pubsubhubbub\Subscriber will always send both modes, whose
     * order of occurrence in the parameter list determines this preference.
     *
     * @param  string $mode Should be 'sync' or 'async'
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setPreferredVerificationMode($mode)
    {
        if (
            $mode !== PubSubHubbub::VERIFICATION_MODE_SYNC
            && $mode !== PubSubHubbub::VERIFICATION_MODE_ASYNC
        ) {
            throw new Exception\InvalidArgumentException(
                'Invalid preferred mode specified: "' . $mode . '" but should be one of'
                    . ' Laminas\Feed\Pubsubhubbub::VERIFICATION_MODE_SYNC or'
                    . ' Laminas\Feed\Pubsubhubbub::VERIFICATION_MODE_ASYNC'
            );
        }
        $this->preferredVerificationMode = $mode;
        return $this;
    }

    /**
     * Get preferred verification mode (sync or async).
     *
     * @return string
     */
    public function getPreferredVerificationMode()
    {
        return $this->preferredVerificationMode;
    }

    /**
     * Add a Hub Server URL supported by Publisher
     *
     * @param  string $url
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function addHubUrl($url)
    {
        $this->_validateUrl($url, "url");
        $this->hubUrls[] = $url;
        return $this;
    }

    /**
     * Add an array of Hub Server URLs supported by Publisher
     *
     * @return $this
     */
    public function addHubUrls(array $urls)
    {
        foreach ($urls as $url) {
            $this->addHubUrl($url);
        }
        return $this;
    }

    /**
     * Remove a Hub Server URL
     *
     * @param  string $url
     * @return $this
     */
    public function removeHubUrl($url)
    {
        if (!in_array($url, $this->getHubUrls())) {
            return $this;
        }
        $key = array_search($url, $this->hubUrls);
        unset($this->hubUrls[$key]);
        return $this;
    }

    /**
     * Return an array of unique Hub Server URLs currently available
     *
     * @return array
     */
    public function getHubUrls()
    {
        $this->hubUrls = array_unique($this->hubUrls);
        return $this->hubUrls;
    }

    /**
     * Add authentication credentials for a given URL
     *
     * @param  string $url
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function addAuthentication($url, array $authentication)
    {
        $this->_validateUrl($url, "url");
        $this->setHubHeader($url, 'auth', $authentication);
        return $this;
    }

    /**
     * Add authentication credentials for hub URLs
     *
     * @return $this
     */
    public function addAuthentications(array $authentications)
    {
        foreach ($authentications as $url => $authentication) {
            $this->addAuthentication($url, $authentication);
        }
        return $this;
    }

    /**
     * Get all hub URL authentication credentials
     *
     * @return array
     */
    public function getAuthentications()
    {
        $auths = [];
        foreach ($this->getAllHubHeaders() as $url => $headers) {
            $auth = $this->getAuthentication($url);
            if ($auth !== null) {
                $auths[$url] = $auth;
            }
        }
        return $auths;
    }

    /**
     * Get authentication credentials for a particular hub
     * @param string $$hubUrl
     * @return array|null
     */
    public function getAuthentication($hubUrl)
    {
        $headers = $this->getHubHeaders($hubUrl);
        if (array_key_exists('auth', $headers)) {
            return $headers['auth'];
        } else {
            return null;
        }
    }

    /**
     * Set flag indicating whether or not to use a path parameter
     *
     * @param  bool $bool
     * @return $this
     */
    public function usePathParameter($bool = true)
    {
        $this->usePathParameter = $bool;
        return $this;
    }

    /**
     * Add an optional parameter to the (un)subscribe requests
     *
     * @param  string      $name
     * @param  null|string $value
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setParameter($name, $value = null)
    {
        if (is_array($name)) {
            $this->setParameters($name);
            return $this;
        }
        $this->_validateString($name, "name");

        if ($value === null) {
            $this->removeParameter($name);
            return $this;
        } else {
            $this->_validateString($value, "value");
            $this->parameters[$name] = $value;
            return $this;
        }
    }

    /**
     * Add an optional parameter to the (un)subscribe requests
     *
     * @return $this
     */
    public function setParameters(array $parameters)
    {
        foreach ($parameters as $name => $value) {
            $this->setParameter($name, $value);
        }
        return $this;
    }

    /**
     * Remove an optional parameter for the (un)subscribe requests
     *
     * @param  string $name
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function removeParameter($name)
    {
        $this->_validateString($name, 'name');
        if (array_key_exists($name, $this->parameters)) {
            unset($this->parameters[$name]);
        }
        return $this;
    }

    /**
     * Return an array of optional parameters for (un)subscribe requests
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Sets an instance of Laminas\Feed\Pubsubhubbub\Model\SubscriptionPersistence used to background
     * save any verification tokens associated with a subscription or other.
     *
     * @return $this
     */
    public function setStorage(Model\SubscriptionPersistenceInterface $storage)
    {
        $this->storage = $storage;
        return $this;
    }

    /**
     * Gets an instance of Laminas\Feed\Pubsubhubbub\Storage\StoragePersistence used
     * to background save any verification tokens associated with a subscription
     * or other.
     *
     * @return Model\SubscriptionPersistenceInterface
     * @throws Exception\RuntimeException
     */
    public function getStorage()
    {
        if ($this->storage === null) {
            throw new Exception\RuntimeException('No storage vehicle has been set.');
        }
        return $this->storage;
    }

    /**
     * Subscribe to one or more Hub Servers using the stored Hub URLs
     * for the given Topic URL (RSS or Atom feed)
     *
     * @return void
     */
    public function subscribeAll()
    {
        $this->_doRequest('subscribe');
    }

    /**
     * Unsubscribe from one or more Hub Servers using the stored Hub URLs
     * for the given Topic URL (RSS or Atom feed)
     *
     * @return void
     */
    public function unsubscribeAll()
    {
        $this->_doRequest('unsubscribe');
    }

    /**
     * Returns a boolean indicator of whether the notifications to Hub
     * Servers were ALL successful. If even one failed, FALSE is returned.
     *
     * @return bool
     */
    public function isSuccess()
    {
        return !$this->errors;
    }

    /**
     * Return an array of errors met from any failures, including keys:
     * 'response' => the Laminas\Http\Response object from the failure
     * 'hubUrl' => the URL of the Hub Server whose notification failed
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Return an array of Hub Server URLs who returned a response indicating
     * operation in Asynchronous Verification Mode, i.e. they will not confirm
     * any (un)subscription immediately but at a later time (Hubs may be
     * doing this as a batch process when load balancing)
     *
     * @return array
     */
    public function getAsyncHubs()
    {
        return $this->asyncHubs;
    }

    /**
     * Executes an (un)subscribe request
     *
     * @param  string $mode
     * @return void
     * @throws Exception\RuntimeException
     */
    // @codingStandardsIgnoreStart
    protected function _doRequest($mode)
    {
        // @codingStandardsIgnoreEnd
        $client = $this->_getHttpClient();
        $hubs   = $this->getHubUrls();
        if (empty($hubs)) {
            throw new Exception\RuntimeException(
                'No Hub Server URLs have been set so no subscriptions can be attempted'
            );
        }
        $this->errors    = [];
        $this->asyncHubs = [];
        foreach ($hubs as $url) {
            $hub_headers = $this->getHubHeaders($url);
            if (array_key_exists('auth', $hub_headers)) {
                $auth = $hub_headers['auth'];
                $client->setAuth($auth[0], $auth[1]);
            }
            //get params
            $params = $this->_getRequestParameters($url, $mode);

            //construct request
            $client->setUri($url);
            $client->setRawBody(
                $this->_toByteValueOrderedString(
                    $this->_urlEncode($params)
                )
            );

            // store subscription to storage
            $this->saveSubscriptionState($mode, $url, $params);

            //execute request
            $response = $client->send();
            if (
                $response->getStatusCode() !== 204
                && $response->getStatusCode() !== 202
            ) {
                $this->errors[] = [
                    'response' => $response,
                    'hubUrl'   => $url,
                ];

                /**
                 * At first I thought it was needed, but the backend storage will
                 * allow tracking async without any user interference. It's left
                 * here in case the user is interested in knowing what Hubs
                 * are using async verification modes so they may update Models and
                 * move these to asynchronous processes.
                 */
            } elseif ($response->getStatusCode() == 202) {
                $this->asyncHubs[] = [
                    'response' => $response,
                    'hubUrl'   => $url,
                ];
            }
        }
    }

    /**
     * Get a basic prepared HTTP client for use
     *
     * @return \Laminas\Http\Client
     */
    // @codingStandardsIgnoreStart
    protected function _getHttpClient()
    {
        // @codingStandardsIgnoreEnd
        $client = PubSubHubbub::getHttpClient();
        $client->setMethod(HttpRequest::METHOD_POST);
        $client->setOptions([
            'useragent' => 'Laminas_Feed_Pubsubhubbub_Subscriber/' . Version::VERSION,
        ]);
        return $client;
    }

    /**
     * Return a list of standard protocol/optional parameters for addition to
     * client's POST body that are specific to the current Hub Server URL
     *
     * @param  string $hubUrl
     * @param  string $mode
     * @return array
     * @throws Exception\InvalidArgumentException
     */
    // @codingStandardsIgnoreStart
    protected function _getRequestParameters($hubUrl, $mode)
    {
        // @codingStandardsIgnoreEnd
        if (!in_array($mode, ['subscribe', 'unsubscribe'])) {
            throw new Exception\InvalidArgumentException(
                'Invalid mode specified: "' . $mode . '" which should have been "subscribe" or "unsubscribe"'
            );
        }

        $params = [
            'hub.mode'  => $mode,
            'hub.topic' => $this->getTopicUrl(),
        ];

        if ($this->getPreferredVerificationMode() === PubSubHubbub::VERIFICATION_MODE_SYNC) {
            $vmodes = [
                PubSubHubbub::VERIFICATION_MODE_SYNC,
                PubSubHubbub::VERIFICATION_MODE_ASYNC,
            ];
        } else {
            $vmodes = [
                PubSubHubbub::VERIFICATION_MODE_ASYNC,
                PubSubHubbub::VERIFICATION_MODE_SYNC,
            ];
        }
        $params['hub.verify'] = [];
        foreach ($vmodes as $vmode) {
            $params['hub.verify'][] = $vmode;
        }

        /**
         * Establish a persistent verify_token and attach key to callback
         * URL's path/query_string
         */
        $key                        = $this->_generateSubscriptionKey($this->getTopicUrl(), $hubUrl);
        $token                      = $this->_generateVerifyToken();
        $params['hub.verify_token'] = $token;

        // Note: query string only usable with PuSH 0.2 Hubs
        if (!$this->usePathParameter) {
            $params['hub.callback'] = $this->getCallbackUrl()
                . '?xhub.subscription=' . PubSubHubbub::urlencode($key);
        } else {
            $params['hub.callback'] = rtrim($this->getCallbackUrl(), '/')
                . '/' . PubSubHubbub::urlencode($key);
        }
        if ($mode === 'subscribe') {
            $params['hub.lease_seconds'] = $this->getLeaseSeconds();
        }

        // hub.secret not currently supported
        $optParams = $this->getParameters();
        foreach ($optParams as $name => $value) {
            $params[$name] = $value;
        }

        return $params;
    }

    /**
     * Simple helper to generate a verification token used in (un)subscribe
     * requests to a Hub Server. Follows no particular method, which means
     * it might be improved/changed in future.
     *
     * @return string
     */
    // @codingStandardsIgnoreStart
    protected function _generateVerifyToken()
    {
        // @codingStandardsIgnoreEnd
        if (!empty($this->testStaticToken)) {
            return $this->testStaticToken;
        }
        return uniqid(rand(), true) . time();
    }

    /**
     * Simple helper to generate a verification token used in (un)subscribe
     * requests to a Hub Server.
     *
     * @param  string  $topicUrl
     * @param  string $hubUrl The Hub Server URL for which this token will apply
     * @return string
     */
    // @codingStandardsIgnoreStart
    protected function _generateSubscriptionKey($topicUrl, $hubUrl)
    {
        // @codingStandardsIgnoreEnd
        $keyBase = $topicUrl . $hubUrl;
        $key     = md5($keyBase);

        return $key;
    }

    /**
     * URL Encode an array of parameters
     *
     * @param  array $params
     * @return array
     */
    // @codingStandardsIgnoreStart
    protected function _urlEncode(array $params)
    {
        // @codingStandardsIgnoreEnd
        $encoded = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $ekey           = PubSubHubbub::urlencode($key);
                $encoded[$ekey] = [];
                foreach ($value as $duplicateKey) {
                    $encoded[$ekey][] = PubSubHubbub::urlencode($duplicateKey);
                }
            } else {
                $encoded[PubSubHubbub::urlencode($key)] = PubSubHubbub::urlencode($value);
            }
        }
        return $encoded;
    }

    /**
     * Order outgoing parameters
     *
     * @param  array $params
     * @return string
     */
    // @codingStandardsIgnoreStart
    protected function _toByteValueOrderedString(array $params)
    {
        // @codingStandardsIgnoreEnd
        $return = [];
        uksort($params, 'strnatcmp');
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $keyduplicate) {
                    $return[] = $key . '=' . $keyduplicate;
                }
            } else {
                $return[] = $key . '=' . $value;
            }
        }
        return implode('&', $return);
    }

    /**
     * Saves subscription state to storage
     *
     * @param string $mode
     * @param string $hubUrl
     * @param array $params
     * @return void
     */
    protected function saveSubscriptionState($mode, $hubUrl, $params)
    {

        $now     = new \DateTime();
        $expires = null;
        if (!array_key_exists('hub.lease_seconds', $params)) {
            $params['hub.lease_seconds'] = null;
        }
        if ($params['hub.lease_seconds'] !== null) {
            $expires = $now->add(new \DateInterval('PT' . $params['hub.lease_seconds'] . 'S'))
                ->format('Y-m-d H:i:s');
        }
        $data = [
            'id'              => $this->_generateSubscriptionKey($params['hub.topic'], $hubUrl),
            'topic_url'       => $params['hub.topic'],
            'hub_url'         => $hubUrl,
            'created_time'    => $now->format('Y-m-d H:i:s'),
            'lease_seconds'   => $params['hub.lease_seconds'],
            'verify_token'    => hash('sha256', $params['hub.verify_token']),
            'secret'          => null,
            'expiration_time' => $expires,
            // @codingStandardsIgnoreStart
            'subscription_state' => ($mode == 'unsubscribe') ? PubSubHubbub::SUBSCRIPTION_TODELETE : PubSubHubbub::SUBSCRIPTION_NOTVERIFIED,
            // @codingStandardsIgnoreEnd
        ];

        $this->getStorage()->setSubscription($data);
    }

    /**
     * Set a header for a given Hub URL
     *
     * @param  string $hubUrl
     * @param  string $header
     * @param  mixed $value
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setHubHeader($hubUrl, $header, $value)
    {
        $this->_validateUrl($hubUrl, 'hubUrl');
        $this->_validateString($header, 'header');

        if ($value === null) {
            $this->removeHubHeader($hubUrl, $header);
        } else {
            $this->hub_headers[$hubUrl][$header] = $value;
        }

        return $this;
    }

    /**
     * Remove a header for a given Hub URL
     *
     * @param  string $hubUrl
     * @param  string $header
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function removeHubHeader($hubUrl, $header)
    {
        $this->_validateUrl($hubUrl, "hubUrl");
        $this->_validateString($header, "header");
        if (array_key_exists($hubUrl, $this->hub_headers)) {
            if (array_key_exists($header, $this->hub_headers[$hubUrl])) {
                unset($this->hub_headers[$hubUrl][$header]);
            }
        }
        return $this;
    }

    /**
     * Set headers for a given Hub URL
     *
     * @param  string $hubUrl Hub URL
     * @param  array $headers An array of headers (name => value)
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setHubHeaders($hubUrl, array $headers)
    {
        $this->_validateUrl($hubUrl, "hubUrl");
        if (array_key_exists($hubUrl, $this->hub_headers)) {
            $this->hub_headers[$hubUrl] = array_merge($this->hub_headers[$hubUrl], $headers);
        } else {
            $this->hub_headers[$hubUrl] = $headers;
        }
        return $this;
    }

    /**
     * Remove all headers for a given Hub URL
     *
     * @param  string $hubUrl     
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function removeHubHeaders($hubUrl)
    {
        $this->_validateUrl($hubUrl, "hubUrl");
        if (array_key_exists($hubUrl, $this->hub_headers)) {
            unset($this->hub_headers[$hubUrl]);
        }
        return $this;
    }

    /**
     * Get all headers for a given Hub URL
     *
     * @param  string $url
     * @return array
     * @throws Exception\InvalidArgumentException
     */
    public function getHubHeaders($hubUrl)
    {
        if (array_key_exists($hubUrl, $this->hub_headers)) {
            return $this->hub_headers[$hubUrl];
        } else {
            return [];
        }
    }

    /**
     * Get all headers for all hubs
     * @return array
     */
    public function getAllHubHeaders()
    {
        return $this->hub_headers;
    }

    /**
     * Set a parameter for a given Hub URL
     *
     * @param  string $hubUrl
     * @param  string $parameter
     * @param  mixed $value
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setHubParameter($hubUrl, $parameter, $value)
    {
        $this->_validateUrl($hubUrl, 'hubUrl');
        $this->_validateString($parameter, 'parameter');

        if ($value === null) {
            $this->removeHubParameter($hubUrl, $parameter);
        } else {
            $this->hub_parameters[$hubUrl][$parameter] = $value;
        }

        return $this;
    }

    /**
     * Remove a parameter for a given Hub URL
     *
     * @param  string $hubUrl
     * @param  string $parameter
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function removeHubParameter($hubUrl, $parameter)
    {
        $this->_validateUrl($hubUrl, "hubUrl");
        $this->_validateString($parameter, "parameter");
        if (array_key_exists($hubUrl, $this->hub_parameters)) {
            if (array_key_exists($parameter, $this->hub_parameters[$hubUrl])) {
                unset($this->hub_parameters[$hubUrl][$parameter]);
            }
        }
        return $this;
    }

    /**
     * Set headers for a given Hub URL
     *
     * @param  string $hubUrl Hub URL
     * @param  array $parameters An array of headers (name => value)
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setHubParameters($hubUrl, array $parameters)
    {
        $this->_validateUrl($hubUrl, "hubUrl");
        if (array_key_exists($hubUrl, $this->hub_parameters)) {
            $this->hub_parameters[$hubUrl] = array_merge($this->hub_parameters[$hubUrl], $parameters);
        } else {
            $this->hub_parameters[$hubUrl] = $parameters;
        }
        return $this;
    }

    /**
     * Remove all parameters for a given Hub URL
     *
     * @param  string $hubUrl     
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function removeHubParameters($hubUrl)
    {
        $this->_validateUrl($hubUrl, "hubUrl");
        if (array_key_exists($hubUrl, $this->hub_parameters)) {
            unset($this->hub_parameters[$hubUrl]);
        }
        return $this;
    }

    /**
     * Get all parameters for a given Hub URL
     *
     * @param  string $url
     * @return array
     * @throws Exception\InvalidArgumentException
     */
    public function getHubParameters($hubUrl)
    {
        if (array_key_exists($hubUrl, $this->hub_parameters)) {
            return $this->hub_parameters[$hubUrl];
        } else {
            return [];
        }
    }

    /**
     * Get all Body parameters for all hubs
     * @return array
     */
    public function getAllHubParameters()
    {
        return $this->hub_parameters;
    }

    /**
     * Add an optional header to the (un)subscribe requests
     *
     * @param  string      $name
     * @param  null|string $value
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setHeader($name, $value = null)
    {
        if (is_array($name)) {
            $this->setHeaders($name);
            return $this;
        }
        $this->_validateString($name, "name");

        if ($value === null) {
            $this->removeHeader($name);
            return $this;
        } else {
            $this->_validateString($value, "value");
            $this->headers[$name] = $value;
            return $this;
        }
    }

    /**
     * Add an optional header to the (un)subscribe requests
     * 
     * @param array $headers
     * @return $this
     */
    public function setHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
        return $this;
    }

    /**
     * Remove an optional parameter for the (un)subscribe requests
     *
     * @param  string $name
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function removeHeader($name)
    {
        $this->_validateString($name, 'name');
        if (array_key_exists($name, $this->headers)) {
            unset($this->headers[$name]);
        }
        return $this;
    }

    /**
     * Return an array of optional headers for (un)subscribe requests
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }


    /**
     * This is STRICTLY for testing purposes only...
     */
    protected $testStaticToken;

    final public function setTestStaticToken($token)
    {
        $this->testStaticToken = (string) $token;
    }
}
