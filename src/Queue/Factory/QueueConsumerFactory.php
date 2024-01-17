<?php

namespace SolarSeahorse\WebmanRedisQueue\Queue\Factory;

use RedisException;
use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Interface\QueueConsumerInterface;
use SolarSeahorse\WebmanRedisQueue\Queue\QueueConsumer;
use Throwable;

class QueueConsumerFactory
{
    private static array $instances = [];

    /**
     * @throws RedisException
     * @throws Throwable
     */
    public static function create(string|Consumer $consumerClassOrObject, string $consumerName): QueueConsumerInterface
    {
        $instanceId = md5(is_object($consumerClassOrObject) ? get_class($consumerClassOrObject) : $consumerClassOrObject);
        if (!isset(self::$instances[$instanceId]) || !self::$instances[$instanceId] instanceof QueueConsumer) {
            self::$instances[$instanceId] = new QueueConsumer($consumerClassOrObject, $consumerName);
        }
        return self::$instances[$instanceId];
    }
}