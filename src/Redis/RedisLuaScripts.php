<?php

namespace SolarSeahorse\WebmanRedisQueue\Redis;

use RedisException;

class RedisLuaScripts
{
    const MOVE_DUE_TASKS_TO_STREAM = 'moveDueTasksToStream';

    protected static array $scripts = [
        self::MOVE_DUE_TASKS_TO_STREAM => <<<LUA
local delayedTaskSetKey = KEYS[1]
local delayedDataHashKey = KEYS[2]
local streamKey = KEYS[3]
local currentTime = tonumber(ARGV[1])
local limit = tonumber(ARGV[2])

local identifiers = redis.call('zrangebyscore', delayedTaskSetKey, 0, currentTime, 'LIMIT', 0, limit)
if #identifiers == 0 then
    return {}
end

local results = {}
for i, identifier in ipairs(identifiers) do
    local taskData = redis.call('hget', delayedDataHashKey, identifier)
    if taskData ~= false then
        local messageId = redis.call('xadd', streamKey, '*', 'message', taskData)
        table.insert(results, messageId)
    end
end

redis.call('hdel', delayedDataHashKey, unpack(identifiers))
redis.call('zrem', delayedTaskSetKey, unpack(identifiers))

return results
LUA,
    ];

    public static function getScript($name)
    {
        return self::$scripts[$name] ?? null;
    }

    /**
     * @throws RedisException
     */
    public static function execMoveDueTasksToStreamLua(
        Redis|RedisConnection $connection,
        string                $delayedTaskSetKey,
        string                $delayedDataHashKey,
        string                $streamKey,
        int                   $currentTime,
        int                   $limit
    )
    {
        $args = array_merge([
            $delayedTaskSetKey,
            $delayedDataHashKey,
            $streamKey
        ], [$currentTime, $limit]);

        return $connection->eval(self::getScript(self::MOVE_DUE_TASKS_TO_STREAM), $args, 3);
    }
}