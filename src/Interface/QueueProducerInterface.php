<?php

namespace SolarSeahorse\WebmanRedisQueue\Interface;

use SolarSeahorse\WebmanRedisQueue\Exceptions\QueueMessagePushException;
use SolarSeahorse\WebmanRedisQueue\Exceptions\ScheduleDelayedMessageException;

interface QueueProducerInterface
{
    /**
     * @param mixed|QueueMessageInterface $data
     * @return string|bool
     * @throws QueueMessagePushException
     */
    public function pushMessage(string|array|int|QueueMessageInterface $data): string|bool;

    /**
     * @param mixed|QueueMessageInterface $data
     * @param int $delay
     * @param string $identifier
     * @return bool
     * @throws ScheduleDelayedMessageException
     */
    public function scheduleDelayedMessage(string|array|int|QueueMessageInterface $data, int $delay = 0, string $identifier = ''): bool;

    /**
     * @param array|QueueMessageInterface[] $dataArray
     * @return array|false
     * @throws QueueMessagePushException
     */
    public function pushMessages(array $dataArray): array|bool;

    /**
     * @param array|QueueMessageInterface[] $dataArray
     * ScheduleDelayedMessageException
     * @return array|false
     */
    public function scheduleDelayedMessages(array $dataArray): array|bool;
}