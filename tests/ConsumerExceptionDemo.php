<?php

namespace SolarSeahorse\Tests;

use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Interface\ConsumerMessageInterface;
use SolarSeahorse\WebmanRedisQueue\Redis\Redis;
use Throwable;

class ConsumerExceptionDemo extends Consumer
{
    protected string $queueName = 'demo';

    protected string $streamKey = 'test';

    protected string $delayedDataHashKey = 'test1';

    protected string $delayedTaskSetKey = 'test2';

    protected bool $autoDel = false;

    protected int $maxAttempts = 1;


    /**
     * @throws \Exception
     */
    public function consume(\SolarSeahorse\WebmanRedisQueue\Interface\ConsumerMessageInterface $consumerMessage)
    {
        // TODO: Implement consume() method.
        throw new \Exception('test');
    }

    /**
     * 自定义错误重试
     * @param $messageId
     * @param ConsumerMessageInterface $consumerMessage
     * @param Throwable $e
     * @return bool
     * @throws Throwable
     * @throws \RedisException
     */
    public function handlerFailRetry($messageId, ConsumerMessageInterface $consumerMessage, Throwable $e): bool
    {
        if ($consumerMessage->getQueueMessage()->getFailCount() >= $this->maxAttempts) {
            $this->handlerDeadLetterQueue($messageId, $consumerMessage, new \Exception('test'));
            return true;
        }
        Redis::getInstance($this->connection)->del('test-fail-retry');
        Redis::getInstance($this->connection)->set('test-fail-retry', $consumerMessage->getMessageId());

        return true;
    }

    /**
     * 自定义死信处理
     * @param $messageId
     * @param ConsumerMessageInterface $consumerMessage
     * @param Throwable $e
     * @return void
     * @throws Throwable
     * @throws \RedisException
     */
    public function handlerDeadLetterQueue($messageId, ConsumerMessageInterface $consumerMessage, Throwable $e): void
    {
        Redis::getInstance($this->connection)->del('test-dead-letter-queue');
        Redis::getInstance($this->connection)->set('test-dead-letter-queue', $consumerMessage->getMessageId());
    }


}