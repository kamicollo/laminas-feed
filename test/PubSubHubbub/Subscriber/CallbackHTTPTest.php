<?php

/**
 * @see       https://github.com/laminas/laminas-feed for the canonical source repository
 * @copyright https://github.com/laminas/laminas-feed/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-feed/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Feed\PubSubHubbub\Subscriber;

use ArrayObject;
use DateInterval;
use DateTime;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Diactoros\StreamFactory;
use Laminas\Feed\PubSubHubbub\AbstractCallback;
use Laminas\Feed\PubSubHubbub\Exception\ExceptionInterface;
use Laminas\Feed\PubSubHubbub\Exception\RuntimeException;
use Laminas\Feed\PubSubHubbub\Model;
use Laminas\Feed\PubSubHubbub\PSR7HTTPClient;
use Laminas\Feed\PubSubHubbub\PubSubHubbub;
use Laminas\Feed\PubSubHubbub\Subscriber\Callback as CallbackSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use TypeError;

/**
 * @group Laminas_Feed
 * @group Laminas_Feed_Subsubhubbub
 */
class CallbackHTTPTest extends TestCase
{
    public function setUp(): void
    {
        $this->_callback = new CallbackSubscriber();

        $this->_adapter      = $this->_getCleanMock(
            Adapter::class
        );
        $this->_tableGateway = $this->_getCleanMock(
            TableGateway::class
        );
        $this->_rowset       = $this->_getCleanMock(
            ResultSet::class
        );

        $this->_tableGateway->expects($this->any())
            ->method('getAdapter')
            ->will($this->returnValue($this->_adapter));
        $storage = new Model\Subscription($this->_tableGateway);

        $this->now = new DateTime();
        $storage->setNow(clone $this->now);

        $this->_callback->setStorage($storage);

        $this->_callback->setRequest($this->_setupRequest());

        //$_SERVER['QUERY_STRING']   = 'xhub.subscription=verifytokenkey';
    }

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

    protected function getStream($file)
    {
        return (new StreamFactory())->createStreamFromFile($file);
    }

    protected $default_db = [
        'id'            => 'subscriptionkey',
        'topic'         => 'http://www.example.com/topic',
        'lease_seconds' => '1234567',
        'verify_token' => '6d970874d0db767a7058798973f22cf6589601edab57996312f2ef7b56e5584d',
        //SHA256 of 'cba'
    ];


    protected function _setupDB($db_values = [])
    {

        $mockReturnValue = $this->getMockBuilder('Result')->setMethods(['getArrayCopy'])->getMock();

        $values = array_merge($this->default_db, $db_values);
        $mockReturnValue->expects($this->any())
            ->method('getArrayCopy')
            ->will($this->returnValue($values));

        $this->_tableGateway->expects($this->any())
            ->method('select')
            ->will($this->returnValueMap(
                [
                    [['id' => $values['id']], $this->_rowset],
                    [['id' => 'wrongkey'], false],
                ]
            ));


        $this->_rowset->expects($this->any())
            ->method('current')
            ->will($this->returnValue($mockReturnValue));

        // require for the count call on the rowset in Model/Subscription
        $this->_rowset->expects($this->any())
            ->method('count')
            ->will($this->returnValue(1));
    }

    ######### TEST Identification of subscriptions #############

    public function testIdentifiesSubscriptionWithKey()
    {
        $this->_callback->setSubscriptionKey('subscriptionkey');
        $this->_setupDB();
        $this->assertEquals(true, $this->_callback->setupSubscription());
    }

    public function testIdentifiesWrongSubscriptionWithKey()
    {
        $this->_callback->setSubscriptionKey('wrongkey');
        $this->_setupDB();
        $this->assertEquals(false, $this->_callback->setupSubscription());
    }

    public function testMissingSubscriptionIdentifier()
    {
        $this->_setupDB();
        $this->expectException(RuntimeException::class);
        $this->assertEquals(true, $this->_callback->setupSubscription());
    }

    public function testIdentifiesSubscriptionFromQueryString()
    {
        $this->_setupDB();
        $request = $this->_setupRequest(['xhub_subscription' => 'subscriptionkey']);
        $this->_callback->setRequest($request);
        $this->assertEquals(true, $this->_callback->setupSubscription());
    }

    public function testIdentifiesNotExistingSubscription()
    {
        $this->markTestIncomplete(
            'Needs review.'
        );
        $this->_setupDB();
        $this->assertEquals(false, $this->_callback->setupSubscription());
    }



    ######### SUBSCRIPTION/UNSUBSCRIPTION TESTS #############

