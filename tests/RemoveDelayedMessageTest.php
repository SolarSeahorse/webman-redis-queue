<?php

namespace SolarSeahorse\Tests;

use SolarSeahorse\WebmanRedisQueue\Exceptions\ScheduleDelayedMessageException;
use SolarSeahorse\WebmanRedisQueue\Queue\Factory\QueueMessageFactory;

class RemoveDelayedMessageTest extends BaseCase
{
    /**
     * @throws ScheduleDelayedMessageException
     */
    public function testRemoveDelayedMessage()
    {
        // queueMessage对象
        $message = QueueMessageFactory::create($this->consumerDemo, [
            'dummy' => 'ok'
        ]);

        // 设置延时
        $message->setDelay(2);

        // 自定义ID
        $message->setIdentifier(uniqid());

        // 调度延时消息
        $this->queueProducer->scheduleDelayedMessage($message);

        // 消息是否存在
        $exists = $this->queueProducer->hasDelayedMessageExists($message->getIdentifier());

        // 添加成功
        $this->assertTrue($exists);

        // 删除消息
        $deleted = $this->queueProducer->removeDelayedMessage($message->getIdentifier());

        // 删除成功
        $this->assertTrue($deleted);

        // 确保不存在
        $exists = $this->queueProducer->hasDelayedMessageExists($message->getIdentifier());

        $this->assertFalse($exists);
    }

    public function testRemoveDelayedMessages()
    {
        $dataArr = array_fill(0, 10, null);

        for ($i = 0; $i < 10; $i++) {

            $queueMessage = $this->consumerDemo::createQueueMessage([
                'dummy' => uniqid()
            ]);

            // 设置延时
            $queueMessage->setDelay(50);

            $dataArr[$i] = $queueMessage;
        }

        // 批量调度延时消息
        $this->queueProducer->scheduleDelayedMessages($dataArr);

        $identifiers = array_map(function ($message) {
            return $message->getIdentifier();
        }, $dataArr);

        // 批量检查消息存在
        $this->assertDelayedMessagesExistence($identifiers, true);

        // 批量删除消息
        $deleted = $this->queueProducer->removeDelayedMessages($identifiers);

        $this->assertIsArray($deleted);

        $anyFailure = in_array(false, $deleted, true);

        $this->assertFalse($anyFailure);

        // 批量检查消息不存在
        $this->assertDelayedMessagesExistence($identifiers, false);
    }

    private function assertDelayedMessagesExistence(array $identifiers, bool $shouldExist): void
    {
        $existsResults = $this->queueProducer->hasDelayedMessagesExist($identifiers);

        $this->assertIsArray($existsResults);

        $anyFailure = in_array(false, $existsResults, true);

        $actualExistence = !$anyFailure;

        $this->assertEquals($shouldExist, $actualExistence, "Delayed messages existence does not match expected");
    }

}