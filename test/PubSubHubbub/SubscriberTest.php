<?php

/**
 * @see       https://github.com/laminas/laminas-feed for the canonical source repository
 * @copyright https://github.com/laminas/laminas-feed/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-feed/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Feed\PubSubHubbub;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Feed\PubSubHubbub\Exception\ExceptionInterface;
use Laminas\Feed\PubSubHubbub\Model\Subscription;
use Laminas\Feed\PubSubHubbub\PubSubHubbub;
use Laminas\Feed\PubSubHubbub\Subscriber;
use Laminas\Http\Client as HttpClient;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @group Laminas_Feed
 * @group Laminas_Feed_Subsubhubbub
 */
class SubscriberTest extends TestCase
{
    /** @var Subscriber */
    protected $subscriber;

    protected $adapter;

    protected $tableGateway;

    protected function setUp(): void
    {
        $client = new HttpClient();
        PubSubHubbub::setHttpClient($client);
        $this->subscriber   = new Subscriber();
        $this->adapter      = $this->_getCleanMock(
            Adapter::class
        );
        $this->tableGateway = $this->_getCleanMock(
            TableGateway::class
        );
        $this->tableGateway->expects($this->any())->method('getAdapter')
            ->will($this->returnValue($this->adapter));
    }

    public function testAddsHubServerUrl()
    {
        $this->subscriber->addHubUrl('http://www.example.com/hub');
        $this->assertEquals(['http://www.example.com/hub'], $this->subscriber->getHubUrls());
    }

    public function testAddsHubServerUrlsFromArray()
    {
        $this->subscriber->addHubUrls([
            'http://www.example.com/hub',
            'http://www.example.com/hub2',
        ]);
        $this->assertEquals([
            'http://www.example.com/hub',
            'http://www.example.com/hub2',
        ], $this->subscriber->getHubUrls());
    }

    public function testAddsHubServerUrlsFromArrayUsingSetOptions()
    {
        $this->subscriber->setOptions([
            'hubUrls' => [
                'http://www.example.com/hub',
                'http://www.example.com/hub2',
            ],
        ]);
        $this->assertEquals([
            'http://www.example.com/hub',
            'http://www.example.com/hub2',
        ], $this->subscriber->getHubUrls());
    }

    public function testRemovesHubServerUrl()
    {
        $this->subscriber->addHubUrls([
            'http://www.example.com/hub',
            'http://www.example.com/hub2',
        ]);
        $this->subscriber->removeHubUrl('http://www.example.com/hub');
        $this->assertEquals([
            1 => 'http://www.example.com/hub2',
        ], $this->subscriber->getHubUrls());
    }

    public function testRetrievesUniqueHubServerUrlsOnly()
    {
        $this->subscriber->addHubUrls([
            'http://www.example.com/hub',
            'http://www.example.com/hub2',
            'http://www.example.com/hub',
        ]);
        $this->assertEquals([
            'http://www.example.com/hub',
            'http://www.example.com/hub2',
        ], $this->subscriber->getHubUrls());
    }

