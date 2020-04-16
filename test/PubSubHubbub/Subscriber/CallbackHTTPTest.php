<?php

/**
 * @see       https://github.com/laminas/laminas-feed for the canonical source repository
 * @copyright https://github.com/laminas/laminas-feed/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-feed/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Feed\PubSubHubbub\Subscriber;

use DateInterval;
use DateTimeImmutable;
use Laminas\Diactoros\StreamFactory;
use Laminas\Feed\PubSubHubbub\Exception\RuntimeException;
use Laminas\Feed\PubSubHubbub\Model\Subscription;
use Laminas\Feed\PubSubHubbub\PubSubHubbub;
use Laminas\Feed\PubSubHubbub\Subscriber\Callback as CallbackSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;

/**
 * @group Laminas_Feed
 * @group Laminas_Feed_Subsubhubbub
 */
class CallbackHTTPTest extends TestCase
{
    public function setUp(): void
    {
        $this->now = new DateTimeImmutable();
        $this->future = $this->now->add(new \DateInterval('P50D'));
        $this->default_db['created_time'] = $this->now->format('Y-m-d H:i:s');

        $this->_callback = new CallbackSubscriber();
        $this->_callback->setStorage($this->_setupDB());
        $this->_callback->setRequest($this->_setupRequest());
        $this->_callback->setSubscriptionKey('subscriptionkey');

        $this->_callback->setTimeNow($this->future);
    }

    /**
     * Undocumented variable
     *
     * @var DateTimeImmutable
     */
    protected $now;

    /**
     * Undocumented variable
     *
     * @var DateTimeImmutable
     */
    protected $future;

    /**
     * @var MockObject
     */
    protected $_storage;

    /**
     * Undocumented variable
     *
     * @var CallbackSubscriber
     */
    protected $_callback;

    protected $default_params = [
        'hub_mode'          => 'subscribe',
        'hub_topic'         => 'http://www.example.com/topic',
        'hub_challenge'     => 'abc',
        'hub_verify_token'  => 'cba',
        'hub_lease_seconds' => '1234567',
    ];

    protected $default_method = 'GET';

    protected $default_uri = '/some/path/callback/subscriptionkey';

    protected function _setupRequest(
        $params = null,
        $method = null,
        $headerline = null,
        $uri = null,
        StreamInterface $body = null
    ) {
        if ($params === null) {
            $params = $this->default_params;
        }
        if ($method === null) {
            $method = $this->default_method;
        }
        if ($uri === null) {
            $uri = $this->default_uri;
        }

        $request = $this->_getCleanMock(ServerRequestInterface::class);

        $request->expects($this->any())
            ->method('getQueryParams')
            ->will($this->returnValue($params));

        $request->expects($this->any())
            ->method('getMethod')
            ->will($this->returnValue($method));

        if (is_array($headerline)) {
            $request->expects($this->any())
                ->method('getHeaderLine')
                ->will($this->returnValueMap(
                    $headerline
                ));
        }
        if ($uri !== null) {
            $request->expects($this->any())
                ->method('getUri')
                ->will($this->returnValue($uri));
        }
        if ($body !== null) {
            $request->expects($this->any())
                ->method('getBody')
                ->will($this->returnValue($body));
        }
        return $request;
    }

    protected function getStream($file)
    {
        return (new StreamFactory())->createStreamFromFile($file);
    }

    protected $default_db = [
        'id'            => 'subscriptionkey',
        'topic_url'         => 'http://www.example.com/topic',
        'hub_url'         => 'http://hub.com',
        'hub_protocol'      => PubSubHubbub::PROTOCOL03,
        'lease_seconds' => null,
        'subscription_state' => PubSubHubbub::SUBSCRIPTION_NOTVERIFIED,
        'verify_token' => '6d970874d0db767a7058798973f22cf6589601edab57996312f2ef7b56e5584d',
        //SHA256 of 'cba'        
        'created_time'    => null,
        'secret'          => null,
        'expiration_time' => null
    ];

    protected function _setupDB($db_values = [])
    {

        $storage = $this->_getCleanMock(Subscription::class);
        $values = array_merge($this->default_db, $db_values);

        $storage->expects($this->any())
            ->method('getSubscription')
            ->will($this->returnValueMap(
                [
                    [$values['id'], $values],
                    ['wrongkey', false],
                ]
            ));

        $storage->expects($this->any())
            ->method('hasSubscription')
            ->will($this->returnValueMap(
                [
                    [$values['id'], true],
                    ['wrongkey', false],
                ]
            ));
        return $storage;
    }

