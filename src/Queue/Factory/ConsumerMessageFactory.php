<?php

namespace SolarSeahorse\WebmanRedisQueue\Queue\Factory;

use SolarSeahorse\WebmanRedisQueue\ConsumerMessage;
use SolarSeahorse\WebmanRedisQueue\Interface\ConsumerMessageInterface;
use SolarSeahorse\WebmanRedisQueue\Queue\QueueConsumer;
use SolarSeahorse\WebmanRedisQueue\Queue\QueueMessage;

class ConsumerMessageFactory
{
    /**
     * @param string $messageId
     * @param array $message
     * @param QueueConsumer $queueConsumer
     * @return ConsumerMessageInterface
     */
    public static function create(string $messageId, array $message, QueueConsumer $queueConsumer): ConsumerMessageInterface
    {
        $queueMessage = QueueMessage::createFromArray($message);
        return new ConsumerMessage($messageId, $queueMessage, $queueConsumer);
    }
}