    public function testThrowsExceptionOnSettingEmptyHubServerUrl()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->addHubUrl('');
    }

    public function testThrowsExceptionOnSettingNonStringHubServerUrl()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->addHubUrl(123);
    }

    public function testThrowsExceptionOnSettingInvalidHubServerUrl()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->addHubUrl('http://');
    }

    public function testAddsParameter()
    {
        $this->subscriber->setParameter('foo', 'bar');
        $this->assertEquals(['foo' => 'bar'], $this->subscriber->getParameters());
    }

    public function testAddsParametersFromArray()
    {
        $this->subscriber->setParameters([
            'foo' => 'bar',
            'boo' => 'baz',
        ]);
        $this->assertEquals([
            'foo' => 'bar',
            'boo' => 'baz',
        ], $this->subscriber->getParameters());
    }

    public function testAddsParametersFromArrayInSingleMethod()
    {
        $this->subscriber->setParameter([
            'foo' => 'bar',
            'boo' => 'baz',
        ]);
        $this->assertEquals([
            'foo' => 'bar',
            'boo' => 'baz',
        ], $this->subscriber->getParameters());
    }

    public function testAddsParametersFromArrayUsingSetOptions()
    {
        $this->subscriber->setOptions([
            'parameters' => [
                'foo' => 'bar',
                'boo' => 'baz',
            ],
        ]);
        $this->assertEquals([
            'foo' => 'bar',
            'boo' => 'baz',
        ], $this->subscriber->getParameters());
    }

    public function testRemovesParameter()
    {
        $this->subscriber->setParameters([
            'foo' => 'bar',
            'boo' => 'baz',
        ]);
        $this->subscriber->removeParameter('boo');
        $this->assertEquals([
            'foo' => 'bar',
        ], $this->subscriber->getParameters());
    }

    public function testRemovesParameterIfSetToNull()
    {
        $this->subscriber->setParameters([
            'foo' => 'bar',
            'boo' => 'baz',
        ]);
        $this->subscriber->setParameter('boo', null);
        $this->assertEquals([
            'foo' => 'bar',
        ], $this->subscriber->getParameters());
    }

    public function testCanSetTopicUrl()
    {
        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $this->assertEquals('http://www.example.com/topic', $this->subscriber->getTopicUrl());
    }

    public function testThrowsExceptionOnSettingEmptyTopicUrl()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->setTopicUrl('');
    }

    public function testThrowsExceptionOnSettingNonStringTopicUrl()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->setTopicUrl(123);
    }

    public function testThrowsExceptionOnSettingInvalidTopicUrl()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->setTopicUrl('http://');
    }

    public function testThrowsExceptionOnMissingTopicUrl()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->getTopicUrl();
    }

    public function testCanSetCallbackUrl()
    {
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $this->assertEquals('http://www.example.com/callback', $this->subscriber->getCallbackUrl());
    }

    public function testThrowsExceptionOnSettingEmptyCallbackUrl()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->setCallbackUrl('');
    }

    public function testThrowsExceptionOnSettingNonStringCallbackUrl()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->setCallbackUrl(123);
    }

    public function testThrowsExceptionOnSettingInvalidCallbackUrl()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->setCallbackUrl('http://');
    }

    public function testThrowsExceptionOnMissingCallbackUrl()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->getCallbackUrl();
    }

    public function testCanSetLeaseSeconds()
    {
        $this->subscriber->setLeaseSeconds('10000');
        $this->assertEquals(10000, $this->subscriber->getLeaseSeconds());
    }

    public function testThrowsExceptionOnSettingZeroAsLeaseSeconds()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->setLeaseSeconds(0);
    }

    public function testThrowsExceptionOnSettingLessThanZeroAsLeaseSeconds()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->setLeaseSeconds(-1);
    }

    public function testThrowsExceptionOnSettingAnyScalarTypeCastToAZeroOrLessIntegerAsLeaseSeconds()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->setLeaseSeconds('0aa');
    }

    public function testCanSetPreferredVerificationMode()
    {
        $this->subscriber->setPreferredVerificationMode(PubSubHubbub::VERIFICATION_MODE_ASYNC);
        $this->assertEquals(PubSubHubbub::VERIFICATION_MODE_ASYNC, $this->subscriber->getPreferredVerificationMode());
    }

    public function testSetsPreferredVerificationModeThrowsExceptionOnSettingBadMode()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->setPreferredVerificationMode('abc');
    }

    public function testPreferredVerificationModeDefaultsToSync()
    {
        $this->assertEquals(PubSubHubbub::VERIFICATION_MODE_SYNC, $this->subscriber->getPreferredVerificationMode());
    }

    public function testCanSetStorageImplementation()
    {
        $storage = new Subscription($this->tableGateway);
        $this->subscriber->setStorage($storage);
        $this->assertThat($this->subscriber->getStorage(), $this->identicalTo($storage));
    }

    public function testGetStorageThrowsExceptionIfNoneSet()
    {
        $this->expectException(ExceptionInterface::class);
        $this->subscriber->getStorage();
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

    public function testAddsHubHeader()
    {
        $this->subscriber->setHubHeader('hub', 'foo', 'bar');
        $this->assertEquals(['foo' => 'bar'], $this->subscriber->getHubHeaders('hub'));
    }

    public function testAddsHubHeaders()
    {
        $this->subscriber->setHubHeaders('hub', ['foo' => 'bar', 'bar' => 'foo']);
        $this->assertEquals(['foo' => 'bar', 'bar' => 'foo'], $this->subscriber->getHubHeaders('hub'));
    }

    public function testAddsHubHeaderMultipleHubs()
    {
        $this->subscriber->setHubHeader('hub1', 'foo', 'bar');
        $this->subscriber->setHubHeader('hub2', 'foo', 'baz');
        $this->assertEquals(['foo' => 'bar'], $this->subscriber->getHubHeaders('hub1'));
    }

    public function testAddsHubHeadersMultipleHubs()
    {
        $this->subscriber->setHubHeader('hub1', 'foo', 'bar');
        $this->subscriber->setHubHeader('hub2', 'foo', 'baz');
        $this->assertEquals(['foo' => 'bar'], $this->subscriber->getHubHeaders('hub1'));
    }

    public function testAddsHubHeaderMultipleTimes()
    {
        $this->subscriber->setHubHeaders('hub1', ['foo' => 'bar']);
        $this->subscriber->setHubHeaders('hub1', ['bar' => 'baz']);
        $this->assertEquals(['foo' => 'bar', 'bar' => 'baz'], $this->subscriber->getHubHeaders('hub1'));
    }

    public function testRemovesHubHeader()
    {
        $this->subscriber->setHubHeader('hub', 'foo', 'bar');
        $this->subscriber->removeHubHeader('hub', 'foo');
        $this->assertEquals([], $this->subscriber->getHubHeaders('hub'));
    }

    public function testRemovesHubHeaderNotEmpty()
    {
        $this->subscriber->setHubHeader('hub', 'foo', 'bar');
        $this->subscriber->setHubHeader('hub', 'quux', 'baz');
        $this->subscriber->removeHubHeader('hub', 'foo');
        $this->assertEquals(['quux' => 'baz'], $this->subscriber->getHubHeaders('hub'));
    }

    public function testRemovesHubHeaderByNull()
    {
        $this->subscriber->setHubHeader('hub', 'foo', 'bar');
        $this->subscriber->setHubHeader('hub', 'foo', null);
        $this->assertEquals([], $this->subscriber->getHubHeaders('hub'));
    }

    public function testRemovesAllHubHeaders()
    {
        $this->subscriber->setHubHeader('hub', 'foo', 'bar');
        $this->subscriber->setHubHeader('hub', 'food', 'bar');
        $this->subscriber->removeHubHeaders('hub');
        $this->assertEquals([], $this->subscriber->getHubHeaders('hub'));
    }

    public function testRemovesAllHubHeadersMultipleHubs()
    {
        $this->subscriber->setHubHeader('hub', 'foo', 'bar');
        $this->subscriber->setHubHeader('hub2', 'bar', 'foo');
        $this->subscriber->removeHubHeaders('hub');
        $this->assertEquals(['bar' => 'foo'], $this->subscriber->getHubHeaders('hub2'));
        $this->assertEquals([], $this->subscriber->getHubHeaders('hub'));
    }

    public function testAddAuthorization()
    {
        $this->subscriber->addAuthentication('hub', ['username', 'password']);
        $this->assertEquals(
            ['username', 'password'],
            $this->subscriber->getAuthentication('hub')
        );
    }

    public function testAddAuthorizations()
    {
        $this->subscriber->addAuthentications(
            [
                'hub' => ['username', 'password'],
                'hub2' => ['username', 'password2']
            ]
        );
        $this->assertEquals(
            [
                'hub' => ['username', 'password'],
                'hub2' => ['username', 'password2']
            ],
            $this->subscriber->getAuthentications()
        );
    }

    public function testAddsHubParameter()
    {
        $this->subscriber->setHubParameter('hub', 'foo', 'bar');
        $this->assertEquals(['foo' => 'bar'], $this->subscriber->getHubParameters('hub'));
    }

    public function testAddsHubParametes()
    {
        $this->subscriber->setHubParameters('hub', ['foo' => 'bar', 'bar' => 'foo']);
        $this->assertEquals(['foo' => 'bar', 'bar' => 'foo'], $this->subscriber->getHubParameters('hub'));
    }

    public function testAddsHubParameteMultipleHubs()
    {
        $this->subscriber->setHubParameter('hub1', 'foo', 'bar');
        $this->subscriber->setHubParameter('hub2', 'foo', 'baz');
        $this->assertEquals(['foo' => 'bar'], $this->subscriber->getHubParameters('hub1'));
    }

    public function testAddsHubParameterMultipleTimes()
    {
        $this->subscriber->setHubParameters('hub1', ['foo' => 'bar']);
        $this->subscriber->setHubParameters('hub1', ['bar' => 'baz']);
        $this->assertEquals(['foo' => 'bar', 'bar' => 'baz'], $this->subscriber->getHubParameters('hub1'));
    }

    public function testOverwritesHubParameterMultipleTimes()
    {
        $this->subscriber->setHubParameters('hub1', ['foo' => 'bar']);
        $this->subscriber->setHubParameters('hub1', ['foo' => 'baz']);
        $this->assertEquals(['foo' => 'baz'], $this->subscriber->getHubParameters('hub1'));
    }

    public function testRemovesHubParameter()
    {
        $this->subscriber->setHubParameter('hub', 'foo', 'bar');
        $this->subscriber->removeHubParameter('hub', 'foo');
        $this->assertEquals([], $this->subscriber->getHubParameters('hub'));
    }

    public function testRemovesHubParameterNotEmpty()
    {
        $this->subscriber->setHubParameter('hub', 'foo', 'bar');
        $this->subscriber->setHubParameter('hub', 'quux', 'baz');
        $this->subscriber->removeHubParameter('hub', 'foo');
        $this->assertEquals(['quux' => 'baz'], $this->subscriber->getHubParameters('hub'));
    }

    public function testRemovesHubParameterByNull()
    {
        $this->subscriber->setHubParameter('hub', 'foo', 'bar');
        $this->subscriber->setHubParameter('hub', 'foo', null);
        $this->assertEquals([], $this->subscriber->getHubParameters('hub'));
    }

    public function testRemovesAllHubParameters()
    {
        $this->subscriber->setHubParameter('hub', 'foo', 'bar');
        $this->subscriber->setHubParameter('hub', 'food', 'bar');
        $this->subscriber->removeHubParameters('hub');
        $this->assertEquals([], $this->subscriber->getHubParameters('hub'));
    }

    public function testRemovesAllHubParametersMultipleHubs()
    {
        $this->subscriber->setHubParameter('hub', 'foo', 'bar');
        $this->subscriber->setHubParameter('hub2', 'bar', 'foo');
        $this->subscriber->removeHubParameters('hub');
        $this->assertEquals(['bar' => 'foo'], $this->subscriber->getHubParameters('hub2'));
        $this->assertEquals([], $this->subscriber->getHubParameters('hub'));
    }

    public function testAddsHeader()
    {
        $this->subscriber->setHeader('foo', 'bar');
        $this->assertEquals(['foo' => 'bar'], $this->subscriber->getHeaders());
    }

    public function testAddsHeadersFromArray()
    {
        $this->subscriber->setHeaders([
            'foo' => 'bar',
            'boo' => 'baz',
        ]);
        $this->assertEquals([
            'foo' => 'bar',
            'boo' => 'baz',
        ], $this->subscriber->getHeaders());
    }

    public function testAddsHeadersFromArrayInSingleMethod()
    {
        $this->subscriber->setHeader([
            'foo' => 'bar',
            'boo' => 'baz',
        ]);
        $this->assertEquals([
            'foo' => 'bar',
            'boo' => 'baz',
        ], $this->subscriber->getHeaders());
    }

    public function testRemovesHeader()
    {
        $this->subscriber->setHeaders([
            'foo' => 'bar',
            'boo' => 'baz',
        ]);
        $this->subscriber->removeHeader('boo');
        $this->assertEquals([
            'foo' => 'bar',
        ], $this->subscriber->getHeaders());
    }

    public function testRemovesHeaderIfSetToNull()
    {
        $this->subscriber->setHeaders([
            'foo' => 'bar',
            'boo' => 'baz',
        ]);
        $this->subscriber->setHeader('boo', null);
        $this->assertEquals([
            'foo' => 'bar',
        ], $this->subscriber->getHeaders());
    }

    public function testAddsheadersFromArrayUsingSetOptions()
    {
        $this->subscriber->setOptions([
            'headers' => [
                'foo' => 'bar',
                'boo' => 'baz',
            ],
        ]);
        $this->assertEquals([
            'foo' => 'bar',
            'boo' => 'baz',
        ], $this->subscriber->getHeaders());
    }
}
