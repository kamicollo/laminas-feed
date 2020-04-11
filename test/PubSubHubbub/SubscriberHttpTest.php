<?php

/**
 * @see       https://github.com/laminas/laminas-feed for the canonical source repository
 * @copyright https://github.com/laminas/laminas-feed/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-feed/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Feed\PubSubHubbub;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Feed\PubSubHubbub\Model\Subscription;
use Laminas\Feed\PubSubHubbub\PubSubHubbub;
use Laminas\Feed\PubSubHubbub\Subscriber;
use Laminas\Http\Client\Adapter\Socket;
use Laminas\Http\Client as HttpClient;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Note that $this->_baseuri must point to a directory on a web server
 * containing all the files under the files directory. You should symlink
 * or copy these files and set '_baseuri' properly using the constant in
 * phpunit.xml (based on phpunit.xml.dist)
 *
 * You can also set the proper constant in your test configuration file to
 * point to the right place.
 *
 * @group Laminas_Feed
 * @group Laminas_Feed_Subsubhubbub
 */
class SubscriberHttpTest extends TestCase
{
    /** @var Subscriber */
    protected $subscriber;

    /** @var string */
    protected $baseuri;

    /** @var HttpClient */
    protected $client;

    protected $storage;

    protected function setUp(): void
    {
        $this->baseuri = getenv('TESTS_LAMINAS_FEED_PUBSUBHUBBUB_BASEURI');
        if ($this->baseuri) {
            if (substr($this->baseuri, -1) !== '/') {
                $this->baseuri .= '/';
            }
            $name = $this->getName();
            if (($pos = strpos($name, ' ')) !== false) {
                $name = substr($name, 0, $pos);
            }
            $uri          = $this->baseuri . $name . '.php';
            $this->client = new HttpClient($uri);
            $this->client->setAdapter(Socket::class);
            PubSubHubbub::setHttpClient($this->client);
            $this->subscriber = new Subscriber();

            $this->storage = $this->_getCleanMock(Subscription::class);
            $this->subscriber->setStorage($this->storage);
        } else {
            // Skip tests
            $this->markTestSkipped('Laminas\Feed\PubSubHubbub\Subscriber dynamic tests are not enabled in phpunit.xml');
        }
    }

    public function testSubscriptionRequestSendsExpectedPostData()
    {
        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $this->subscriber->addHubUrl($this->baseuri . '/testRawPostData.php');
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $this->subscriber->usePathParameter(false);
        $this->subscriber->setLeaseSeconds(2592000);
        $token = md5('http://www.example.com/topic' . $this->baseuri . '/testRawPostData.php');
        $this->subscriber->setTestStaticToken('abc'); // override for testing
        $this->subscriber->subscribeAll();
        $this->assertEquals(
            'hub.callback=http%3A%2F%2Fwww.example.com%2Fcallback%3Fxhub.subscription%3D' . $token
                . '&hub.lease_seconds=2592000&hub.mode='
                . 'subscribe&hub.topic=http%3A%2F%2Fwww.example.com%2Ftopic&hub.veri'
                . 'fy=sync&hub.verify=async&hub.verify_token=abc',
            $this->client->getResponse()->getBody()
        );
    }

    public function testSubscriptionRequestWithPathParameterSendsExpectedPostData()
    {
        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $this->subscriber->addHubUrl($this->baseuri . '/testRawPostData.php');
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $this->subscriber->usePathParameter(true);
        $this->subscriber->setLeaseSeconds(2592000);
        $token = md5('http://www.example.com/topic' . $this->baseuri . '/testRawPostData.php');
        $this->subscriber->setTestStaticToken('abc'); // override for testing
        $this->subscriber->subscribeAll();
        $this->assertEquals(
            'hub.callback=http%3A%2F%2Fwww.example.com%2Fcallback%2F' . $token
                . '&hub.lease_seconds=2592000&hub.mode='
                . 'subscribe&hub.topic=http%3A%2F%2Fwww.example.com%2Ftopic&hub.veri'
                . 'fy=sync&hub.verify=async&hub.verify_token=abc',
            $this->client->getResponse()->getBody()
        );
    }

    public function testSubscriptionRequestNoLeaseSecondsSendsExpectedPostData()
    {
        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $this->subscriber->addHubUrl($this->baseuri . '/testRawPostData.php');
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $this->subscriber->usePathParameter(true);
        $token = md5('http://www.example.com/topic' . $this->baseuri . '/testRawPostData.php');
        $this->subscriber->setTestStaticToken('abc'); // override for testing
        $this->subscriber->subscribeAll();
        $this->assertEquals(
            'hub.callback=http%3A%2F%2Fwww.example.com%2Fcallback%2F' . $token
                . '&hub.mode='
                . 'subscribe&hub.topic=http%3A%2F%2Fwww.example.com%2Ftopic&hub.veri'
                . 'fy=sync&hub.verify=async&hub.verify_token=abc',
            $this->client->getResponse()->getBody()
        );
    }

