<?php

namespace SolarSeahorse\WebmanRedisQueue\Timer;

use Workerman\Timer;

class WorkerTimerManager
{
    private static array $timer = [];

    private static ?self $instance = null;

    private function __construct()
    {
    }

    private function __clone(): void
    {
        // TODO: Implement __clone() method.
    }

    public static function instance(): ?WorkerTimerManager
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function add($timerName, $interval, callable $callback, $args = [], $persistent = true): int
    {
        if ($this->getTimerId($timerName)) {
            $this->del($timerName);
        }

        if ($persistent === false) {
            return Timer::add($interval, $callback, $args, false);
        }

        self::$timer[$timerName] = Timer::add($interval, $callback, $args);

        return self::$timer[$timerName];
    }

    public function getTimerList(): array
    {
        return self::$timer;
    }

    public function getTimerId($timerName): ?int
    {
        return self::$timer[$timerName] ?? null;
    }

    public function del($timerName): void
    {
        Timer::del($this->getTimerId($timerName));
        unset(self::$timer[$timerName]);
    }

    public function delAll(): void
    {
        foreach ($this->getTimerList() as $item) {
            Timer::del($item);
        }
        self::$timer = [];
    }
}