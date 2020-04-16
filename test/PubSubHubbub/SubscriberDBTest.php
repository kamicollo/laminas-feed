<?php

/**
 * @see       https://github.com/laminas/laminas-feed for the canonical source repository
 * @copyright https://github.com/laminas/laminas-feed/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-feed/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Feed\PubSubHubbub;

use DateInterval;
use DateTime;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\TableGateway;
use ForkedLaminas\Feed\PubSubHubbub\Exception\ExceptionInterface;
use ForkedLaminas\Feed\PubSubHubbub\Exception\InvalidArgumentException;
use ForkedLaminas\Feed\PubSubHubbub\Model\Subscription;
use ForkedLaminas\Feed\PubSubHubbub\PubSubHubbub;
use ForkedLaminas\Feed\PubSubHubbub\Subscriber;
use Laminas\Http\Client as HttpClient;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @group Laminas_Feed
 * @group Laminas_Feed_Subsubhubbub
 */
class SubscriberDBTest extends TestCase
{
    /** @var Subscriber */
    protected $subscriber;

    protected $adapter;

    protected $tableGateway;

    protected function setUp(): void
    {
        $this->subscriber   = new Subscriber();
        $this->subscriber->setDefaultProtocol(PubSubHubbub::PROTOCOL03);
    }

    protected function initDb()
    {
        if (
            !extension_loaded('pdo')
            || !in_array('sqlite', PDO::getAvailableDrivers())
        ) {
            $this->markTestSkipped('Test only with pdo_sqlite');
        }
        $db = new Adapter(['driver' => 'pdo_sqlite', 'dsn' => 'sqlite::memory:']);
        $this->createTable($db);

        return $db;
    }

    protected function createTable(Adapter $db)
    {
        $sql = 'CREATE TABLE subscription ('
            . "id varchar(32) PRIMARY KEY NOT NULL DEFAULT '', "
            . 'topic_url varchar(255) DEFAULT NULL, '
            . 'hub_url varchar(255) DEFAULT NULL, '
            . 'hub_protocol varchar(10) DEFAULT NULL, '
            . 'created_time datetime DEFAULT NULL, '
            . 'lease_seconds bigint(20) DEFAULT NULL, '
            . 'verify_token varchar(255) DEFAULT NULL, '
            . 'secret varchar(255) DEFAULT NULL, '
            . 'expiration_time datetime DEFAULT NULL, '
            . 'subscription_state varchar(12) DEFAULT NULL'
            . ');';

        $db->query($sql)->execute();
    }

    public function testDBValuesAll()
    {
        $adapter = $this->initDb();
        $table   = new TableGateway('subscription', $adapter);
        $subscription = new Subscription($table);
        $this->subscriber->setStorage($subscription);

        $this->subscriber->setTopicUrl('http://foo.com');
        $this->subscriber->addHubUrl('http://localhost');
        $this->subscriber->setCallbackUrl('http://localhost/callback');
        $this->subscriber->setLeaseSeconds(125);
        $this->subscriber->setHubSecret('http://localhost', 'secret');
        $this->subscriber->setTestStaticToken('token');
        $this->subscriber->subscribeAll();
        $id = md5('http://foo.com' . 'http://localhost');

        $dataSubscription = $subscription->getSubscription($id);
        $createdTime = $dataSubscription['created_time'];
        $expirationTime = (new DateTime($createdTime))->add(new DateInterval('PT125S'));
        $this->assertEquals(
            $dataSubscription,
            [
                'id' => $id,
                'topic_url' => 'http://foo.com',
                'hub_url' => 'http://localhost',
                'hub_protocol' => $this->subscriber->getDefaultProtocol(),
                'created_time' => $createdTime,
                'lease_seconds' => '125',
                'verify_token' => hash('sha256', 'token'),
                'secret' => 'secret',
                'expiration_time' => $expirationTime->format('Y-m-d H:i:s'),
                'subscription_state' => PubSubHubbub::SUBSCRIPTION_NOTVERIFIED,
            ]
        );
    }

    public function testDBValuesNoLeaseSeconds()
    {
        $adapter = $this->initDb();
        $table   = new TableGateway('subscription', $adapter);
        $subscription = new Subscription($table);
        $this->subscriber->setStorage($subscription);

        $this->subscriber->setTopicUrl('http://foo.com');
        $this->subscriber->addHubUrl('http://localhost');
        $this->subscriber->setCallbackUrl('http://localhost/callback');
        $this->subscriber->setHubSecret('http://localhost', 'secret');
        $this->subscriber->setTestStaticToken('token');
        $this->subscriber->subscribeAll();
        $id = md5('http://foo.com' . 'http://localhost');

        $dataSubscription = $subscription->getSubscription($id);
        $createdTime = $dataSubscription['created_time'];
        $this->assertEquals(
            $dataSubscription,
            [
                'id' => $id,
                'topic_url' => 'http://foo.com',
                'hub_url' => 'http://localhost',
                'hub_protocol' => $this->subscriber->getDefaultProtocol(),
                'created_time' => $createdTime,
                'lease_seconds' => null,
                'verify_token' => hash('sha256', 'token'),
                'secret' => 'secret',
                'expiration_time' => null,
                'subscription_state' => PubSubHubbub::SUBSCRIPTION_NOTVERIFIED,
            ]
        );
    }

