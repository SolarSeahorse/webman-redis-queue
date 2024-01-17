<?php

namespace SolarSeahorse\WebmanRedisQueue\Process;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RedisException;
use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Log\LogUtility;
use SolarSeahorse\WebmanRedisQueue\Queue\Factory\QueueConsumerFactory;
use SolarSeahorse\WebmanRedisQueue\Queue\QueueUtility;
use SolarSeahorse\WebmanRedisQueue\Redis\RedisQueueWorker;
use SolarSeahorse\WebmanRedisQueue\Timer\WorkerTimerManager;
use Throwable;
use Workerman\Worker;

class ConsumerProcess
{
    // Stores the path or class name of the consumer source
    protected string $_consumer_source = '';

    // Instance of the Redis Queue Worker
    protected RedisQueueWorker $redisQueueWorker;

    // Array of Redis connections
    protected array $redisConnections = [];

    /**
     * Constructor to initialize the consumer source and Redis Queue Worker.
     * @param string $consumer_source Path or class name of the consumer source.
     */
    public function __construct(string $consumer_source = '')
    {
        $this->_consumer_source = $consumer_source;
        $this->redisQueueWorker = RedisQueueWorker::getInstance();
    }

    /**
     * Called when the worker process starts.
     * It loads the consumer class or directory and starts the consumer.
     * @param Worker $worker Workerman worker instance.
     * @throws RedisException
     * @throws Throwable
     */
    public function onWorkerStart(Worker $worker): void
    {
        if (class_exists($this->_consumer_source)) { // Load Class Name
            if (!self::isConsumerClassValid($this->_consumer_source)) {
                echo "$this->_consumer_source is not a valid consumer class\r\n";
                LogUtility::warning("$this->_consumer_source is not a valid consumer class\r\n");
                return;
            }

            $this->subscribe($this->_consumer_source, $worker);

        } else { // Load Directory
            if (!is_dir($this->_consumer_source)) {
                echo "Consumer directory $this->_consumer_source not exists\r\n";
                LogUtility::warning("Consumer directory $this->_consumer_source not exists");
                return;
            }

            $this->loadConsumerSourceByDir($worker);
        }

        $this->redisQueueWorker->startConsumer($worker);
    }

    /**
     * @param Worker $worker
     * @return void
     * @throws RedisException
     * @throws Throwable
     */
    private function loadConsumerSourceByDir(Worker $worker): void
    {
        $dir_iterator = new RecursiveDirectoryIterator($this->_consumer_source);
        $iterator = new RecursiveIteratorIterator($dir_iterator);

        foreach ($iterator as $file) {
            if (!$file->isFile() || !$file->getExtension() == 'php') {
               continue;
            }

            $class = str_replace('/', "\\", substr(substr($file, strlen(base_path())), 0, -4));

            if (!self::isConsumerClassValid($class)) {
                continue;
            }

            $this->subscribe($class, $worker);
        }
    }

    /**
     * Checks if the given class is a valid consumer class.
     * @param string $class Name of the class to check.
     * @return bool True if valid, false otherwise.
     */
    public static function isConsumerClassValid(string $class): bool
    {
        return is_a($class, Consumer::class, true);
    }

    /**
     * Subscribes to a given class and starts consuming messages.
     * @param string|Consumer $class Consumer class to subscribe to.
     * @param Worker $worker Workerman worker instance.
     * @throws RedisException
     * @throws Throwable
     */
    private function subscribe(Consumer|string $class, Worker $worker): void
    {
        $consumer = QueueUtility::getConsumerInstance($class);

        $group_name = $consumer->getGroupName();

        $consumerName = "$group_name-$worker->id";

        $queueConsumer = $this->redisQueueWorker->subscribe(QueueConsumerFactory::create($class, $consumerName));

        $this->redisConnections[] = $queueConsumer->getRedisConnection();
    }

    /**
     * Called when the worker process stops.
     * It disconnects all Redis connections and deletes all timers.
     */
    public function onWorkerStop(): void
    {
        // Disconnect All Redis Connection
        foreach ($this->redisConnections as $redisConnection) {
            $redisConnection->disconnectAll();
        }
        // Delete All Timer
        WorkerTimerManager::instance()->delAll();
    }
}