    public function testUnsubscriptionRequestSendsExpectedPostData()
    {
        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $this->subscriber->addHubUrl($this->baseuri . '/testRawPostData.php');
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $this->subscriber->setLeaseSeconds(2592000);
        $token = md5('http://www.example.com/topic' . $this->baseuri . '/testRawPostData.php');
        $this->subscriber->setTestStaticToken('abc'); // override for testing
        $this->subscriber->subscribeAll();

        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $this->subscriber->addHubUrl($this->baseuri . '/testRawPostData.php');
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $token = md5('http://www.example.com/topic' . $this->baseuri . '/testRawPostData.php');
        $this->subscriber->setTestStaticToken('abc'); //override for testing
        $this->subscriber->unsubscribeAll();
        $this->assertEquals(
            'hub.callback=http%3A%2F%2Fwww.example.com%2Fcallback%3Fxhub.subscription%3D' . $token
                . '&hub.mode=unsubscribe&hub.topic=http'
                . '%3A%2F%2Fwww.example.com%2Ftopic&hub.verify=sync&hub.verify=async'
                . '&hub.verify_token=abc',
            $this->client->getResponse()->getBody()
        );
    }

    public function testSubscriptionUnsubscriptionRequestSendsExpectedPostData()
    {
        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $this->subscriber->addHubUrl($this->baseuri . '/testRawPostData.php');
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $token = md5('http://www.example.com/topic' . $this->baseuri . '/testRawPostData.php');
        $this->subscriber->setTestStaticToken('abc'); //override for testing
        $this->subscriber->unsubscribeAll();
        $this->assertEquals(
            'hub.callback=http%3A%2F%2Fwww.example.com%2Fcallback%3Fxhub.subscription%3D' . $token
                . '&hub.mode=unsubscribe&hub.topic=http'
                . '%3A%2F%2Fwww.example.com%2Ftopic&hub.verify=sync&hub.verify=async'
                . '&hub.verify_token=abc',
            $this->client->getResponse()->getBody()
        );
    }

    public function testSubscriptionRequestSendsParametersExpectedPostData()
    {
        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $this->subscriber->addHubUrl($this->baseuri . '/testRawPostData.php');
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $this->subscriber->usePathParameter(false);
        $this->subscriber->setParameter('hub.foo', 'bar');
        $token = md5('http://www.example.com/topic' . $this->baseuri . '/testRawPostData.php');
        $this->subscriber->setTestStaticToken('abc'); // override for testing
        $this->subscriber->subscribeAll();
        $this->assertEquals(
            'hub.callback=http%3A%2F%2Fwww.example.com%2Fcallback%3Fxhub.subscription%3D' . $token
                . '&hub.foo=bar&hub.mode='
                . 'subscribe&hub.topic=http%3A%2F%2Fwww.example.com%2Ftopic&hub.veri'
                . 'fy=sync&hub.verify=async&hub.verify_token=abc',
            $this->client->getResponse()->getBody()
        );
    }

    public function testSubscriptionRequestSendsHubParametersExpectedPostData()
    {
        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $hubUrl = $this->baseuri . '/testRawPostData.php';
        $this->subscriber->addHubUrl($hubUrl);
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $this->subscriber->usePathParameter(false);
        $this->subscriber->setHubParameter($hubUrl, 'hub.foo', 'bar');
        $token = md5('http://www.example.com/topic' . $hubUrl);
        $this->subscriber->setTestStaticToken('abc'); // override for testing
        $this->subscriber->subscribeAll();
        $this->assertEquals(
            'hub.callback=http%3A%2F%2Fwww.example.com%2Fcallback%3Fxhub.subscription%3D' . $token
                . '&hub.foo=bar&hub.mode='
                . 'subscribe&hub.topic=http%3A%2F%2Fwww.example.com%2Ftopic&hub.veri'
                . 'fy=sync&hub.verify=async&hub.verify_token=abc',
            $this->client->getResponse()->getBody()
        );
    }

    public function testSubscriptionRequestSendsExpectedHeaders()
    {
        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $hubUrl = $this->baseuri . '/testRawHeaders.php';
        $this->subscriber->addHubUrl($hubUrl);
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $this->subscriber->setTestStaticToken('abc'); // override for testing
        $this->subscriber->subscribeAll();
        $headers = json_decode($this->client->getResponse()->getBody(), true);
        $this->assertEquals(
            'application/x-www-form-urlencoded',
            $headers['Content-Type']
        );
    }