    // @codingStandardsIgnoreStart
    protected function _getCleanMock($className)
    {
        // @codingStandardsIgnoreEnd
        $class       = new ReflectionClass($className);
        $methods     = $class->getMethods();
        $stubMethods = [];
        foreach ($methods as $method) {
            if (
                $method->isPublic()
                || ($method->isProtected() && $method->isAbstract())
            ) {
                $stubMethods[] = $method->getName();
            }
        }
        $mocked = $this->getMockBuilder($className)
            ->setMethods($stubMethods)
            ->setConstructorArgs([])
            ->setMockClassName(str_replace('\\', '_', $className . '_PubsubSubscriberMock_' . uniqid()))
            ->disableOriginalConstructor()
            ->getMock();
        return $mocked;
    }

    ######### TEST Identification of subscriptions #############

    public function testIdentifiesSubscriptionWithKey()
    {
        $this->assertEquals(true, $this->_callback->setupSubscription());
    }

    public function testIdentifiesWrongSubscriptionWithKey()
    {
        $this->_callback->setSubscriptionKey('wrongkey');
        $this->assertEquals(false, $this->_callback->setupSubscription());
    }

    public function testMissingSubscriptionIdentifier()
    {
        $this->_callback->setSubscriptionKey(null);
        $this->expectException(RuntimeException::class);
        $this->assertEquals(true, $this->_callback->setupSubscription());
    }

    public function testIdentifiesSubscriptionFromQueryString()
    {
        $this->_callback->setSubscriptionKey(null);
        $request = $this->_setupRequest(['xhub_subscription' => 'subscriptionkey']);
        $this->_callback->setRequest($request);
        $this->assertEquals(true, $this->_callback->setupSubscription());
    }

    public function testIdentifiesNotExistingSubscriptionFromQueryString()
    {
        $this->_callback->setSubscriptionKey(null);
        $request = $this->_setupRequest(['xhub_subscription' => 'wrongkey']);
        $this->_callback->setRequest($request);
        $this->assertEquals(false, $this->_callback->setupSubscription());
    }

    ######### TEST well-formedness of hub verification requests #############

