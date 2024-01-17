<?php
return [
    'default' => [
        'host' => 'redis://127.0.0.1:6379',
        'options' => [
            'auth' => null,       // 密码，字符串类型，可选参数
            'db' => 0,            // 数据库
            'prefix' => 'webman_redis_queue_',      // key 前缀
            'timeout' => 2, // Timeout
            'ping' => 55,             // Ping
            'reconnect' => true,  // 断线重连
            'max_retries' => 5, // 最大重连次数
            'retry_interval' => 5 , // 重连间隔 s
        ]
    ],
];