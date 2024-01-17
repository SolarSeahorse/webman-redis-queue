<?php

namespace SolarSeahorse\WebmanRedisQueue\Interface;

interface DelayedQueueInterface
{
    public function addTask($identifier, array $data, int $executeAtTimestamp): bool;

    public function addTasks(array $tasks): array|bool;

    public function hasTaskExists(string $identifier): bool;

    public function removeTask(string $identifier): bool;

    public function removeTasks(array $identifiers): array|bool;

    public function moveDueTasksToStream(string $streamKey, int $currentTime, int $limit = 10): mixed;
}