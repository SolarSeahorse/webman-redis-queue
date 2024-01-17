<?php

namespace SolarSeahorse\Tests;

use SolarSeahorse\WebmanRedisQueue\Exceptions\QueueMessagePushException;
use SolarSeahorse\WebmanRedisQueue\Interface\QueueMessageInterface;
use SolarSeahorse\WebmanRedisQueue\Queue\Factory\QueueProducerFactory;

class PushMessagesTest extends BaseCase
{
    /**
     * @throws \Throwable
     * @throws \RedisException
     * @throws QueueMessagePushException
     */
    private function pushMessages($dataArr): void
    {
        // 生产者工厂方法
        $pushResult = QueueProducerFactory::create($this->consumerDemo)->pushMessages($dataArr);

        $this->isPushMessagesSuccessful($pushResult, $dataArr);


        // 通过消费类创建生产者
        $pushResult = $this->consumerDemo::createQueueProducer()->pushMessages($dataArr);


        $this->isPushMessagesSuccessful($pushResult, $dataArr);


        // 确保删除成功
        $this->streamLenEq(0);
    }

    /**
     * @throws \RedisException
     */
    public function isPushMessagesSuccessful($messageIds = [], $dataArr = []): void
    {
        $this->assertIsArray($messageIds);

        $this->assertIsArray($dataArr);

        $deleteMessageIds = [];

        foreach ($messageIds as $key => $messageId) {

            $messageDetails = $this->getMessageDetails($messageId);

            if ($dataArr[$key] instanceof QueueMessageInterface) {
                $this->assertEquals($messageDetails->getData(), $dataArr[$key]->getData());
            } else {
                $this->assertEquals($messageDetails->getData(), $dataArr[$key]);
            }

            $deleteMessageIds[] = $messageId;
        }

        $this->deleteMessages($deleteMessageIds);
    }


    /**
     * 测试添加多条消息
     * @return void
     * @throws \RedisException
     * @throws \Throwable
     */
    public function testPushMessages(): void
    {
        $dataArr = array_fill(0, 10, null);

        for ($i = 0; $i < 10; $i++) {
            $rand = mt_rand(0, 10) % 6;

            if ($rand == 1 || $rand == 2) {
                $dataArr[$i] = $rand;
            } elseif ($rand == 0) {
                $dataArr[$i] = ['dummy' => uniqid()];
            } else {
                $dataArr[$i] = 'ok';
            }
        }

        // 测试数组方式添加
        $this->pushMessages($dataArr);


        for ($i = 0; $i < 10; $i++) {
            $queueMessage = $this->consumerDemo::createQueueMessage([
                'dummy' => uniqid()
            ]);

            $rand = mt_rand(0, 10) % 6;

            if ($rand == 1 || $rand == 2) {
                $queueMessage->setData('ok');
            } elseif ($rand == 0) {
                $queueMessage->setData(0);
            } else {
                $queueMessage->setData([
                    'dummy' => 'ok'
                ]);
            }


            $dataArr[$i] = $queueMessage;

        }


        // 通过queueMessage对象数组形式
        $this->pushMessages($dataArr);


        // 测试有数据 null 抛出异常

        $this->expectException(\TypeError::class);

        for ($i = 0; $i < 10; $i++) {
            if ($i == 1 || $i == 2) {
                $dataArr[$i] = $rand;
            } elseif ($i == 0) {
                $dataArr[$i] = null;
            } else {
                $dataArr[$i] = 'ok';
            }
        }

        // 测试数组方式添加
        $this->pushMessages($dataArr);
    }
}