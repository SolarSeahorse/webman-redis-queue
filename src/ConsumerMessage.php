<?php

namespace SolarSeahorse\WebmanRedisQueue;

use Closure;
use RedisException;
use SolarSeahorse\WebmanRedisQueue\Exceptions\ScheduleDelayedMessageException;
use SolarSeahorse\WebmanRedisQueue\Interface\ConsumerMessageInterface;
use SolarSeahorse\WebmanRedisQueue\Interface\QueueMessageInterface;
use SolarSeahorse\WebmanRedisQueue\Queue\QueueConsumer;
use Throwable;

class ConsumerMessage implements ConsumerMessageInterface
{
    protected bool $isAck = false;
    protected bool $ackStatus = false;
    private ?Closure $errorHandler = null;
    private bool $disableFailRetry = false;

    public function __construct(
        private string                  $messageId,
        protected QueueMessageInterface $queueMessage,
        private QueueConsumer           $queueConsumer
    )
    {
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getQueueMessage(): QueueMessageInterface
    {
        return $this->queueMessage;
    }

    public function getData(): mixed
    {
        return $this->queueMessage->getData();
    }

    public function setAck(bool $ack): void
    {
        $this->isAck = $ack;
    }

    public function isAcked(): bool
    {
        return $this->isAck;
    }

    public function getAckStatus(): bool
    {
        return $this->ackStatus;
    }

    public function setAckStatus(bool $ackStatus): void
    {
        $this->ackStatus = $ackStatus;
    }

    public function ack(): int|bool
    {
        $this->setAck(true);
        try {
            $this->queueConsumer->xAck($this->messageId);
            $this->setAckStatus(true);
        } catch (\Throwable) {
            $this->setAckStatus(false);
        }
        return $this->getAckStatus();
    }

    public function onError(callable $callback): void
    {
        $this->errorHandler = $callback;
    }

    public function triggerError(Throwable $e): void
    {
        if (is_callable($this->errorHandler)) {
            call_user_func($this->errorHandler, $e, $this);
        }
    }

    public function isDisableFailRetry(): bool
    {
        return $this->disableFailRetry;
    }

    public function disableFailRetry(): void
    {
        $this->disableFailRetry = true;
    }

    /**
     * @throws Throwable
     * @throws ScheduleDelayedMessageException
     * @throws RedisException
     */
    public function triggerFailRetry(Throwable $e): bool
    {
        // Disable retries
        $this->disableFailRetry();

        // Handling retries
        return $this->queueConsumer->getConsumer()->handlerFailRetry(
            $this->messageId,
            $this,
            $e
        );
    }
}