    /**
     * @group Laminas_CONFLICT
     */
    public function testValidatesValidHttpGetData()
    {
        $this->markTestIncomplete(
            'Needs review.'
        );

        $mockReturnValue = $this->getMockBuilder('Result')->setMethods(['getArrayCopy'])->getMock();
        $mockReturnValue->expects($this->any())
            ->method('getArrayCopy')
            ->will($this->returnValue([
                'verify_token' => hash('sha256', 'cba'),
            ]));

        $this->_tableGateway->expects($this->any())
            ->method('select')
            ->with($this->equalTo(['id' => 'verifytokenkey']))
            ->will($this->returnValue($this->_rowset));
        $this->_rowset->expects($this->any())
            ->method('current')
            ->will($this->returnValue($mockReturnValue));
        $this->_rowset->expects($this->any())
            ->method('count')
            ->will($this->returnValue(1));

        $this->assertTrue($this->_callback->isValidHubVerification());
    }

    public function testReturnsFalseIfHubVerificationNotAGetRequest()
    {
        $this->markTestIncomplete(
            'Needs review.'
        );
        $request = $this->_setupRequest(null, 'POST');
        $this->assertFalse($this->_callback->isValidHubVerification($request));
    }

    public function testReturnsFalseIfModeMissingFromHttpGetData()
    {
        $this->markTestIncomplete(
            'Needs review.'
        );
        $params = $this->default_params;
        unset($params['hub_mode']);
        $request = $this->_setupRequest($params);
        $this->_callback->setRequest($request);

        $this->assertFalse($this->_callback->isValidHubVerification());
    }

    public function testReturnsFalseIfTopicMissingFromHttpGetData()
    {
        $this->markTestIncomplete(
            'Needs review.'
        );
        $params = $this->default_params;
        unset($params['hub_topic']);
        $request = $this->_setupRequest($params);
        $this->_callback->setRequest($request);

        $this->assertFalse($this->_callback->isValidHubVerification());
    }

    public function testReturnsFalseIfChallengeMissingFromHttpGetData()
    {
        $this->markTestIncomplete(
            'Needs review.'
        );
        $params = $this->default_params;
        unset($params['hub_challenge']);
        $request = $this->_setupRequest($params);
        $this->_callback->setRequest($request);

        $this->assertFalse($this->_callback->isValidHubVerification());
    }

    public function testReturnsFalseIfVerifyTokenMissingFromHttpGetData()
    {
        $this->markTestIncomplete(
            'Needs review.'
        );
        $params = $this->default_params;
        unset($params['hub_verify_token']);
        $request = $this->_setupRequest($params);
        $this->_callback->setRequest($request);

        $this->assertFalse($this->_callback->isValidHubVerification());
    }

    public function testReturnsTrueIfModeSetAsUnsubscribeFromHttpGetData()
    {
        $this->markTestIncomplete(
            'Needs review.'
        );
        $mockReturnValue = $this->getMockBuilder('Result')->setMethods(['getArrayCopy'])->getMock();
        $mockReturnValue->expects($this->any())
            ->method('getArrayCopy')
            ->will($this->returnValue([
                'verify_token' => hash('sha256', 'cba'),
            ]));


        $this->_tableGateway->expects($this->any())
            ->method('select')
            ->with($this->equalTo(['id' => 'verifytokenkey']))
            ->will($this->returnValue($this->_rowset));
        $this->_rowset->expects($this->any())
            ->method('current')
            ->will($this->returnValue($mockReturnValue));
        // require for the count call on the rowset in Model/Subscription
        $this->_rowset->expects($this->any())
            ->method('count')
            ->will($this->returnValue(1));

        $params = $this->default_params;
        $params['hub_mode'] = 'unsubscribe';
        $request = $this->_setupRequest($params);
        $this->_callback->setRequest($request);

        $this->assertTrue($this->_callback->isValidHubVerification());
    }

    public function testReturnsFalseIfModeNotRecognisedFromHttpGetData()
    {
        $this->markTestIncomplete(
            'Needs review.'
        );
        $params = $this->default_params;
        $params['hub_mode'] = 'abc';
        $request = $this->_setupRequest($params);
        $this->_callback->setRequest($request);
        $this->assertFalse($this->_callback->isValidHubVerification());
    }

    public function testReturnsFalseIfLeaseSecondsMissedWhenModeIsSubscribeFromHttpGetData()
    {
        $this->markTestIncomplete(
            'Needs review.'
        );
        $params = $this->default_params;
        unset($params['hub_lease_seconds']);
        $request = $this->_setupRequest($params);
        $this->_callback->setRequest($request);
        $this->assertFalse($this->_callback->isValidHubVerification());
    }

    public function testReturnsFalseIfHubTopicInvalidFromHttpGetData()
    {
        $this->markTestIncomplete(
            'Needs review.'
        );
        $params = $this->default_params;
        $params['hub_topic'] = 'http://';
        $request = $this->_setupRequest($params);
        $this->_callback->setRequest($request);
        $this->assertFalse($this->_callback->isValidHubVerification());
    }

    public function testReturnsFalseIfVerifyTokenRecordDoesNotExistForConfirmRequest()
    {
        $this->markTestIncomplete(
            'Needs review.'
        );
        $this->assertFalse($this->_callback->isValidHubVerification());
    }

