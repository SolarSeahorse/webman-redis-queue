<?php

namespace SolarSeahorse\Tests;

use SolarSeahorse\WebmanRedisQueue\Queue\Factory\QueueMessageFactory;

class ScheduleDelayedMessageTest extends BaseCase
{
    public function scheduleDelayedMessage($data, int $assertLen)
    {
        // 发布延时
        $this->queueProducer->scheduleDelayedMessage($data);

        $this->assertScheduleDelayedMessage($assertLen);

    }

    /**
     * 测试单条延时消息
     * @return void
     */
    public function testScheduleDelayedMessage()
    {
        // 数组
        $this->scheduleDelayedMessage(['dummy' => 'ok'], 1);

        // 数字
        $this->scheduleDelayedMessage(123, 1);

        // 字符串
        $this->scheduleDelayedMessage(json_encode('123'), 1);

        // null
        $this->expectException(\TypeError::class);

        $this->scheduleDelayedMessage(null,0);

        // queueMessage对象
        $message = QueueMessageFactory::create($this->consumerDemo,[
            'dummy'  => 'ok'
        ]);

        // 设置延时
        $message->setDelay(2);

        // 自定义ID
        $message->setIdentifier(uniqid());

        $this->scheduleDelayedMessage($message,1);
    }
}