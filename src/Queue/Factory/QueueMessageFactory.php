<?php

namespace SolarSeahorse\WebmanRedisQueue\Queue\Factory;

use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Interface\QueueMessageInterface;
use SolarSeahorse\WebmanRedisQueue\Queue\QueueMessage;
use SolarSeahorse\WebmanRedisQueue\Queue\QueueUtility;

class QueueMessageFactory
{
    /**
     * @param string|Consumer $consumerClassOrObject
     * @param $data
     * @return QueueMessageInterface
     */
    public static function create(string|Consumer $consumerClassOrObject, $data): QueueMessageInterface
    {
        static $inc = 0;

        $consumer = QueueUtility::getConsumerInstance($consumerClassOrObject);

        $instance = new QueueMessage($consumer->getQueueName(), $data, get_class($consumer));

        $instance->setIdentifier($instance->getIdentifier() . '-' . $inc);

        $inc++;

        if ($inc >= 200000) {
            $inc = 0;
        }

        return $instance;
    }
}