    public function testReturnsFalseIfModeMissingFromHttpGetData()
    {
        $params = $this->default_params;
        unset($params['hub_mode']);
        $request = $this->_setupRequest($params);
        $this->_callback->handle($request);
        $this->assertEquals(404, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testReturnsFalseIfTopicMissingFromHttpGetData()
    {
        $params = $this->default_params;
        unset($params['hub_topic']);
        $request = $this->_setupRequest($params);
        $this->_callback->handle($request);
        $this->assertEquals(404, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testReturnsFalseIfChallengeMissingFromHttpGetData()
    {
        $params = $this->default_params;
        unset($params['hub_challenge']);
        $request = $this->_setupRequest($params);

        $this->_callback->handle($request);
        $this->assertEquals(404, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testReturnsFalseIfVerifyTokenMissingFromHttpGetData()
    {
        $params = $this->default_params;
        unset($params['hub_verify_token']);
        $request = $this->_setupRequest($params);
        $this->_callback->handle($request);
        $this->assertEquals(404, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testReturnsTrueIfVerifyTokenMissingFromHttpGetDataButPSHB4()
    {
        $params = $this->default_params;
        unset($params['hub_verify_token']);
        $request = $this->_setupRequest($params);
        $db_params = $this->default_db;
        $db_params['hub_protocol'] = PubSubHubbub::PROTOCOL04;
        $db = $this->_setupDB($db_params);
        $this->_callback->setStorage($db);

        $this->_callback->handle($request);
        $this->assertEquals(200, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testReturnsFalseIfModeNotRecognisedFromHttpGetData()
    {
        $params = $this->default_params;
        $params['hub_mode'] = 'denied';
        $request = $this->_setupRequest($params);
        $this->_callback->handle($request);
        $this->assertEquals(404, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testReturnsTrueIfModeDenialButPSHB4()
    {
        $params = $this->default_params;
        $params['hub_mode'] = 'denied';
        $request = $this->_setupRequest($params);
        $db_params = $this->default_db;
        $db_params['hub_protocol'] = PubSubHubbub::PROTOCOL04;
        $db = $this->_setupDB($db_params);
        $this->_callback->setStorage($db);

        $this->_callback->handle($request);
        $this->assertEquals(200, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testReturnsFalseIfLeaseSecondsMissedWhenModeIsSubscribeFromHttpGetData()
    {
        $params = $this->default_params;
        unset($params['hub_lease_seconds']);
        $request = $this->_setupRequest($params);
        $this->_callback->handle($request);
        $this->assertEquals(404, $this->_callback->getHttpResponse()->getStatusCode());
    }

    ######### TEST that data sent corresponds to our database #############

    public function testReturnsFalseIfVerifyTokenIncorrect()
    {
        $params = $this->default_params;
        $params['hub_verify_token'] = 'wrongtoken';
        $request = $this->_setupRequest($params);
        $this->_callback->handle($request);
        $this->assertEquals(404, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testReturnsFalseIfHubNotRequestedToSubscribe()
    {
        $db_params = $this->default_db;
        $db_params['subscription_state'] = PubSubHubbub::SUBSCRIPTION_TODELETE;
        $storage = $this->_setupDB($db_params);
        $this->_callback->setStorage($storage);

        $params = $this->default_params;
        $request = $this->_setupRequest($params);
        $this->_callback->handle($request);
        $this->assertEquals(404, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testReturnsFalseIfHubNotRequestedToUnSubscribeToVerify()
    {
        $params = $this->default_params;
        $params['hub_mode'] = 'unsubscribe';
        $request = $this->_setupRequest($params);
        $this->_callback->handle($request);
        $this->assertEquals(404, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testReturnsFalseIfHubNotRequestedToUnSubscribeVerified()
    {
        $db_params = $this->default_db;
        $db_params['subscription_state'] = PubSubHubbub::SUBSCRIPTION_VERIFIED;
        $storage = $this->_setupDB($db_params);
        $this->_callback->setStorage($storage);

        $params = $this->default_params;
        $params['hub_mode'] = 'unsubscribe';
        $request = $this->_setupRequest($params);
        $this->_callback->handle($request);
        $this->assertEquals(404, $this->_callback->getHttpResponse()->getStatusCode());
    }

    ######### TEST successfull requests #############

    public function testReturns200ForSubscriptionRequest()
    {
        $this->_callback->handle();
        $this->assertEquals(200, $this->_callback->getHttpResponse()->getStatusCode());
        $this->assertEquals('abc', $this->_callback->getHttpResponse()->getBody());
    }

    public function testReturns200ForResubscriptionRequest()
    {
        $db_params = $this->default_db;
        $db_params['subscription_state'] = PubSubHubbub::SUBSCRIPTION_VERIFIED;
        $storage = $this->_setupDB($db_params);
        $this->_callback->setStorage($storage);
        $this->_callback->handle();
        $this->assertEquals('abc', $this->_callback->getHttpResponse()->getBody());
        $this->assertEquals(200, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testReturns200ForUnsubscriptionRequest()
    {
        $db_params = $this->default_db;
        $db_params['subscription_state'] = PubSubHubbub::SUBSCRIPTION_TODELETE;
        $storage = $this->_setupDB($db_params);
        $this->_callback->setStorage($storage);

        $params = $this->default_params;
        $params['hub_mode'] = 'unsubscribe';
        $request = $this->_setupRequest($params);
        $this->_callback->handle($request);
        $this->assertEquals('abc', $this->_callback->getHttpResponse()->getBody());
        $this->assertEquals(200, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testUpdatesDatabaseForUnSubscriptionRequests()
    {

        $db_params = $this->default_db;
        $db_params['subscription_state'] = PubSubHubbub::SUBSCRIPTION_TODELETE;
        $storage = $this->_setupDB($db_params);
        $this->_callback->setStorage($storage);
        $storage->expects($this->once())
            ->method('deleteSubscription')
            ->with('subscriptionkey');

        $params = $this->default_params;
        $params['hub_mode'] = 'unsubscribe';
        $request = $this->_setupRequest($params);
        $this->_callback->handle($request);
        $this->assertEquals('abc', $this->_callback->getHttpResponse()->getBody());
        $this->assertEquals(200, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testUpdatesDatabaseForSubscriptionRequestsDenied()
    {
        $db_params = $this->default_db;
        $db_params['subscription_state'] = PubSubHubbub::SUBSCRIPTION_NOTVERIFIED;
        $db_params['hub_protocol'] = PubSubHubbub::PROTOCOL04;
        $storage = $this->_setupDB($db_params);
        $this->_callback->setStorage($storage);

        $db_params['subscription_state'] = PubSubHubbub::SUBSCRIPTION_DENIED;
        $storage->expects($this->once())
            ->method('setSubscription')
            ->with($this->equalTo($db_params));

        $params = $this->default_params;
        $params['hub_mode'] = 'denied';
        $request = $this->_setupRequest($params);
        $this->_callback->handle($request);
        $this->assertEquals(200, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testUpdatesDatabaseForSubscriptionRequests()
    {
        $db = $this->_setupDB();
        $db_params = $this->default_db;
        $db_params['subscription_state'] = PubSubHubbub::SUBSCRIPTION_VERIFIED;
        $db_params['lease_seconds'] = $this->default_params['hub_lease_seconds'];
        $db_params['expiration_time'] = $this->now->add(
            new \DateInterval('PT' . $this->default_params['hub_lease_seconds'] . 'S')
        )->format('Y-m-d H:i:s');

        $db->expects($this->once())
            ->method('setSubscription')
            ->with($this->equalTo($db_params));

        $this->_callback->setStorage($db);

        $this->_callback->handle();
        $this->assertEquals('abc', $this->_callback->getHttpResponse()->getBody());
        $this->assertEquals(200, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testUpdatesDatabaseForReSubscriptionRequests()
    {
        $db_params = $this->default_db;
        $db_params['subscription_state'] = PubSubHubbub::SUBSCRIPTION_VERIFIED;
        $db = $this->_setupDB($db_params);

        $db_params['lease_seconds'] = $this->default_params['hub_lease_seconds'];
        $db_params['expiration_time'] = $this->future->add(
            new \DateInterval('PT' . $this->default_params['hub_lease_seconds'] . 'S')
        )->format('Y-m-d H:i:s');

        $db->expects($this->once())
            ->method('setSubscription')
            ->with($this->equalTo($db_params));

        $this->_callback->setStorage($db);

        $this->_callback->handle();
        $this->assertEquals('abc', $this->_callback->getHttpResponse()->getBody());
        $this->assertEquals(200, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testUpdatesDatabaseForReSubscriptionRequestsNoLeaseSeconds()
    {
        $db_params = $this->default_db;
        $db_params['subscription_state'] = PubSubHubbub::SUBSCRIPTION_VERIFIED;
        $db = $this->_setupDB($db_params);

        $db_params['expiration_time'] = null;
        $db_params['lease_seconds'] = null;

        $db->expects($this->once())
            ->method('setSubscription')
            ->with($this->equalTo($db_params));

        $this->_callback->setStorage($db);

        $params = $this->default_params;
        $params['hub_lease_seconds'] = null;
        $request = $this->_setupRequest($params);
        $this->_callback->handle($request);
        $this->assertEquals('abc', $this->_callback->getHttpResponse()->getBody());
        $this->assertEquals(200, $this->_callback->getHttpResponse()->getStatusCode());
    }

    ######### FEED UPDATE TESTS #############

    //do we respond with 200?
    public function testRespondsWith200Response()
    {
        $request = $this->_setupRequest(
            [],
            'POST',
            [
                ['Content-Type', 'application/atom+xml']
            ],
            null,
            $this->getStream(__DIR__ . '/_files/atom10.xml')
        );

        $this->_callback->handle($request);
        $this->assertEquals(200, $this->_callback->getHttpResponse()->getStatusCode());
    }

    //do we capture content properly?
    public function testCapturesUpdateContent()
    {
        $request = $this->_setupRequest(
            [],
            'POST',
            [
                ['Content-Type', 'application/atom+xml']
            ],
            null,
            $this->getStream(__DIR__ . '/_files/atom10.xml')
        );

        $this->_callback->handle($request);
        $this->assertInstanceOf(StreamInterface::class, $this->_callback->getContent());
        $this->assertEquals(
            $this->getStream(__DIR__ . '/_files/atom10.xml')->getContents(),
            $this->_callback->getContent()->getContents()
        );
    }

    //do we capture feed content properly?
    public function testCapturesUpdateFeed()
    {
        $request = $this->_setupRequest(
            [],
            'POST',
            [
                ['Content-Type', 'application/atom+xml']
            ],
            null,
            $this->getStream(__DIR__ . '/_files/atom10.xml')
        );

        $this->_callback->handle($request);
        $this->assertInstanceOf(StreamInterface::class, $this->_callback->getFeedUpdate());
        $this->assertEquals(
            $this->getStream(__DIR__ . '/_files/atom10.xml')->getContents(),
            $this->_callback->getFeedUpdate()->getContents()
        );
        $this->assertEquals(true, $this->_callback->hasFeedUpdate());
    }

    //do we capture HTML content properly?
    public function testNotCapturesUpdateHTMLAsFeed()
    {
        $request = $this->_setupRequest(
            [],
            'POST',
            [
                ['Content-Type', 'application/html']
            ],
            null,
            $this->getStream(__DIR__ . '/_files/atom10.xml')
        );

        $this->_callback->handle($request);

        $this->assertInstanceOf(StreamInterface::class, $this->_callback->getContent());
        $this->assertEquals(
            $this->getStream(__DIR__ . '/_files/atom10.xml')->getContents(),
            $this->_callback->getContent()->getContents()
        );

        $this->assertEquals(
            null,
            $this->_callback->getFeedUpdate()
        );
        $this->assertEquals(false, $this->_callback->hasFeedUpdate());
    }

    public function testRespondsToInvalidFeedUpdateNotPostWith404Response()
    {
        // yes, this example makes no sense for GET - I know!!!
        $request = $this->_setupRequest(
            [],
            'GET',
            [
                ['Content-Type', 'application/atom+xml']
            ],
            '/some/path/callback/verifytokenkey',
            $this->getStream(__DIR__ . '/_files/atom10.xml')
        );
        $this->_callback->handle($request);
        $this->assertEquals(404, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testRespondsToValidFeedUpdateWithXHubOnBehalfOfHeader()
    {
        $request = $this->_setupRequest(
            [],
            'POST',
            [
                ['Content-Type', 'application/atom+xml']
            ],
            null,
            $this->getStream(__DIR__ . '/_files/atom10.xml')
        );

        $this->_callback->handle($request);
        $header = $this->_callback->getHttpResponse()->getHeader('X-Hub-On-Behalf-Of');
        $this->assertEquals([1], $header);
    }

    public function testNeedsAuthentication()
    {
        $db_params = $this->default_db;
        $db_params['secret'] = 'secret';
        $db = $this->_setupDB($db_params);
        $this->_callback->setStorage($db);
        $this->_callback->handle();
        $this->assertEquals(true, $this->_callback->needsAuthentication());
    }

    public function testDoesNotNeedAuthentication()
    {
        $db_params = $this->default_db;
        $db_params['secret'] = null;
        $db = $this->_setupDB($db_params);
        $this->_callback->setStorage($db);
        $this->_callback->handle();
        $this->assertEquals(false, $this->_callback->needsAuthentication());
    }

    public function testAuthenticatedDistribution()
    {
        $db_params = $this->default_db;
        $db_params['secret'] = 'secret';
        $db = $this->_setupDB($db_params);
        $this->_callback->setStorage($db);

        $hmac = 'sha1='
            . hash_hmac('sha1', file_get_contents(__DIR__ . '/_files/atom10.xml'), 'secret');
        $request = $this->_setupRequest(
            [],
            'POST',
            [
                ['X-Hub-Signature', $hmac],
                ['Content-Type', 'application/atom+xml']
            ],
            null,
            $this->getStream(__DIR__ . '/_files/atom10.xml')
        );

        $this->_callback->handle($request);
        $this->assertEquals(true, $this->_callback->authenticateContent());
    }

    public function testAuthenticatedDistributionWrongSecret()
    {
        $db_params = $this->default_db;
        $db_params['secret'] = 'secret';
        $db = $this->_setupDB($db_params);
        $this->_callback->setStorage($db);

        $hmac = 'sha1='
            . hash_hmac('sha1', file_get_contents(__DIR__ . '/_files/atom10.xml'), 'wrongsecret');
        $request = $this->_setupRequest(
            [],
            'POST',
            [
                ['X-Hub-Signature', $hmac],
                ['Content-Type', 'application/atom+xml']
            ],
            null,
            $this->getStream(__DIR__ . '/_files/atom10.xml')
        );

        $this->_callback->handle($request);
        $this->assertEquals(false, $this->_callback->authenticateContent());
    }

    public function testAuthenticatedDistributionNoSecret()
    {
        $db_params = $this->default_db;
        $db_params['secret'] = 'secret';
        $db = $this->_setupDB($db_params);
        $this->_callback->setStorage($db);


        $request = $this->_setupRequest(
            [],
            'POST',
            [
                ['Content-Type', 'application/atom+xml']
            ],
            null,
            $this->getStream(__DIR__ . '/_files/atom10.xml')
        );

        $this->_callback->handle($request);

        $this->assertEquals(false, $this->_callback->authenticateContent());
    }

    public function testAuthenticatedDistributionHasNoSecret()
    {
        $hmac = 'sha1='
            . hash_hmac('sha1', file_get_contents(__DIR__ . '/_files/atom10.xml'), 'randomsecret');
        $request = $this->_setupRequest(
            [],
            'POST',
            [
                ['X-Hub-Signature', $hmac],
                ['Content-Type', 'application/atom+xml']
            ],
            null,
            $this->getStream(__DIR__ . '/_files/atom10.xml')
        );

        $this->_callback->handle($request);
        $this->assertEquals(200, $this->_callback->getHttpResponse()->getStatusCode());
        $this->assertEquals(true, $this->_callback->authenticateContent());
    }
}
