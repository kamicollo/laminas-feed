<?php

/**
 * @see       https://github.com/laminas/laminas-feed for the canonical source repository
 * @copyright https://github.com/laminas/laminas-feed/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-feed/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Feed\PubSubHubbub;

use Laminas\Stdlib\ArrayUtils;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Traversable;

abstract class AbstractCallback implements CallbackInterface
{
    /**
     * An instance of Laminas\Feed\Pubsubhubbub\Model\SubscriptionPersistenceInterface
     * used to background save any verification tokens associated with a subscription
     * or other.
     *
     * @var Model\SubscriptionPersistenceInterface
     */
    protected $storage;

    /**
     * An instance of a class handling Http Responses. PSR-7 compatible.
     *
     * @var ResponseInterface
     */
    protected $httpResponse;

    /**
     * Response Factory (usually the HTTP Client)
     *
     * @var ResponseFactoryInterface
     */
    protected $http_client;

    /**
     * The input stream to use when retrieving the request body. Defaults to
     * php://input, but can be set to another value in order to force usage
     * of another input method. This should primarily be used for testing
     * purposes.
     *
     * @var resource|string String indicates a filename or stream to open;
     *     resource indicates an already created stream to use.
     */
    protected $inputStream = 'php://input';

    /**
     * The number of Subscribers for which any updates are on behalf of.
     *
     * @var int
     */
    protected $subscriberCount = 1;

    /**
     * Constructor; accepts an array or Traversable object to preset
     * options for the Subscriber without calling all supported setter
     * methods in turn.
     *
     * @param null|array|Traversable $options Options array or Traversable object
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
     * @param  array|Traversable $options Options array or Traversable object
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

        if (is_array($options)) {
            $this->setOptions($options);
        }

        if (array_key_exists('storage', $options)) {
            $this->setStorage($options['storage']);
        }
        return $this;
    }

    /**
     * Send the response, including all headers.    
     *     
     * @return void
     */
    public function sendResponse()
    {
        $this->sendHeaders();
        echo $this->getHttpResponse()->getBody();
    }

    /**
     * Sends HTTP headers
     *
     * @return void
     */
    protected function sendHeaders()
    {
        $headers = $this->getHttpResponse()->getHeaders();
        $status_code = $this->getHttpResponse()->getStatusCode();

        if (empty($headers) && (200 == $status_code)) {
            //if empty headers & 200 code - do nothing
            return;
        }
        //check if headers not yet sent, raise an error otherwise
        $this->canSendHeaders(true);
        $httpCodeSent = false;

        //send headers
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                if (!$httpCodeSent && !empty($status_code)) {
                    header(sprintf('%s: %s', $name, $value), false, $status_code);
                } else {
                    header(sprintf('%s: %s', $name, $value), false);
                }
            }
        }
        if (!$httpCodeSent) {
            header(
                'HTTP/'
                    . $this->getHttpResponse()->getProtocolVersion()
                    . ' '
                    . $this->getHttpResponse()->getStatusCode()
            );
        }
    }

    /**
     * Can we send headers?
     *
     * @param  bool $throw Whether or not to throw an exception if headers have been sent; defaults to false
     * @return bool
     * @throws Exception\RuntimeException
     */
    public function canSendHeaders($throw = false)
    {
        $ok = headers_sent($file, $line);
        if ($ok && $throw) {
            throw new Exception\RuntimeException(
                'Cannot send headers; headers already sent in ' . $file . ', line ' . $line
            );
        }
        return !$ok;
    }

    /**
     * Sets an instance of Laminas\Feed\Pubsubhubbub\Model\SubscriptionPersistence used
     * to background save any verification tokens associated with a subscription
     * or other.
     *
     * @return $this
     */
    public function setStorage(Model\SubscriptionPersistenceInterface $storage)
    {
        $this->storage = $storage;
        return $this;
    }

    /**
     * Gets an instance of Laminas\Feed\Pubsubhubbub\Model\SubscriptionPersistence used
     * to background save any verification tokens associated with a subscription
     * or other.
     *
     * @return Model\SubscriptionPersistenceInterface
     * @throws Exception\RuntimeException
     */
    public function getStorage()
    {
        if ($this->storage === null) {
            throw new Exception\RuntimeException(
                'No storage object has been set that subclasses'
                    . ' Laminas\Feed\Pubsubhubbub\Model\SubscriptionPersistence'
            );
        }
        return $this->storage;
    }

    /**
     * An instance of a class handling Http Responses. PSR-7 compatible.
     *
     * @param  ResponseInterface $httpResponse
     * @return $this     
     */
    public function setHttpResponse(ResponseInterface $httpResponse)
    {
        $this->httpResponse = $httpResponse;
        return $this;
    }

    /**
     * An instance of a class handling Http Responses. This is implemented in
     * Laminas\Feed\Pubsubhubbub\HttpResponse which shares an unenforced interface with
     * (i.e. not inherited from) Laminas\Controller\Response\Http.
     *
     * @return ResponseInterface
     */
    public function getHttpResponse()
    {
        if ($this->httpResponse === null) {
            $this->httpResponse = $this->getResponseFactory()->createResponse();
        }
        return $this->httpResponse;
    }

    /**
     * Set HTTP Response Factory
     *
     * @param ResponseFactoryInterface $client
     * @return void
     */
    public function setResponseFactory(ResponseFactoryInterface $client)
    {
        $this->http_client = $client;
    }

    /**
     * Get HTTP Response Factory
     *
     * @return ResponseFactoryInterface
     */
    public function getResponseFactory(): ResponseFactoryInterface
    {
        if ($this->http_client === null) {
            $client = PubSubHubbub::getHttpClient();
            $this->setResponseFactory(new PSR7HTTPClient($client));
        }

        return $this->http_client;
    }

    /**
     * Sets the number of Subscribers for which any updates are on behalf of.
     * In other words, is this class serving one or more subscribers? How many?
     * Defaults to 1 if left unchanged.
     *
     * @param  int|string $count
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setSubscriberCount($count)
    {
        $count = intval($count);
        if ($count <= 0) {
            throw new Exception\InvalidArgumentException(
                'Subscriber count must be'
                    . ' greater than zero'
            );
        }
        $this->subscriberCount = $count;
        return $this;
    }

    /**
     * Gets the number of Subscribers for which any updates are on behalf of.
     * In other words, is this class serving one or more subscribers? How many?
     *
     * @return int
     */
    public function getSubscriberCount()
    {
        return $this->subscriberCount;
    }

    /**
     * Attempt to detect the callback URL (specifically the path forward)
     *
     * @return string
     */
    // @codingStandardsIgnoreStart
    protected function _detectCallbackUrl()
    {
        // @codingStandardsIgnoreEnd
        $callbackUrl = null;

        // IIS7 with URL Rewrite: make sure we get the unencoded url
        // (double slash problem).
        $iisUrlRewritten = isset($_SERVER['IIS_WasUrlRewritten']) ? $_SERVER['IIS_WasUrlRewritten'] : null;
        $unencodedUrl    = isset($_SERVER['UNENCODED_URL']) ? $_SERVER['UNENCODED_URL'] : null;
        if ('1' == $iisUrlRewritten && !empty($unencodedUrl)) {
            return $unencodedUrl;
        }

        // HTTP proxy requests setup request URI with scheme and host [and port]
        // + the URL path, only use URL path.
        if (isset($_SERVER['REQUEST_URI'])) {
            $callbackUrl = $this->buildCallbackUrlFromRequestUri();
        }

        if (null !== $callbackUrl) {
            return $callbackUrl;
        }

        if (isset($_SERVER['ORIG_PATH_INFO'])) {
            return $this->buildCallbackUrlFromOrigPathInfo();
        }

        return '';
    }

    /**
     * Get the HTTP host
     *
     * @return string
     */
    // @codingStandardsIgnoreStart
    protected function _getHttpHost()
    {
        // @codingStandardsIgnoreEnd
        if (!empty($_SERVER['HTTP_HOST'])) {
            return $_SERVER['HTTP_HOST'];
        }

        $https  = isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : null;
        $scheme = $https === 'on' ? 'https' : 'http';
        $name   = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
        $port   = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 80;

        if (($scheme === 'http' && $port === 80)
            || ($scheme === 'https' && $port === 443)
        ) {
            return $name;
        }

        return sprintf('%s:%d', $name, $port);
    }

    /**
     * Retrieve a Header value from either $_SERVER or Apache
     *
     * @param  string $header
     * @return bool|string
     */
    // @codingStandardsIgnoreStart
    protected function _getHeader($header)
    {
        // @codingStandardsIgnoreEnd
        $temp = strtoupper(str_replace('-', '_', $header));
        if (!empty($_SERVER[$temp])) {
            return $_SERVER[$temp];
        }
        $temp = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        if (!empty($_SERVER[$temp])) {
            return $_SERVER[$temp];
        }
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (!empty($headers[$header])) {
                return $headers[$header];
            }
        }
        return false;
    }

    /**
     * Return the raw body of the request
     *
     * @return false|string Raw body, or false if not present
     */
    // @codingStandardsIgnoreStart
    protected function _getRawBody()
    {
        // @codingStandardsIgnoreEnd
        $body = is_resource($this->inputStream)
            ? stream_get_contents($this->inputStream)
            : file_get_contents($this->inputStream);

        return strlen(trim($body)) > 0 ? $body : false;
    }

    /**
     * Build the callback URL from the REQUEST_URI server parameter.
     *
     * @return string
     */
    private function buildCallbackUrlFromRequestUri()
    {
        $callbackUrl = $_SERVER['REQUEST_URI'];
        $https       = isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : null;
        $scheme      = $https === 'on' ? 'https' : 'http';
        if ($https === 'on') {
            $scheme = 'https';
        }
        $schemeAndHttpHost = $scheme . '://' . $this->_getHttpHost();
        if (strpos($callbackUrl, $schemeAndHttpHost) === 0) {
            $callbackUrl = substr($callbackUrl, strlen($schemeAndHttpHost));
        }
        return $callbackUrl;
    }

    /**
     * Build the callback URL from the ORIG_PATH_INFO server parameter.
     *
     * @return string
     */
    private function buildCallbackUrlFromOrigPathInfo()
    {
        $callbackUrl = $_SERVER['ORIG_PATH_INFO'];
        if (!empty($_SERVER['QUERY_STRING'])) {
            $callbackUrl .= '?' . $_SERVER['QUERY_STRING'];
        }
        return $callbackUrl;
    }
}
