<?php

namespace SolarSeahorse\WebmanRedisQueue\Queue\Factory;


use RedisException;
use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Interface\QueueProducerInterface;
use SolarSeahorse\WebmanRedisQueue\Queue\QueueProducer;
use Throwable;

class QueueProducerFactory
{
    private static array $instances = [];

    /**
     * @throws RedisException
     * @throws Throwable
     */
    public static function create(string|Consumer $consumerClassOrObject): QueueProducerInterface
    {
        $instanceId = md5(is_object($consumerClassOrObject) ? get_class($consumerClassOrObject) : $consumerClassOrObject);
        if (!isset(self::$instances[$instanceId]) || !self::$instances[$instanceId] instanceof QueueProducer) {
            self::$instances[$instanceId] = new QueueProducer($consumerClassOrObject);
        }
        return self::$instances[$instanceId];
    }
}