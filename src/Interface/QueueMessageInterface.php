<?php

namespace SolarSeahorse\WebmanRedisQueue\Interface;

interface QueueMessageInterface
{
    public static function isValidMessage(array $data): bool;

    public function toArray(): array;

    public static function createFromArray(array $data): QueueMessageInterface;

    public function getIdentifier(): string;

    public function setIdentifier(int|string $identifier): void;

    public function setDelay(int $seconds): void;

    public function getDelayUntil(): int;

    public function incrementFailCount(): void;

    public function updateNextRetry(int $retryInterval): void;

    public function getFailCount(): int;

    public function getNextRetry(): int;

    public function getData(): mixed;

    public function getTimestamp(): int;

    public function setTimestamp(int $timestamp): void;

    public function getSource(): string;

    public function getType(): string;

    public function setData(string|array|int $data): void;

    public function setDelayUntil(int $delayUntil): void;

    public function setFailCount(int $failCount): void;

    public function setNextRetry(int $nextRetry): void;

    public function setSource(string $source): void;

    public function setType(string $type): void;

}