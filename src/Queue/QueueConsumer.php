<?php

namespace SolarSeahorse\WebmanRedisQueue\Queue;

use SolarSeahorse\WebmanRedisQueue\Interface\ConsumerMessageInterface;
use SolarSeahorse\WebmanRedisQueue\Queue\Factory\DelayedQueueFactory;
use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Interface\QueueConsumerInterface;
use SolarSeahorse\WebmanRedisQueue\Log\LogUtility;
use SolarSeahorse\WebmanRedisQueue\Queue\Factory\ConsumerMessageFactory;
use RedisException;
use Throwable;

class QueueConsumer extends AbstractQueueMember implements QueueConsumerInterface
{
    private string $consumerName;

    public function __construct(string|Consumer $queueMember, string $consumerName)
    {
        parent::__construct($queueMember);
        $this->consumerName = $consumerName;
    }

    /**
     * @param string $consumerName
     * @return void
     */
    public function setConsumerName(string $consumerName): void
    {
        $this->consumerName = $consumerName;
    }

    /**
     * @return string
     */
    public function getConsumerName(): string
    {
        return $this->consumerName;
    }

    /**
     * @param int $now
     * @return void
     * @throws RedisException
     */
    public function handleDueTasks(int $now = 0): void
    {
        $now = !$now ? time() : $now;

        $delayedQueue = DelayedQueueFactory::create($this);

        $delayedQueue->moveDueTasksToStream($this->getStreamKey(), $now, $this->getDelayedQueueOnceHandlerCount());
    }

    /**
     * @return void
     * @throws RedisException
     * @throws Throwable
     */
    public function handlePendingTimeoutMessages(): void
    {
        $checkCount = $this->getOnceCheckPendingCount();

        $this->readPendingTimeoutMessages($checkCount, function (
            $messageId,
            $messageDetails,
            $consumerName,
            $elapsedTime,
            $deliveryCount
        ) {
            $consumerMessage = ConsumerMessageFactory::create($messageId, $messageDetails, $this);
            try {
                $this->getConsumer()->handlerPendingTimeoutMessages($messageId, $consumerMessage, $consumerName, $elapsedTime, $deliveryCount);
            } catch (\Throwable $e) {
                LogUtility::warning("Failed to handle pending timeout message. Error: {$e->getMessage()}");
            } finally {

                // Automatically ack when not acked
                $this->handleAutoAck($consumerMessage);

                // Delete immediately after ack is successful
                $this->handleAutoDeleteMessage($consumerMessage);

                unset($consumerMessage);
            }
        });
    }

    /**
     * @param callable $onMessage
     *                              - string $messageId
     *                              - array $messageDetails
     *                              - string $stream * prefixed stream key
     * @throws RedisException
     */
    public function readGroupMessages(callable $onMessage): void
    {
        $messages = $this->getRedisConnection()->xReadGroup(
            $this->getGroupName(),
            $this->getConsumerName(),
            [$this->getStreamKey() => '>'],
            $this->getPrefetchCount(),
            $this->getBlockTime()
        );

        if (!$messages) {
            return;
        }

        foreach ($messages as $streamKey => $messageList) {
            foreach ($messageList as $messageId => $message) {

                if (!$messageDetails = QueueMessage::parseRawMessage($message)) {
                    $this->ackAndDeleteMessage([$messageId]);
                    continue;
                }

                call_user_func($onMessage, $messageId, $messageDetails, $streamKey);

            }
        }

        unset($messages);
    }

    /**
     * @param callable $onMessage
     *                              - string $messageId
     *                              - array $messageDetails
     *                              - string $consumerName
     *                              - int $elapsedTime
     *                              - int $deliveryCount
     * @throws RedisException
     */
    public function readPendingTimeoutMessages(int $count, callable $onMessage): void
    {
        $this->lockAndExecute($this->getConsumerName() . '_read_pending_messages', function () use ($onMessage, $count) {

            $start = '-';

            $end = '+';

            // Get details of pending messages
            $pendingMessages = $this->getRedisConnection()->xPending(
                $this->getStreamKey(),
                $this->getGroupName(),
                $start,
                $end,
                $count
            );

            if (!$pendingMessages) {
                return 0;
            }

            $count = count($pendingMessages);

            foreach ($pendingMessages as $message) {

                $messageId = $message[0]; // Message ID
                $consumerName = $message[1]; // Consumer name
                $elapsedTime = $message[2]; // The time the message is pending ms
                $deliveryCount = $message[3]; // The number of times the message was delivered

                if ($elapsedTime < $this->getPendingTimout() * 1000) {
                    continue;
                }

                $messagesData = $this->getRedisConnection()->xClaim($this->getStreamKey(),
                    $this->getGroupName(),
                    $this->getConsumerName(),
                    0,
                    [$messageId],
                    []
                );

                if (!$messageDetails = QueueMessage::parseRawMessage($messagesData[$messageId])) {
                    $this->ackAndDeleteMessage($messageId);
                    continue;
                }

                call_user_func($onMessage, $messageId, $messageDetails, $consumerName, $elapsedTime, $deliveryCount);
            }

            unset($pendingMessages);

            return $count;
        }, 300);
    }

