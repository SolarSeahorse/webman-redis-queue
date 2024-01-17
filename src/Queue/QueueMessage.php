<?php

namespace SolarSeahorse\WebmanRedisQueue\Queue;

use InvalidArgumentException;
use SolarSeahorse\WebmanRedisQueue\Interface\QueueMessageInterface;

class QueueMessage implements QueueMessageInterface
{
    private string|int $identifier;
    private string $type;
    private string|array|int $data;
    private int $timestamp;
    private string $source;
    private int $failCount;
    private int $nextRetry;
    private int $delayUntil;


    /**
     * @param string $type
     * @param mixed $data
     * @param string $source
     * @param int $timestamp
     * @param int $failCount
     * @param int $nextRetry
     * @param int $delayUntil
     */
    public function __construct(string $type, string|array|int $data, string $source, int $timestamp = 0, int $failCount = 0, int $nextRetry = 0, int $delayUntil = 0)
    {
        $this->type = $type;
        $this->data = $data;
        $this->timestamp = !$timestamp ? time() : $timestamp;
        $this->source = $source;
        $this->failCount = $failCount;
        $this->nextRetry = $nextRetry;
        $this->delayUntil = $delayUntil;
        $this->identifier = md5(microtime(true) . uniqid());
    }

    /**
     * @param mixed $data
     * @return bool
     */
    public static function isValidMessage(array $data): bool
    {
        return isset(
            $data['type'],
            $data['data'],
            $data['timestamp'],
            $data['source'],
            $data['failCount'],
            $data['nextRetry'],
            $data['delayUntil']
        );
    }

    /**
     * Parse Raw Message
     * @param $message
     * @return bool|array
     */
    public static function parseRawMessage($message): bool|array
    {
        $messageData = json_decode($message['message'] ?? '{}', true);

        if (!$messageData) return false;
        if (!self::isValidMessage($messageData)) {
            return false;
        }

        return $messageData;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
            'timestamp' => $this->timestamp,
            'source' => $this->source,
            'failCount' => $this->failCount,
            'nextRetry' => $this->nextRetry,
            'delayUntil' => $this->delayUntil
        ];
    }

    /**
     * @param array $data
     * @return QueueMessageInterface
     */
    public static function createFromArray(array $data): QueueMessageInterface
    {
        if (!self::isValidMessage($data)) {
            throw new InvalidArgumentException("Invalid message data");
        }
        return new self(
            $data['type'],
            $data['data'],
            $data['source'],
            $data['timestamp'] ?? 0,
            $data['failCount'] ?? 0,
            $data['nextRetry'] ?? 0,
            $data['delayUntil'] ?? 0
        );
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param int|string $identifier
     * @return void
     */
    public function setIdentifier(int|string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @param int $seconds
     * @return void
     */
    public function setDelay(int $seconds): void
    {
        $this->delayUntil = time() + $seconds;
    }

    /**
     * @return int
     */
    public function getDelayUntil(): int
    {
        return $this->delayUntil;
    }

    /**
     * @return void
     */
    public function incrementFailCount(): void
    {
        $this->failCount++;
    }

    /**
     * @param int $retryInterval
     * @return void
     */
    public function updateNextRetry(int $retryInterval): void
    {
        $this->nextRetry = time() + $retryInterval;
    }

    /**
     * @return int
     */
    public function getFailCount(): int
    {
        return $this->failCount;
    }

    /**
     * @return int
     */
    public function getNextRetry(): int
    {
        return $this->nextRetry;
    }

    /**
     * @return string|array|int
     */
    public function getData(): string|array|int
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * @param int $timestamp
     * @return void
     */
    public function setTimestamp(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string|array|int $data
     */
    public function setData(string|array|int $data): void
    {
        $this->data = $data;
    }

    /**
     * @param int $delayUntil
     */
    public function setDelayUntil(int $delayUntil): void
    {
        $this->delayUntil = $delayUntil;
    }

    /**
     * @param int $failCount
     */
    public function setFailCount(int $failCount): void
    {
        $this->failCount = $failCount;
    }

    /**
     * @param int $nextRetry
     */
    public function setNextRetry(int $nextRetry): void
    {
        $this->nextRetry = $nextRetry;
    }

    /**
     * @param string $source
     */
    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }
}