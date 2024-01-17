<?php

namespace SolarSeahorse\WebmanRedisQueue\Redis;

use RedisException;
use RuntimeException;
use SolarSeahorse\WebmanRedisQueue\Config;
use Throwable;

class Redis
{
    /**
     * @var RedisConnection[]
     */
    private static array $_connections = [];

    private static array $instances = [];

    private string $connectionName;

    /**
     * @throws Throwable
     * @throws RedisException
     */
    private function __construct(string $connectionName)
    {
        $this->connectionName = $connectionName;

        $this->connection($this->connectionName);
    }

    private function __clone(): void
    {
        // TODO: Implement __clone() method.
    }

    /**
     * @param string $connectionName
     * @return Redis
     * @throws RedisException
     * @throws Throwable
     */
    public static function getInstance(string $connectionName = 'default'): Redis
    {
        $instance = self::$instances[$connectionName] ?? null;
        if (!$instance instanceof self) {
            self::$instances[$connectionName] = new self($connectionName);
        }
        return self::$instances[$connectionName];
    }

    /**
     * @param string $name
     * @return RedisConnection
     * @throws RedisException|Throwable
     */
    protected function connection(string $name = 'default'): RedisConnection
    {
        if (!isset(static::$_connections[$name])) {
            $configs = Config::redis();
            if (!isset($configs[$name])) {
                throw new RuntimeException("RedisQueue connection $name not found");
            }
            $config = $configs[$name];
            static::$_connections[$name] = $this->createRedisConnection($config);
        }
        return static::$_connections[$name];
    }

    /**
     * @return RedisConnection
     * @throws RedisException
     * @throws Throwable
     */
    public function getCurrentConnection(): RedisConnection
    {
        return $this->connection($this->connectionName);
    }

    /**
     * @return array
     */
    public function getConnections(): array
    {
        return self::$_connections;
    }

    /**
     * @return int
     */
    public function disconnectAll(): int
    {
        $i = 0;

        foreach ($this->getConnections() as $connection) {
            $this->disconnect($connection) && $i++;
        }

        return $i;
    }

    /**
     * @param string|RedisConnection $connection
     * @return bool
     */
    public function disconnect(string|RedisConnection $connection): bool
    {
        if (!is_object($connection)) {
            $connection = static::$_connections[$connection] ?? null;
        }

        if (!$connection instanceof RedisConnection) {
            return false;
        }

        try {
            return $connection->close();
        } catch (RedisException) {
            return false;
        }
    }

    /**
     * @throws RedisException|Throwable
     */
    protected function createRedisConnection($config): RedisConnection
    {
        if (!extension_loaded('redis')) {
            throw new RuntimeException('Please make sure the PHP Redis extension is installed and enabled.');
        }

        $redis = new RedisConnection();
        $address = $config['host'];
        $config = [
            'host' => parse_url($address, PHP_URL_HOST),
            'port' => parse_url($address, PHP_URL_PORT),
            'db' => $config['options']['database'] ?? $config['options']['db'] ?? 0,
            'auth' => $config['options']['auth'] ?? '',
            'timeout' => $config['options']['timeout'] ?? 2,
            'ping' => $config['options']['ping'] ?? 55,
            'prefix' => $config['options']['prefix'] ?? '',
            'reconnect' => $config['options']['reconnect'] ?? true,
            'max_retries' => $config['options']['max_retries'] ?? 3,
            'retry_interval' => $config['options']['retry_interval'] ?? 5,
        ];

        $redis->connectWithConfig($config);
        return $redis;
    }

    /**
     * @throws Throwable
     * @throws RedisException
     */
    public function __call($name, $arguments)
    {
        return $this->getCurrentConnection()->execCommand($name, ...$arguments);
    }
}