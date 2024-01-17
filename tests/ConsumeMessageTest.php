<?php

namespace SolarSeahorse\Tests;

use SolarSeahorse\WebmanRedisQueue\Exceptions\QueueMessagePushException;
use SolarSeahorse\WebmanRedisQueue\Queue\Factory\QueueConsumerFactory;
use SolarSeahorse\WebmanRedisQueue\Queue\Factory\QueueMessageFactory;
use SolarSeahorse\WebmanRedisQueue\Redis\RedisQueueWorker;

class ConsumeMessageTest extends BaseCase
{

    public function consumeMessage($consumerClassOrObject): void
    {
        $worker = RedisQueueWorker::getInstance();

        $queueConsumer = RedisQueueWorker::getInstance()->subscribe(
            QueueConsumerFactory::create($consumerClassOrObject, 'testing')
        );

        $worker->subscribe($queueConsumer);


        $worker->createConsumerGroups($worker->getQueues());

        // 消费消息
        $queueConsumer->consumeMessages();
    }

    /**
     * @throws \RedisException
     * @throws QueueMessagePushException
     * @throws \Throwable
     */
    public function testConsumeMessage()
    {
        $messageId = $this->consumerDemo::createQueueProducer()->pushMessage([
            'type' => 'ping'
        ]);

        $this->consumeMessage($this->consumerDemo);

        // 预期消费结果
        $this->assertEquals($messageId, $this->redisConnection->get('test-consume'));

        $this->redisConnection->del('test-consume');

        // 删除队列消息
        $this->deleteMessages([$messageId]);

        // 测试消费报错重试
        $messageId = ConsumerExceptionDemo::createQueueProducer()->pushMessage([
            'type' => 'ping'
        ]);

        // 消费消息，触发异常重试，此时消息将触发错误重试方法
        $this->consumeMessage(ConsumerExceptionDemo::class);

        // 删除消费消息
        $this->deleteMessages([$messageId]);

        // 预期错误重试结果
        $this->assertEquals($messageId, $this->redisConnection->get('test-fail-retry'));

        $queueMessage = QueueMessageFactory::create(ConsumerExceptionDemo::class,[
            'type' => 'ping'
        ]);

        // 设置错误次数
        $queueMessage->setFailCount(5);

        $messageId = ConsumerExceptionDemo::createQueueProducer()->pushMessage($queueMessage);

        // 测试触发死信处理
        $this->consumeMessage(ConsumerExceptionDemo::class);

        // 删除消费消息
        $this->deleteMessages([$messageId]);

        // 预期错误重试结果
        $this->assertEquals($messageId, $this->redisConnection->get('test-dead-letter-queue'));
    }
}