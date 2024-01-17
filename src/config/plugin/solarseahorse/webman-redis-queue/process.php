<?php

return [
    'send-email' => [
        'handler' => SolarSeahorse\WebmanRedisQueue\Process\ConsumerProcess::class,
        'count' => 20, // 在目录模式中,目录下所有队列是共用进程
        'constructor' => [
            // 支持目录和类 推荐使用类名
            'consumer_source' => \App\queue\test\SendEmail::class
        ]
    ]
];