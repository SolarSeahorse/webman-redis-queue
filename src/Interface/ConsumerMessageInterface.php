<?php

namespace SolarSeahorse\WebmanRedisQueue\Interface;

use Throwable;

interface ConsumerMessageInterface
{
    public function getMessageId(): string;

    public function getData(): mixed;

    public function getQueueMessage(): QueueMessageInterface;

    public function onError(callable $callback): void;

    public function triggerError(Throwable $e): void;

    public function ack(): int|bool;

    public function setAck(bool $ack): void;

    public function isAcked(): bool;

    public function getAckStatus(): bool;

    public function setAckStatus(bool $ackStatus): void;

    public function isDisableFailRetry(): bool;

    public function disableFailRetry(): void;

    public function triggerFailRetry(Throwable $e): bool;
}