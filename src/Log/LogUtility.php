<?php

namespace SolarSeahorse\WebmanRedisQueue\Log;

use SolarSeahorse\WebmanRedisQueue\Config;
use SolarSeahorse\WebmanRedisQueue\Exceptions\LoggerConfigurationException;
use Psr\Log\LoggerInterface;

/**
 * @method static void log($level, $message, array $context = [])
 * @method static void debug($message, array $context = [])
 * @method static void info($message, array $context = [])
 * @method static void notice($message, array $context = [])
 * @method static void warning($message, array $context = [])
 * @method static void error($message, array $context = [])
 * @method static void critical($message, array $context = [])
 * @method static void alert($message, array $context = [])
 * @method static void emergency($message, array $context = [])
 */
class LogUtility
{
    private static ?LoggerInterface $logger = null;

    /**
     * @throws LoggerConfigurationException
     */
    public function __construct()
    {
        $config = Config::log();

        if (empty($config['enable'])) {
            return;
        }

        $logger = $config['handlers'] ?? null;

        if (is_null($logger)) {
            return;
        }

        if (!$logger instanceof LoggerInterface) {
            throw new LoggerConfigurationException("Logger configuration is not valid or logger is not an instance of LoggerInterface.");
        }

        self::$logger = $logger;
    }

    public static function __callStatic($method, $args)
    {
        $instance = (new self())::$logger;
        if (is_null($instance)) {
            return null;
        }
        return call_user_func_array([$instance, $method], $args);
    }
}