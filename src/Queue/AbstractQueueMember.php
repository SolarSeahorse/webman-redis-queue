<?php

namespace SolarSeahorse\WebmanRedisQueue\Queue;

use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Redis\Redis;
use SolarSeahorse\WebmanRedisQueue\Redis\RedisConnection;
use Throwable;

class AbstractQueueMember
{
    protected Consumer $consumer;
    protected string $consumerClass;

    /**
     * @throws Throwable
     */
    public function __construct(string|Consumer $consumerClassOrObject)
    {
        $consumer = QueueUtility::getConsumerInstance($consumerClassOrObject);
        $this->consumer = $consumer;
        $this->consumerClass = $consumer::class;
    }

    /**
     * @return Consumer
     */
    public function getConsumer(): Consumer
    {
        return $this->consumer;
    }

    /**
     * @return string
     */
    public function getConsumerClass(): string
    {
        return $this->consumerClass;
    }

    /**
     * @param string $consumerClass
     */
    public function setConsumerClass(string $consumerClass): void
    {
        $this->consumerClass = $consumerClass;
    }

    /**
     * @param Consumer $consumer
     */
    public function setConsumer(Consumer $consumer): void
    {
        $this->consumer = $consumer;
    }

    /**
     * @return Redis|RedisConnection
     */
    public function getRedisConnection(): Redis|RedisConnection
    {
        return $this->getConsumer()->getRedisConnection();
    }

    /**
     * @return string
     */
    public function getStreamKey(): string
    {
        return $this->getConsumer()->getStreamKey();
    }

    /**
     * @return string
     */
    public function getQueueName(): string
    {
        return $this->getConsumer()->getQueueName();
    }

    /**
     * @return string
     */
    public function getGroupName(): string
    {
        return $this->getConsumer()->getGroupName();
    }

    /**
     * @return int
     */
    public function getPrefetchCount(): int
    {
        return $this->getConsumer()->getPrefetchCount();
    }

    /**
     * @return int
     */
    public function getBlockTime(): int
    {
        return $this->getConsumer()->getBlockTime();
    }

    /**
     * @return float
     */
    public function getConsumerTimerInterval(): float
    {
        return $this->getConsumer()->getConsumerTimerInterval();
    }

    /**
     * @return int
     */
    public function getMaxAttempts(): int
    {
        return $this->getConsumer()->getMaxAttempts();
    }

    /**
     * @return int
     */
    public function getRetrySeconds(): int
    {
        return $this->getConsumer()->getRetrySeconds();
    }

    /**
     * @return bool
     */
    public function isAutoAck(): bool
    {
        return $this->getConsumer()->isAutoAck();
    }

    /**
     * @return bool
     */
    public function isAutoDel(): bool
    {
        return $this->getConsumer()->isAutoDel();
    }

    /**
     * @return int
     */
    public function getDelayedQueueOnceHandlerCount(): int
    {
        return $this->getConsumer()->getDelayedQueueOnceHandlerCount();
    }

    /**
     * @return int
     */
    public function getDelayedMessagesMaxWorkerCount(): int
    {
        return $this->getConsumer()->getDelayedMessagesMaxWorkerCount();
    }

    /**
     * @return int
     */
    public function getDelayedMessagesTimerInterval(): int
    {
        return $this->getConsumer()->getDelayedMessagesTimerInterval();
    }

    /**
     * @return string
     */
    public function getDelayedDataHashKey(): string
    {
        return $this->getConsumer()->getDelayedDataHashKey();
    }

    /**
     * @return string
     */
    public function getDelayedTaskSetKey(): string
    {
        return $this->getConsumer()->getDelayedTaskSetKey();
    }

    /**
     * @return int
     */
    public function getPendingProcessingStrategy(): int
    {
        return $this->getConsumer()->getPendingProcessingStrategy();
    }

    /**
     * @return int
     */
    public function getCheckPendingTimerInterval(): int
    {
        return $this->getConsumer()->getCheckPendingTimerInterval();
    }

    /**
     * @return int
     */
    public function getOnceCheckPendingCount(): int
    {
        return $this->getConsumer()->getOnceCheckPendingCount();
    }

    /**
     * @return int
     */
    public function getPendingTimout(): int
    {
        return $this->getConsumer()->getPendingTimout();
    }
}