    public function testDBValuesNoSecret()
    {
        $adapter = $this->initDb();
        $table   = new TableGateway('subscription', $adapter);
        $subscription = new Subscription($table);
        $this->subscriber->setStorage($subscription);

        $this->subscriber->setTopicUrl('http://foo.com');
        $this->subscriber->addHubUrl('http://localhost');
        $this->subscriber->setCallbackUrl('http://localhost/callback');
        $this->subscriber->setTestStaticToken('token');
        $this->subscriber->subscribeAll();
        $id = md5('http://foo.com' . 'http://localhost');

        $dataSubscription = $subscription->getSubscription($id);
        $createdTime = $dataSubscription['created_time'];
        $this->assertEquals(
            $dataSubscription,
            [
                'id' => $id,
                'topic_url' => 'http://foo.com',
                'hub_url' => 'http://localhost',
                'hub_protocol' => $this->subscriber->getDefaultProtocol(),
                'created_time' => $createdTime,
                'lease_seconds' => null,
                'verify_token' => hash('sha256', 'token'),
                'secret' => null,
                'expiration_time' => null,
                'subscription_state' => PubSubHubbub::SUBSCRIPTION_NOTVERIFIED,
            ]
        );
    }

    public function testDBValuesUnsubscribe()
    {
        $adapter = $this->initDb();
        $table   = new TableGateway('subscription', $adapter);
        $subscription = new Subscription($table);
        $this->subscriber->setStorage($subscription);

        $this->subscriber->setTopicUrl('http://foo.com');
        $this->subscriber->addHubUrl('http://localhost');
        $this->subscriber->setCallbackUrl('http://localhost/callback');
        $this->subscriber->setTestStaticToken('token');
        $this->subscriber->setHubSecret('http://localhost', 'secret');
        $id = md5('http://foo.com' . 'http://localhost');
        $this->subscriber->setLeaseSeconds(125);

        $this->subscriber->subscribeAll();
        $initialData = $subscription->getSubscription($id);
        $createdTime = $initialData['created_time'];
        $expirationTime = (new DateTime($createdTime))->add(new DateInterval('PT125S'));

        $subscriber2 = new Subscriber();
        $subscriber2->setStorage($subscription);

        $subscriber2->setTopicUrl('http://foo.com');
        $subscriber2->addHubUrl('http://localhost');
        $subscriber2->setCallbackUrl('http://localhost/callback');
        $subscriber2->setTestStaticToken('token2');
        $subscriber2->unsubscribeAll();
        $dataSubscription = $subscription->getSubscription($id);
        $this->assertEquals(
            $dataSubscription,
            [
                'id' => $id,
                'topic_url' => 'http://foo.com',
                'hub_url' => 'http://localhost',
                'hub_protocol' => $this->subscriber->getDefaultProtocol(),
                'created_time' => $createdTime,
                'lease_seconds' => '125',
                'verify_token' => hash('sha256', 'token2'),
                'secret' => 'secret',
                'expiration_time' => $expirationTime->format('Y-m-d H:i:s'),
                'subscription_state' => PubSubHubbub::SUBSCRIPTION_TODELETE,
            ]
        );
    }

    public function testDBValuesProtocol04()
    {
        $adapter = $this->initDb();
        $table   = new TableGateway('subscription', $adapter);
        $subscription = new Subscription($table);
        $this->subscriber->setStorage($subscription);

        $this->subscriber->setTopicUrl('http://foo.com');
        $this->subscriber->addHubUrl('http://localhost', PubSubHubbub::PROTOCOL04);
        $this->subscriber->setCallbackUrl('http://localhost/callback');
        $this->subscriber->setTestStaticToken('token');

        $this->subscriber->subscribeAll();
        $id = md5('http://foo.com' . 'http://localhost');

        $dataSubscription = $subscription->getSubscription($id);
        $createdTime = $dataSubscription['created_time'];

        $this->assertEquals(
            $dataSubscription,
            [
                'id' => $id,
                'topic_url' => 'http://foo.com',
                'hub_url' => 'http://localhost',
                'hub_protocol' => PubSubHubbub::PROTOCOL04,
                'created_time' => $createdTime,
                'lease_seconds' => null,
                'verify_token' => null,
                'secret' => null,
                'expiration_time' => null,
                'subscription_state' => PubSubHubbub::SUBSCRIPTION_NOTVERIFIED,
            ]
        );
    }
}
