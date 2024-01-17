<?php

namespace SolarSeahorse\WebmanRedisQueue\Redis;

use Exception;
use RedisException;
use SolarSeahorse\WebmanRedisQueue\Log\LogUtility;
use Throwable;
use Workerman\Timer;
use Workerman\Worker;

class RedisConnection extends \Redis
{
    /**
     * @var array
     */
    protected array $config = [];

    /**
     * @param array $config
     * @return void
     * @throws RedisException|Throwable
     */
    public function connectWithConfig(array $config = []): void
    {
        static $timer;
        if ($config) {
            $this->config = $config;
        }
        if (false === $this->connect($this->config['host'], $this->config['port'], $this->config['timeout'] ?? 2)) {
            throw new RedisException("Redis connect {$this->config['host']}:{$this->config['port']} fail.");
        }
        if (!empty($this->config['auth'])) {
            $this->auth($this->config['auth']);
        }
        if (!empty($this->config['db'])) {
            $this->select($this->config['db']);
        }
        if (!empty($this->config['prefix'])) {
            $this->setOption(\Redis::OPT_PREFIX, $this->config['prefix']);
        }
        if (Worker::getAllWorkers() && !$timer) {
            $timer = Timer::add($this->config['ping'] ?? 55, function () {
                $this->execCommand('ping');
            });
        }
    }

    /**
     * @return bool|null
     * @throws Throwable
     */
    protected function reconnect(): ?bool
    {
        static $reconnecting;

        if ($reconnecting || empty($this->config['reconnect'])) {
            return null;
        }

        $maxRetries = $this->config['max_retries'];
        $retryInterval = $this->config['retry_interval'];

        $reconnecting = true;

        LogUtility::warning("Redis reconnection starts...");

        echo "Redis reconnection starts... \n\n";

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {

            LogUtility::warning("Redis reconnection " . ($attempt + 1) . " ....");

            echo "Redis reconnection " . ($attempt + 1) . " .... \n\n";

            try {
                $this->connectWithConfig($this->config);
                $reconnecting = false;

                LogUtility::notice("Redis reconnection successful!");
                echo "Redis reconnection successful! \n\n";

                return true; // Reconnect successfully, exit the loop
            } catch (RedisException) {
                sleep($retryInterval); // Wait for some time and try again
            }
        }

        throw new Exception('Redis Reconnection failed.');
    }

    /**
     * @param $message
     * @return bool
     */
    private function shouldRetry($message): bool
    {
        $message = strtolower($message);
        return (
            str_contains($message, 'connect') ||
            str_contains($message, 'went away') ||
            str_contains($message, 'error on read')
        );
    }

    /**
     * @throws Throwable
     * @throws RedisException
     */
    public function execCommand($command, ...$args)
    {
        try {
            return call_user_func_array([$this, $command], $args);
        } catch (Throwable $e) {

            if ($this->shouldRetry($e->getMessage())) {

                $this->reconnect();

                return call_user_func_array([$this, $command], $args);
            }

            LogUtility::warning("Redis Error executing $command", [
                'command' => $command,
                'args' => $args,
                'message' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}