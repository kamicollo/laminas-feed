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
use Laminas\Feed\PubSubHubbub\AbstractCallback;
use Laminas\Feed\PubSubHubbub\Exception\ExceptionInterface;
use Laminas\Feed\PubSubHubbub\Model;
use Laminas\Feed\PubSubHubbub\PSR7HTTPClient;
use Laminas\Feed\PubSubHubbub\PubSubHubbub;
use Laminas\Feed\PubSubHubbub\Subscriber\Callback as CallbackSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use TypeError;

/**
 * @group Laminas_Feed
 * @group Laminas_Feed_Subsubhubbub
 */
class CallbackTest extends TestCase
{
    // @codingStandardsIgnoreStart
    /** @var CallbackSubscriber */
    public $_callback;
    /** @var \Laminas\Db\Adapter\Adapter|\PHPUnit_Framework_MockObject_MockObject */
    public $_adapter;
    /** @var \Laminas\Db\TableGateway\TableGateway|\PHPUnit_Framework_MockObject_MockObject */
    public $_tableGateway;
    /** @var \Laminas\Db\ResultSet\ResultSet|\PHPUnit_Framework_MockObject_MockObject */
    public $_rowset;
    /** @var array */
    public $_get = 'BLAH';
    // @codingStandardsIgnoreEnd

    /** @var DateTime */
    public $now;

    protected function setUp(): void
    {
        $this->_callback = new CallbackSubscriber();


        $this->_tableGateway = $this->_getCleanMock(
            TableGateway::class
        );

        $storage = new Model\Subscription($this->_tableGateway);

        $this->now = new DateTime();
        $storage->setNow(clone $this->now);

        $this->_callback->setStorage($storage);
    }

    public function testCanSetHttpResponseObject()
    {
        $client = new PSR7HTTPClient();
        $response = $client->createResponse();
        $this->_callback->setHttpResponse($response);
        $this->assertInstanceOf(ResponseInterface::class, $this->_callback->getHttpResponse());
    }

    public function testCanUsesDefaultHttpResponseObject()
    {
        $this->assertInstanceOf(ResponseInterface::class, $this->_callback->getHttpResponse());
    }

    public function testThrowsExceptionOnInvalidHttpResponseObjectSet()
    {
        $this->expectException(TypeError::class);
        $this->_callback->setHttpResponse(new stdClass());
    }

    public function testThrowsExceptionIfNonObjectSetAsHttpResponseObject()
    {
        $this->expectException(TypeError::class);
        $this->_callback->setHttpResponse('');
    }

    public function testCanSetSubscriberCount()
    {
        $this->_callback->setSubscriberCount('10000');
        $this->assertEquals(10000, $this->_callback->getSubscriberCount());
    }

    public function testDefaultSubscriberCountIsOne()
    {
        $this->assertEquals(1, $this->_callback->getSubscriberCount());
    }

    public function testThrowsExceptionOnSettingZeroAsSubscriberCount()
    {
        $this->expectException(ExceptionInterface::class);
        $this->_callback->setSubscriberCount(0);
    }

    public function testThrowsExceptionOnSettingLessThanZeroAsSubscriberCount()
    {
        $this->expectException(ExceptionInterface::class);
        $this->_callback->setSubscriberCount(-1);
    }

    public function testThrowsExceptionOnSettingAnyScalarTypeCastToAZeroOrLessIntegerAsSubscriberCount()
    {
        $this->expectException(ExceptionInterface::class);
        $this->_callback->setSubscriberCount('0aa');
    }

    public function testCanSetStorageImplementation()
    {
        $storage = new Model\Subscription($this->_tableGateway);
        $this->_callback->setStorage($storage);
        $this->assertThat($this->_callback->getStorage(), $this->identicalTo($storage));
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
}
