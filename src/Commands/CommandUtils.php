<?php

namespace SolarSeahorse\WebmanRedisQueue\Commands;

use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Queue\QueueUtility;

class CommandUtils
{
    public static function validateQueueName(string $queueName): bool
    {
        if (!preg_match('/^[a-zA-Z-]+$/', $queueName)) {
            throw new \InvalidArgumentException('Error: Queue name must contain only letters and hyphens.');
        }
        return true;
    }

    public static function getProcessConfigFile(): string
    {
        return config_path() . '/plugin/solarseahorse/webman-redis-queue/process.php';
    }

    public static function getProcessConfig(string $queueName = '')
    {
        $config = self::readProcessConfigFile() ?? [];

        return !$queueName ? $config : $config[$queueName] ?? null;
    }

    public static function getProcessConsumerSource(string $queueName = '')
    {
        return CommandUtils::getProcessConfig($queueName)['constructor']['consumer_source'];
    }

    public static function validateQueueNameExists(string $queueName): bool
    {
        $config = CommandUtils::getProcessConfig();

        return isset($config[$queueName]);
    }

    public static function setProcessConfig(string $queueName, array $newConfig = [], bool $save = false): array
    {
        $config = self::getProcessConfig();

        if (isset($config[$queueName])) {
            throw new \InvalidArgumentException('Queue already exists!');
        }

        $config[$queueName] = $newConfig;

        if ($save) {
            self::writeProcessConfigToFile($config);
        }

        return $config[$queueName];
    }

    public static function removeProcessConfig(string $queueName, bool $save = false): bool
    {
        $config = self::getProcessConfig();

        unset($config[$queueName]);

        if ($save) {
            self::writeProcessConfigToFile($config);
        }

        return true;
    }

    public static function getConsumerInstanceByQueueName($queueName): Consumer
    {
        $consumer_source = CommandUtils::getProcessConsumerSource($queueName);

        return QueueUtility::getConsumerInstance($consumer_source);
    }


    /**
     * @throws \RedisException
     */
    public static function cleanQueueRedisData($consumer_source): bool|int|\Redis
    {
        $consumerInstance = QueueUtility::getConsumerInstance($consumer_source);

        return $consumerInstance->getRedisConnection()->del(
            $consumerInstance->getDelayedDataHashKey(),
            $consumerInstance->getDelayedTaskSetKey(),
            $consumerInstance->getStreamKey()
        );
    }

    public static function readProcessConfigFile()
    {
        return include self::getProcessConfigFile();
    }

    public static function writeProcessConfigToFile(array $config): void
    {
        $exportedConfig = var_export($config, true);
        file_put_contents(self::getProcessConfigFile(), "<?php\n\nreturn $exportedConfig;\n");
    }

    public static function pathToNamespace(string $path): array|string
    {
        return str_replace('/', '\\', $path);
    }

    public static function classNameToPath(string $className): string
    {
        return str_replace("\\", DIRECTORY_SEPARATOR, $className) . '.php';
    }

    public static function getFullPath(string $path, string $className): string
    {
        return rtrim($path, '/') . '/' . $className . '.php';
    }

    public static function parseQueueName($name): array|string
    {
        $name = ucwords($name, '-');
        return str_replace('-', '', $name);
    }

}