    public function testSubscriptionRequestSendsExpectedAuth()
    {
        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $hubUrl = $this->baseuri . '/testRawHeaders.php';
        $this->subscriber->addHubUrl($hubUrl);
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $this->subscriber->setTestStaticToken('abc'); // override for testing
        $this->subscriber->addAuthentication($hubUrl, ['user', 'pass']);
        $this->subscriber->subscribeAll();
        $headers = json_decode($this->client->getResponse()->getBody(), true);
        $this->assertEquals(
            'Basic ' . base64_encode('user:pass'),
            $headers['Authorization']
        );
    }

    public function testSubscriptionRequestDoesNotSendExpectedAuth()
    {
        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $hubUrl = $this->baseuri . '/testRawHeaders.php';
        $this->subscriber->addHubUrl($hubUrl);
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $this->subscriber->setTestStaticToken('abc'); // override for testing
        $this->subscriber->addAuthentication('randomurl', ['user', 'pass']);
        $this->subscriber->subscribeAll();
        $headers = json_decode($this->client->getResponse()->getBody(), true);
        $this->assertArrayNotHasKey('Authorization', $headers);
    }

    public function testSubscriptionRequestSendsExpectedHeader()
    {
        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $hubUrl = $this->baseuri . '/testRawHeaders.php';
        $this->subscriber->addHubUrl($hubUrl);
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $this->subscriber->setTestStaticToken('abc'); // override for testing
        $this->subscriber->setHeader('foo', 'bar');
        $this->subscriber->subscribeAll();
        $headers = json_decode($this->client->getResponse()->getBody(), true);
        $this->assertEquals(
            'bar',
            $headers['foo']
        );
    }

    public function testSubscriptionRequestSendsExpectedHubHeader()
    {
        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $hubUrl = $this->baseuri . '/testRawHeaders.php';
        $this->subscriber->addHubUrl($hubUrl);
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $this->subscriber->setTestStaticToken('abc'); // override for testing
        $this->subscriber->setHubHeader($hubUrl, 'foo', 'bar');
        $this->subscriber->subscribeAll();
        $headers = json_decode($this->client->getResponse()->getBody(), true);
        $this->assertEquals(
            'bar',
            $headers['foo']
        );
    }

    public function testSubscriptionRequestSendsNoExpectedHubHeader()
    {
        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $hubUrl = $this->baseuri . '/testRawHeaders.php';
        $this->subscriber->addHubUrl($hubUrl);
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $this->subscriber->setTestStaticToken('abc'); // override for testing
        $this->subscriber->setHubHeader('randomurl', 'foo', 'bar');
        $this->subscriber->subscribeAll();
        $headers = json_decode($this->client->getResponse()->getBody(), true);
        $this->assertArrayNotHasKey('foo', $headers);
    }

    public function testSubscriptionRequestSendsHubSecretExpectedPostData()
    {
        $this->subscriber->setTopicUrl('http://www.example.com/topic');
        $hubUrl = $this->baseuri . '/testRawPostData.php';
        $this->subscriber->addHubUrl($hubUrl);
        $this->subscriber->setCallbackUrl('http://www.example.com/callback');
        $this->subscriber->usePathParameter(false);
        $this->subscriber->setHubSecret($hubUrl, 'mysecret');
        $token = md5('http://www.example.com/topic' . $hubUrl);
        $this->subscriber->setTestStaticToken('abc'); // override for testing
        $this->subscriber->subscribeAll();
        $this->assertEquals(
            'hub.callback=http%3A%2F%2Fwww.example.com%2Fcallback%3Fxhub.subscription%3D' . $token
                . '&hub.mode='
                . 'subscribe&hub.secret=mysecret&hub.topic=http%3A%2F%2Fwww.example.com%2Ftopic&hub.veri'
                . 'fy=sync&hub.verify=async&hub.verify_token=abc',
            $this->client->getResponse()->getBody()
        );
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
        $parts    = explode('\\', $class->getName());
        $table    = strtolower(array_pop($parts));
        $adapter = new Adapter(['driver' => 'pdo_sqlite', 'dsn' => 'sqlite::memory:']);
        $tableGateway = new TableGateway($table, $adapter);
        $mocked = $this->getMockBuilder($className)->setConstructorArgs([$tableGateway])->setMethods($stubMethods)->getMock();
        return $mocked;
    }
}