    public function testReturnsFalseIfVerifyTokenRecordDoesNotAgreeWithConfirmRequest()
    {
        $this->markTestIncomplete(
            'Needs review.'
        );
        $this->assertFalse($this->_callback->isValidHubVerification());
    }

    public function testRespondsToInvalidConfirmationWith404Response()
    {
        $this->markTestIncomplete(
            'Needs review.'
        );
        $params = $this->default_params;
        unset($params['hub_mode']);
        $request = $this->_setupRequest($params);
        $this->_callback->handle($request);
        $this->assertEquals(404, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testRespondsToValidConfirmationWith200Response()
    {

        $this->markTestIncomplete(
            'Needs review.'
        );
        $this->_tableGateway->expects($this->any())
            ->method('select')
            ->with($this->equalTo(['id' => 'verifytokenkey']))
            ->will($this->returnValue($this->_rowset));

        $t       = clone $this->now;
        $rowdata = [
            'id'            => 'verifytokenkey',
            'verify_token'  => hash('sha256', 'cba'),
            'created_time'  => $t->getTimestamp(),
            'lease_seconds' => 10000,
        ];

        $row = new ArrayObject($rowdata, ArrayObject::ARRAY_AS_PROPS);

        $this->_rowset->expects($this->any())
            ->method('current')
            ->will($this->returnValue($row));
        // require for the count call on the rowset in Model/Subscription
        $this->_rowset->expects($this->any())
            ->method('count')
            ->will($this->returnValue(1));

        $this->_tableGateway->expects($this->once())
            ->method('delete')
            ->with($this->equalTo(['id' => 'verifytokenkey']))
            ->will($this->returnValue(true));

        $params = $this->default_params;
        $params['hub_mode'] = 'unsubscribe';
        $this->_callback->handle($this->_setupRequest($params));
        $this->assertEquals(200, $this->_callback->getHttpResponse()->getStatusCode());
    }

    public function testRespondsToValidConfirmationWithBodyContainingHubChallenge()
    {
        $this->markTestIncomplete(
            'Needs review.'
        );
        $this->_tableGateway->expects($this->any())
            ->method('select')
            ->with($this->equalTo(['id' => 'verifytokenkey']))
            ->will($this->returnValue($this->_rowset));

        $t       = clone $this->now;
        $rowdata = [
            'id'            => 'verifytokenkey',
            'verify_token'  => hash('sha256', 'cba'),
            'created_time'  => $t->getTimestamp(),
            'lease_seconds' => 10000,
        ];

        $row = new ArrayObject($rowdata, ArrayObject::ARRAY_AS_PROPS);

        $this->_rowset->expects($this->any())
            ->method('current')
            ->will($this->returnValue($row));
        // require for the count call on the rowset in Model/Subscription
        $this->_rowset->expects($this->any())
            ->method('count')
            ->will($this->returnValue(1));

        $this->_tableGateway->expects($this->once())
            ->method('update')
            ->with(
                $this->equalTo([
                    'id'                 => 'verifytokenkey',
                    'verify_token'       => hash('sha256', 'cba'),
                    'created_time'       => $t->getTimestamp(),
                    'lease_seconds'      => 1234567,
                    'subscription_state' => 'verified',
                    'expiration_time'    => $t->add(new DateInterval('PT1234567S'))->format('Y-m-d H:i:s'),
                ]),
                $this->equalTo(['id' => 'verifytokenkey'])
            );

        $this->_callback->handle();
        $this->assertEquals('abc', $this->_callback->getHttpResponse()->getBody());
    }

    ######### FEED UPDATE TESTS #############

    //do we respond with 200?
    public function testRespondsWith200Response()
    {
        $this->_setupDB();
        $this->_callback->setSubscriptionKey('subscriptionkey');

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
        $this->_setupDB();
        $this->_callback->setSubscriptionKey('subscriptionkey');
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
        $this->_setupDB();
        $this->_callback->setSubscriptionKey('subscriptionkey');
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
        $this->_setupDB();
        $this->_callback->setSubscriptionKey('subscriptionkey');
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
        $this->_setupDB();
        $this->_callback->setSubscriptionKey('subscriptionkey');

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
        $this->_setupDB();
        $this->_callback->setSubscriptionKey('subscriptionkey');
        $request = $this->_setupRequest(
            [],
            'POST',
            [
                ['Content-Type', 'application/atom+xml']
            ],
            null,
            $this->getStream(__DIR__ . '/_files/atom10.xml')
        );

        $request->expects($this->any())->method('getHeader')->will(
            $this->returnValueMap([
                ['X-Hub-On-Behalf-Of', [1]]
            ])
        );

        $this->_callback->handle($request);
        $header = $this->_callback->getHttpResponse()->getHeader('X-Hub-On-Behalf-Of');
        $this->assertEquals([1], $header);
    }
}
