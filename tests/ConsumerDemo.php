<?php

namespace SolarSeahorse\Tests;

use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Interface\ConsumerMessageInterface;
use SolarSeahorse\WebmanRedisQueue\Redis\Redis;

class ConsumerDemo extends Consumer
{
    protected string $connection = 'default';

    protected string $queueName = 'demo';

    protected string $streamKey = 'test';

    protected bool $autoAck = true;

    protected bool $autoDel = false;

    protected string $delayedDataHashKey = 'test1';

    protected string $delayedTaskSetKey = 'test2';

    public function consume(ConsumerMessageInterface $consumerMessage)
    {
        Redis::getInstance($this->connection)->del('test-consume');
        Redis::getInstance($this->connection)->set('test-consume',$consumerMessage->getMessageId());

        return $consumerMessage->getData();
    }
}