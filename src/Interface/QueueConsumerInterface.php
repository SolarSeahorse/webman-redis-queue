<?php

namespace SolarSeahorse\WebmanRedisQueue\Interface;

interface QueueConsumerInterface
{
    public function setConsumerName(string $consumerName): void;

    public function getConsumerName(): string;

    public function readGroupMessages(callable $onMessage): void;

    public function consumeMessages(): void;

    public function processMessage(string $messageId, array $messageDetails): void;

    public function handleAutoAck(ConsumerMessageInterface $consumerMessage) :void;

    public function handleAutoDeleteMessage(ConsumerMessageInterface $consumerMessage): void;

    public function handleDueTasks(int $now = 0): void;

    public function handlePendingTimeoutMessages(): void;

    public function readPendingTimeoutMessages(int $count, callable $onMessage): void;

    public function ackAndDeleteMessage($messageId): bool;

    public function lockAndExecute($lockKey, $callback, $lockTimeout = 10): void;

    public function xAck(string|array $messageId): bool|int;

    public function xDel(string|array $messageId): bool|int;
}