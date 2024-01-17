<?php

namespace SolarSeahorse\WebmanRedisQueue\Redis;

use SolarSeahorse\WebmanRedisQueue\Exceptions\QueueDoesNotExistException;
use SolarSeahorse\WebmanRedisQueue\Interface\QueueConsumerInterface;
use SolarSeahorse\WebmanRedisQueue\Log\LogUtility;
use SolarSeahorse\WebmanRedisQueue\Queue\QueueConsumer;
use SolarSeahorse\WebmanRedisQueue\Timer\WorkerTimerManager;
use RedisException;
use Throwable;
use Workerman\Worker;

class RedisQueueWorker
{
    /**
     * @var ?RedisQueueWorker $instance
     */
    private static ?RedisQueueWorker $instance = null;

    /**
     * @var QueueConsumer[] $queues
     */
    protected static array $queues = [];

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public function __destruct()
    {
        self::$queues = [];
    }

    /**
     * @return RedisQueueWorker
     */
    public static function getInstance(): RedisQueueWorker
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param Worker $worker
     * @return void
     * @throws Throwable
     */
    public function startConsumer(Worker $worker): void
    {
        $queues = $this->getQueues();

        $this->createConsumerGroups($queues);

        foreach ($queues as $queue) {

            // Single process
            if ($worker->id == 0) {

                // Processing PEL timeout
                $timerId = $queue->getConsumerName() . '_handle_pending_messages';
                $interval = max($queue->getCheckPendingTimerInterval(), 1);
                WorkerTimerManager::instance()->add(
                    $timerId,
                    $interval,
                    function () use ($worker, $queue) {
                        $queue->handlePendingTimeoutMessages();
                    });
            }

            // Process queue message consumption
            WorkerTimerManager::instance()->add(
                $queue->getConsumerName(),
                $queue->getConsumerTimerInterval(),
                function () use ($worker, $queue) {
                    $queue->consumeMessages();
                });

            // Process delay queue
            $delayedMessagesMaxWorkerCount = $queue->getDelayedMessagesMaxWorkerCount();
            if (($delayedMessagesMaxWorkerCount > 0 && $worker->id < $delayedMessagesMaxWorkerCount) || $delayedMessagesMaxWorkerCount == -1) {

                $timerId = $queue->getConsumerName() . '_handle_du_tasks';
                $interval = max($queue->getDelayedMessagesTimerInterval(), 1);

                WorkerTimerManager::instance()->add(
                    $timerId,
                    $interval,
                    function () use ($worker, $queue) {
                        $queue->handleDueTasks();
                    });

            }
        }
    }

    /**
     * @param QueueConsumer[] $queues
     * @return void
     */
    public function createConsumerGroups(array $queues): void
    {
        foreach ($queues as $queue) {
            $streamKey = $queue->getStreamKey();
            $groupName = $queue->getGroupName();
            $connection = $queue->getRedisConnection();
            try {
                $connection->xGroup('CREATE', $streamKey, $groupName, 0, true);
            } catch (RedisException) {
            }
        }
    }

    /**
     * @param QueueConsumer $queueConsumer
     * @return QueueConsumerInterface
     */
    public function subscribe(QueueConsumerInterface $queueConsumer): QueueConsumerInterface
    {
        self::$queues[$queueConsumer->getQueueName()] = $queueConsumer;
        return self::$queues[$queueConsumer->getQueueName()];
    }

    /**
     * @param $queueName
     * @return void
     */
    public function unsubscribe($queueName): void
    {
        unset(self::$queues[$queueName]);
    }

    /**
     * @param $queueName
     * @return QueueConsumerInterface
     * @throws QueueDoesNotExistException
     */
    public function getQueue($queueName): QueueConsumerInterface
    {
        $this->checkQueueExists($queueName);

        return self::$queues[$queueName];
    }

    /**
     * @return QueueConsumer[]
     */
    public function getQueues(): array
    {
        return self::$queues;
    }

    /**
     * @throws QueueDoesNotExistException
     */
    public function checkQueueExists($queueName): void
    {
        if (!isset(self::$queues[$queueName])) {
            LogUtility::warning("QueueConsumer $queueName does not exist.");
            throw new QueueDoesNotExistException("QueueConsumer $queueName does not exist.");
        }
    }
}