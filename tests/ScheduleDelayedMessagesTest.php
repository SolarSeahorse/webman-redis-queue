<?php

namespace SolarSeahorse\Tests;

use SolarSeahorse\WebmanRedisQueue\Interface\QueueMessageInterface;
use SolarSeahorse\WebmanRedisQueue\Queue\Factory\DelayedQueueFactory;
use SolarSeahorse\WebmanRedisQueue\Queue\QueueMessage;

class scheduleDelayedMessagesTest extends BaseCase
{

    public function scheduleDelayedMessages($dataArr, int $assertLen)
    {
        // 发布延时
        $this->consumerDemo::createQueueProducer()->scheduleDelayedMessages($dataArr);

        $this->assertScheduleDelayedMessage($assertLen);

    }

    /**
     * 测试批量延时消息
     * @return void
     */
    public function testScheduleDelayedMessages()
    {
        $dataArr = array_fill(0, 10, null);

        // 正确的延时队列数据
        for ($i = 0; $i < 10; $i++) {
            $rand = mt_rand(0, 10) % 6;

            if ($rand == 1 || $rand == 2) {
                $dataArr[$i] = [
                    'delay' => 2,
                    'data' => $rand
                ];
            } elseif ($rand == 0) {
                $dataArr[$i] = [
                    'delay' => 2,
                    'data' => ['dummy' => uniqid()]
                ];
            } else {
                $dataArr[$i] = [
                    'delay' => 2,
                    'data' => 'ok'
                ];
            }
        }

        // 发布延时 并验证消息内容和预期长度
        $this->scheduleDelayedMessages($dataArr, 10);


        // 自定义 identifier  延时时间内同样ID会替换最新一条 不新增
        for ($i = 0; $i < 10; $i++) {
            $rand = mt_rand(0, 10) % 6;

            if ($rand == 1 || $rand == 2) {
                $dataArr[$i] = [
                    'delay' => 2,
                    'data' => $rand,
                    'identifier' => 123
                ];
            } elseif ($rand == 0) {
                $dataArr[$i] = [
                    'delay' => 2,
                    'data' => ['dummy' => uniqid()],
                    'identifier' => 123
                ];
            } else {
                $dataArr[$i] = [
                    'delay' => 2,
                    'data' => 'ok',
                    'identifier' => 123
                ];
            }
        }

        // 发布延时 并验证消息内容和预期长度
        $this->scheduleDelayedMessages($dataArr, 1);


        // 通过对象数组方式传递

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

            // 设置延时
            $queueMessage->setDelay(2);

            // 自定义 identifier
            $queueMessage->setIdentifier(md5(uniqid()));


            $dataArr[$i] = $queueMessage;

        }

        // 发布延时 并验证消息内容和预期长度
        $this->scheduleDelayedMessages($dataArr, 10);

        // 不规范的数据 抛出异常
        $this->expectException(\InvalidArgumentException::class);

        for ($i = 0; $i < 10; $i++) {
            $rand = mt_rand(0, 10) % 6;

            if ($rand == 1 || $rand == 2) {
                $dataArr[$i] = ['dummy' => uniqid()];
            } elseif ($rand == 0) {
                $dataArr[$i] = null;
            } else {
                $dataArr[$i] = 'ok';
            }
        }

        $this->scheduleDelayedMessages($dataArr, 0);

    }
}