    /**
     * @return void
     */
    public function consumeMessages(): void
    {
        try {
            // Read group messages
            $this->readGroupMessages(function ($messageId, $messageDetails) {

                // Multiple queues using the same stream key may read other queue name data
                if ($messageDetails['type'] !== $this->getQueueName() || $messageDetails['source'] != $this->getConsumerClass()) {
                    $this->ackAndDeleteMessage($messageId);
                    return null;
                }

                // Process queue message
                $this->processMessage($messageId, $messageDetails);
            });
        } catch (Throwable $e) {
            LogUtility::warning(sprintf(
                "Error occurred while consuming messages from queue '%s' with consumer '%s': %s",
                $this->getQueueName(),
                $this->getConsumerClass(),
                $e->getMessage()
            ));
        }
    }

    /**
     * @param string $messageId
     * @param array $messageDetails
     * @return void
     */
    public function processMessage(string $messageId, array $messageDetails): void
    {
        $consumerMessage = ConsumerMessageFactory::create($messageId, $messageDetails, $this);

        try {
            // Consume
            $this->getConsumer()->consume($consumerMessage);

            // Automatically ack when not acked
            $this->handleAutoAck($consumerMessage);

        } catch (Throwable $e) {
            // trigger error event
            $consumerMessage->triggerError($e);

            // Retry has been triggered or disabled
            if ($consumerMessage->isDisableFailRetry()) {
                // Ensure ACK avoids processing again
                !$consumerMessage->isAcked() && $consumerMessage->ack();
                return;
            }

            // Data that has been acked will not be retried.
            if ($consumerMessage->isAcked()) {
                return;
            }

            // Automatic error retry
            $triggerFailRetry = $consumerMessage->triggerFailRetry($e);

            if ($triggerFailRetry) { // Retry successfully, ack message
                !$consumerMessage->isAcked() && $consumerMessage->ack();
            }
        } finally {
            // Delete immediately after ack is successful
            $this->handleAutoDeleteMessage($consumerMessage);

            unset($consumerMessage, $queueMessage);
        }
    }

    /**
     * @param ConsumerMessageInterface $consumerMessage
     * @return void
     */
    public function handleAutoAck(ConsumerMessageInterface $consumerMessage): void
    {
        if ($this->getConsumer()->isAutoAck() && !$consumerMessage->isAcked()) {
            $consumerMessage->ack();
        }
    }

    /**
     * @param ConsumerMessageInterface $consumerMessage
     * @return void
     */
    public function handleAutoDeleteMessage(ConsumerMessageInterface $consumerMessage): void
    {
        if ($consumerMessage->getAckStatus() && $this->isAutoDel()) {
            try {
                $this->xDel([$consumerMessage->getMessageId()]);
            } catch (\Throwable $e) {
                LogUtility::warning("Failed to delete message. Error: {$e->getMessage()}");
            }
        }
    }

    /**
     * ack message and del message
     * @param $messageId
     * @return bool
     */
    public function ackAndDeleteMessage($messageId): bool
    {
        try {
            if ($this->xAck($messageId) !== false) {
                $this->xDel($messageId);
                return true;
            }
        } catch (Throwable $e) {
            LogUtility::warning("Failed to ack and add delete message. Error: {$e->getMessage()}");
        }

        return false;
    }

    /**
     * Delete message
     * @param string|array $messageId
     * @return int|bool
     * @throws RedisException
     */
    public function xDel(string|array $messageId): int|bool
    {
        return $this->getRedisConnection()->Xdel($this->getStreamKey(), is_array($messageId) ? $messageId : [$messageId]);
    }

    /**
     * Ack message
     * @param string|array $messageId
     * @return bool|int
     * @throws RedisException
     */
    public function xAck(string|array $messageId): bool|int
    {
        return $this->getRedisConnection()->xAck($this->getStreamKey(), $this->getGroupName(), is_string($messageId) ? [$messageId] : $messageId);
    }

    /**
     * @throws RedisException
     */
    public function lockAndExecute($lockKey, $callback, $lockTimeout = 10): void
    {
        $lockValue = md5(uniqid());
        $locked = $this->getRedisConnection()->set($lockKey, $lockValue, ['NX', 'EX' => $lockTimeout]);

        if (!$locked) {
            return;
        }

        try {
            call_user_func($callback);
        } finally {
            if ($this->getRedisConnection()->get($lockKey) == $lockValue) {
                $this->getRedisConnection()->del($lockKey);
            }
        }
    }
}