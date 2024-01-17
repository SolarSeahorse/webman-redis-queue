<?php

namespace SolarSeahorse\WebmanRedisQueue;

class Config
{
    public static function getConfig($name = null)
    {
        $config = config('plugin.solarseahorse.webman-redis-queue');

        return !$name ? $config : $config[$name] ?? '';
    }

    public static function database(): array
    {
        return self::getConfig('database');
    }

    public static function redis(): array
    {
        return self::getConfig('redis');
    }

    public static function log(): array
    {
        return self::getConfig('log');
    }

    public static function process(): array
    {
        return self::getConfig('process');
    }
}