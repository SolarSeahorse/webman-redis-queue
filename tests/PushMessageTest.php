<?php
namespace SolarSeahorse\Tests;

use SolarSeahorse\WebmanRedisQueue\Queue\Factory\QueueMessageFactory;
use SolarSeahorse\WebmanRedisQueue\Queue\Factory\QueueProducerFactory;

class PushMessageTest extends BaseCase
{
    /**
     * @param $data
     * @return void
     * @throws \RedisException
     * @throws \Throwable
     */
    public function pushMessage($data)
    {
        // 生产者工厂方法
        $messageId = QueueProducerFactory::create($this->consumerDemo)->pushMessage($data);

        $this->getMessageDetails($messageId);

        $this->deleteMessages([$messageId]);

        // 通过消费类创建生产者
        $messageId = $this->consumerDemo::createQueueProducer()->pushMessage($data);

        $this->getMessageDetails($messageId);

        $this->deleteMessages([$messageId]);


        // 通过消费者创建队列消息对象
        $queueMessage = $this->consumerDemo::createQueueMessage($data);

        // 修改队列数据
        $queueMessage->setData(['data' => 'ok']);

        // 推送消息
        $messageId = QueueProducerFactory::create($this->consumerDemo)->pushMessage($queueMessage);

        $messageData = $this->getMessageDetails($messageId);

        $this->assertEquals($queueMessage->getData(), $messageData->getData());

        $this->deleteMessages([$messageId]);


        // 通过队列消息工厂方法 创建一条消息
        $queueMessage = QueueMessageFactory::create($this->consumerDemo, $data);

        // 修改队列数据
        $queueMessage->setData(['data' => 'ok']);

        $messageId = $this->consumerDemo::createQueueProducer()->pushMessage($queueMessage);

        $messageData = $this->getMessageDetails($messageId);

        $this->assertEquals($queueMessage->getData(), $messageData->getData());

        $this->deleteMessages([$messageId]);

        // 确保删除成功
        $this->streamLenEq(0);
    }

    /**
     * 测试添加单条队列数据
     * @throws \Throwable
     * @throws \RedisException
     */
    public function testPushMessage()
    {
        $data = [
            'data' => 'phpunit'
        ];

        // 测试数组
        $this->pushMessage($data);

        // 测试字符串
        $data = json_encode(['data' => 123]);
        $this->pushMessage($data);

        // 测试数字
        $this->pushMessage(1);

        // 测试null 抛出异常
        $this->expectException(\TypeError::class);

        $this->pushMessage(null);


        // 测试二维数组 抛出异常
        $this->expectException(\InvalidArgumentException::class);

        $this->pushMessage([
            [
                'data' => '123'
            ]
        ]);
    }
}