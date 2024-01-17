<?php

namespace SolarSeahorse\WebmanRedisQueue\Queue\Factory;

use SolarSeahorse\WebmanRedisQueue\Interface\DelayedQueueInterface;
use SolarSeahorse\WebmanRedisQueue\Queue\AbstractQueueMember;
use SolarSeahorse\WebmanRedisQueue\Queue\DelayedQueue;

class DelayedQueueFactory
{
    private static array $instances = [];

    /**
     * @param AbstractQueueMember $queueMember
     * @return DelayedQueue
     */
    public static function create(AbstractQueueMember $queueMember): DelayedQueueInterface
    {
        $instanceId = spl_object_hash($queueMember);
        if (!isset(self::$instances[$instanceId]) || !self::$instances[$instanceId] instanceof DelayedQueue) {
            self::$instances[$instanceId] = new DelayedQueue(
                $queueMember->getDelayedTaskSetKey(),
                $queueMember->getDelayedDataHashKey(),
                $queueMember->getRedisConnection()
            );
        }
        return self::$instances[$instanceId];
    }
}