<?php

namespace SolarSeahorse\WebmanRedisQueue\Queue;

use InvalidArgumentException;
use SolarSeahorse\WebmanRedisQueue\Consumer;
use support\Container;

class QueueUtility
{
    /**
     * Generate delay queue SET KEY by class name
     * @param string $consumerClass
     * @return string
     */
    public static function generateDelayedTaskSetKey(string $consumerClass): string
    {
        return strtolower(trim(str_replace("\\", '_', $consumerClass), '_')) . '_delayed_tasks';
    }

    /**
     * Generate delay queue Hash KEY by class name
     * @param string $consumerClass
     * @return string
     */
    public static function generateDelayedDataHashKey(string $consumerClass): string
    {
        return strtolower(trim(str_replace("\\", '_', $consumerClass), '_')) . '_delayed_data';
    }

    /**
     * Generate queue name from class name
     * @param string $consumerClass
     * @return string
     */
    public static function generateQueueName(string $consumerClass): string
    {
        return strtolower(trim(str_replace("\\", '-', $consumerClass), '-'));
    }

    /**
     * Generate group by class name
     * @param string $consumerClass
     * @return string
     */
    public static function generateGroupName(string $consumerClass): string
    {
        return strtolower(trim(str_replace("\\", '_', $consumerClass), '_'));
    }

    /**
     * Generate stream key by class name
     * @param string $consumerClass
     * @return string
     */
    public static function generateStreamKey(string $consumerClass): string
    {
        return strtolower(trim(str_replace("\\", '_', $consumerClass), '_'));
    }

    /**
     * Get consumer instance
     * @param Consumer|string $consumerClassOrObject
     * @return Consumer
     * @throws InvalidArgumentException
     */
    public static function getConsumerInstance(Consumer|string $consumerClassOrObject): Consumer
    {
        if ($consumerClassOrObject instanceof Consumer) {
            return $consumerClassOrObject;
        }

        if (class_exists($consumerClassOrObject)) {
            return Container::get($consumerClassOrObject);
        }

        throw new InvalidArgumentException("Invalid consumer class or object provided.");
    }
}