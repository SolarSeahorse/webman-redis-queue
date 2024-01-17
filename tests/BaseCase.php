<?php

namespace SolarSeahorse\Tests;

use PHPUnit\Framework\TestCase;
use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Interface\QueueConsumerInterface;
use SolarSeahorse\WebmanRedisQueue\Interface\QueueMessageInterface;
use SolarSeahorse\WebmanRedisQueue\Interface\QueueProducerInterface;
use SolarSeahorse\WebmanRedisQueue\Queue\AbstractQueueMember;
use SolarSeahorse\WebmanRedisQueue\Queue\Factory\DelayedQueueFactory;
use SolarSeahorse\WebmanRedisQueue\Queue\Factory\QueueConsumerFactory;
use SolarSeahorse\WebmanRedisQueue\Queue\QueueMessage;
use SolarSeahorse\WebmanRedisQueue\Queue\QueueUtility;
use SolarSeahorse\WebmanRedisQueue\Redis\Redis;
use SolarSeahorse\WebmanRedisQueue\Redis\RedisConnection;
use SolarSeahorse\WebmanRedisQueue\Redis\RedisQueueWorker;

class BaseCase extends TestCase
{
    public ?Consumer $consumerDemo = null;

    public Redis|RedisConnection|null $redisConnection = null;

    public AbstractQueueMember|QueueConsumerInterface $queueConsumer;

    public AbstractQueueMember|QueueProducerInterface $queueProducer;

    /**
     * @throws \Throwable
     * @throws \RedisException
     */
    public function setUp(): void
    {
        parent::setUp();

        $consumerClassOrObject = ConsumerDemo::class;

        $this->consumerDemo = QueueUtility::getConsumerInstance($consumerClassOrObject);

        $this->assertInstanceOf(Consumer::class, $this->consumerDemo);

        $group_name = $this->consumerDemo->getGroupName();

        $consumerName = "{$group_name}-1";

        $queueConsumer = RedisQueueWorker::getInstance()->subscribe(QueueConsumerFactory::create($this->consumerDemo, $consumerName));

        $this->assertInstanceOf(AbstractQueueMember::class, $queueConsumer);

        $this->redisConnection = $queueConsumer->getRedisConnection();

        $this->assertInstanceOf(Redis::class, $this->redisConnection);

        $this->queueConsumer = $queueConsumer;

        $this->clear();
    }

    public function clear()
    {
        $this->redisConnection->del(
            $this->queueConsumer->getStreamKey(),
            $this->queueConsumer->getDelayedDataHashKey(),
            $this->queueConsumer->getDelayedTaskSetKey()
        );
    }


    /**
     * @throws \RedisException
     */
    public function getMessageDetails($messageId): QueueMessageInterface
    {
        $message = $this->redisConnection->xRange($this->queueConsumer->getStreamKey(), $messageId, $messageId);

        $this->assertIsArray($message);

        $this->assertArrayHasKey($messageId, $message);

        $this->assertIsArray($message[$messageId]);

        $this->assertArrayHasKey('message', $message[$messageId]);

        $messageDetails = QueueMessage::parseRawMessage($message[$messageId]);

        $this->assertIsArray($messageDetails);

        $createQueueMessageFromArray = QueueMessage::createFromArray($messageDetails);

        $this->assertInstanceOf(QueueMessageInterface::class, $createQueueMessageFromArray);

        return $createQueueMessageFromArray;
    }

    /**
     * @throws \RedisException
     */
    public function deleteMessages(array $deleteMessageIds): void
    {
        foreach ($deleteMessageIds as $messageId) {

            $this->assertIsString($messageId);

        }

        $delete = $this->redisConnection->xDel($this->queueConsumer->getStreamKey(), $deleteMessageIds);

        $this->assertIsInt($delete);

        $this->assertEquals(count($deleteMessageIds), $delete);
    }

    /**
     * @param $eq
     * @return int
     * @throws \RedisException
     */
    public function streamLenEq($eq): int
    {
        $len = $this->redisConnection->xLen($this->queueConsumer->getStreamKey());

        $this->assertEquals($eq, $len);

        return $len;
    }

    public function moveDueTasksToStream()
    {
        $now = time() + 3600;

        $delayedQueue = DelayedQueueFactory::create($this->queueConsumer);

        $delayedQueue->moveDueTasksToStream($this->queueConsumer->getStreamKey(), $now, $this->queueConsumer->getDelayedQueueOnceHandlerCount());
    }

    /**
     * @throws \RedisException
     */
    public function assertScheduleDelayedMessage($assertLen, $delete = true): void
    {
        // 移动至stream队列
        $this->moveDueTasksToStream();

        // 预期新增n条队列
        $this->streamLenEq($assertLen);

        // 取n条数据
        $messages = $this->redisConnection->xRange($this->consumerDemo->getStreamKey(), '-', '+', $assertLen);

        $messageIds = [];

        foreach ($messages as $messageId => $messageData) {
            $messageIds[] = $messageId;

            // 确保消息格式正确
            $messageDetails = QueueMessage::parseRawMessage($messageData);

            $this->assertIsArray($messageDetails);

            $createQueueMessageFromArray = QueueMessage::createFromArray($messageDetails);

            $this->assertInstanceOf(QueueMessageInterface::class, $createQueueMessageFromArray);
        }

        if ($delete){
            // 确保删除成功
            $this->deleteMessages($messageIds);

            $this->streamLenEq(0);
        }

    }
}