<?php

namespace SolarSeahorse\WebmanRedisQueue\Queue;

use RedisException;
use SolarSeahorse\WebmanRedisQueue\Interface\DelayedQueueInterface;
use SolarSeahorse\WebmanRedisQueue\Redis\Redis;
use SolarSeahorse\WebmanRedisQueue\Redis\RedisConnection;
use SolarSeahorse\WebmanRedisQueue\Redis\RedisLuaScripts;

class DelayedQueue implements DelayedQueueInterface
{
    public function __construct(
        protected string                $delayedTaskSetKey,
        protected string                $delayedDataHashKey,
        protected Redis|RedisConnection $connection
    )
    {
    }

    /**
     * Add a task
     * @param $identifier
     * @param array $data
     * @param int $executeAtTimestamp
     * @return bool
     * @throws RedisException
     */
    public function addTask($identifier, array $data, int $executeAtTimestamp): bool
    {
        return is_array($this->addTasks([
            [
                'identifier' => $identifier,
                'data' => $data,
                'executeAtTimestamp' => $executeAtTimestamp
            ]
        ]));
    }

    /**
     * Add multiple tasks
     * @param array $tasks
     * @return array|false
     * @throws RedisException
     */
    public function addTasks(array $tasks): array|bool
    {
        $pipeline = $this->connection->pipeline();

        foreach ($tasks as $task) {
            $identifier = $task['identifier'];
            $data = json_encode($task['data']);
            $executeAtTimestamp = $task['executeAtTimestamp'];

            $pipeline->zAdd($this->delayedTaskSetKey, $executeAtTimestamp, $identifier);
            $pipeline->hSet($this->delayedDataHashKey, $identifier, $data);
        }

        return $pipeline->exec();
    }

    /**
     * Verify that the task exists
     * @throws RedisException
     */
    public function hasTaskExists(string $identifier): bool
    {
        return $this->connection->sIsMember($this->delayedTaskSetKey, $identifier);
    }

    /**
     * Remove a task
     * @throws RedisException
     */
    public function removeTask(string $identifier): bool
    {
        return $this->removeTasks([$identifier]);
    }

    /**
     * Remove multiple tasks
     * @throws RedisException
     */
    public function removeTasks(array $identifiers): array|bool
    {
        $this->connection->pipeline();
        foreach ($identifiers as $identifier) {
            $this->connection->zRem($this->delayedTaskSetKey, $identifier);
            $this->connection->hDel($this->delayedDataHashKey, $identifier);
        }

        return $this->connection->exec();
    }

    /**
     * Move due tasks to queue
     * @throws RedisException
     */
    public function moveDueTasksToStream(string $streamKey, int $currentTime, int $limit = 10): mixed
    {
        return RedisLuaScripts::execMoveDueTasksToStreamLua(
            $this->connection,
            $this->delayedTaskSetKey,
            $this->delayedDataHashKey,
            $streamKey,
            $currentTime,
            $limit
        );
    }
}