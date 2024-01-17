<?php

namespace SolarSeahorse\WebmanRedisQueue\Queue;

use Exception;
use SolarSeahorse\WebmanRedisQueue\Queue\Factory\DelayedQueueFactory;
use SolarSeahorse\WebmanRedisQueue\Exceptions\QueueMessagePushException;
use SolarSeahorse\WebmanRedisQueue\Exceptions\ScheduleDelayedMessageException;
use SolarSeahorse\WebmanRedisQueue\Interface\QueueMessageInterface;
use SolarSeahorse\WebmanRedisQueue\Interface\QueueProducerInterface;
use SolarSeahorse\WebmanRedisQueue\Log\LogUtility;
use SolarSeahorse\WebmanRedisQueue\Queue\Factory\QueueMessageFactory;
use InvalidArgumentException;

class QueueProducer extends AbstractQueueMember implements QueueProducerInterface
{
    /**
     * Add queue message
     * @param mixed $data
     * @return string|bool
     * @throws QueueMessagePushException
     */
    public function pushMessage(string|array|int|QueueMessageInterface $data): string|bool
    {
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            throw new InvalidArgumentException("Use pushMessages method for multidimensional array.");
        }

        try {
            if ($data instanceof QueueMessageInterface) {
                $message = $data;
            } else {
                $message = QueueMessageFactory::create($this->getConsumer(), $data);
            }

            return $this->getRedisConnection()->xAdd($this->getStreamKey(), '*', [
                'message' => json_encode($message->toArray())
            ]);
        } catch (Exception $e) {
            LogUtility::error("Failed to push message: " . $e->getMessage());
            throw new QueueMessagePushException("Failed to push message: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Add queue messages in batches. Use this method for a large number of queues.
     * @param array $dataArray
     * @return array|false
     * @throws QueueMessagePushException
     */
    public function pushMessages(array $dataArray): array|bool
    {
        try {
            $pipeline = $this->getRedisConnection()->pipeline();

            foreach ($dataArray as $data) {
                if ($data instanceof QueueMessageInterface) {
                    $message = $data;
                } else {
                    $message = QueueMessageFactory::create($this->getConsumer(), $data);
                }

                $pipeline->xAdd($this->getStreamKey(), '*', [
                    'message' => json_encode($message->toArray())
                ]);

                unset($message);
            }

            unset($dataArray);

            return $pipeline->exec();
        } catch (Exception $e) {
            LogUtility::error("Failed to push messages: " . $e->getMessage());
            throw new QueueMessagePushException("Failed to push messages: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Add delay queue message
     * @param mixed|QueueMessageInterface $data
     * @throws ScheduleDelayedMessageException
     */
    public function scheduleDelayedMessage(string|array|int|QueueMessageInterface $data, int $delay = 0, string $identifier = ''): bool
    {
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            throw new InvalidArgumentException("Use scheduleDelayedMessages method for multidimensional array.");
        }

        if ($data instanceof QueueMessageInterface) {
            $message = $data;
        } else {
            $message = QueueMessageFactory::create($this->getConsumer(), $data);
        }

        if ($delay > 0) {
            $message->setDelay($delay);
        }

        if (!empty($identifier)) {
            $message->setIdentifier($identifier);
        }

        $executeAtTimestamp = $message->getDelayUntil();

        try {
            return DelayedQueueFactory::create($this)->addTask(
                $message->getIdentifier(),
                $message->toArray(),
                $executeAtTimestamp);
        } catch (Exception $e) {
            LogUtility::error("Failed to schedule delayed message: " . $e->getMessage());
            throw new ScheduleDelayedMessageException("Failed to schedule delayed message: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Batch delay queue Use this method for large queues
     * @param array|QueueMessageInterface[] $dataArray
     * @return array|false
     * @throws ScheduleDelayedMessageException
     */
    public function scheduleDelayedMessages(array $dataArray): array|bool
    {
        $tasks = [];
        foreach ($dataArray as $data) {

            if ($data instanceof QueueMessageInterface) {
                $message = $data;
            } else {
                if (!isset($data['data'], $data['delay'])) {
                    throw new InvalidArgumentException('Invalid message data format.');
                }

                $message = QueueMessageFactory::create($this->getConsumer(), $data);

                $message->setDelay((int)$data['delay']);

                if (!empty($data['identifier'])) {
                    $message->setIdentifier($data['identifier']);
                }

            }

            $tasks[] = [
                'identifier' => $message->getIdentifier(),
                'executeAtTimestamp' => $message->getDelayUntil(),
                'data' => $message->toArray()
            ];

            unset($message);
        }

        unset($dataArray);

        try {
            return DelayedQueueFactory::create($this)->addTasks($tasks);
        } catch (Exception $e) {
            LogUtility::error("Failed to schedule delayed messages: " . $e->getMessage());
            throw new ScheduleDelayedMessageException("Failed to schedule delayed messages: " . $e->getMessage(), 0, $e);
        }
    }
}