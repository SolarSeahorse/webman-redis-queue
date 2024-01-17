# Webman Redis Queue 插件

## 简介

`webman-redis-queue` 是为 [Webman](https://www.workerman.net/doc/webman/install.html) 框架设计的高效、灵活的 Redis
队列插件。利用 Redis Stream 的强大特性，该插件专注于提供可靠和高性能的消息队列解决方案，适合处理大规模的数据流和复杂的队列操作。

### 主要特性

- **基于 Redis Stream：** 使用 Redis 最新的 Stream 数据类型，为消息队列和事件流提供优化的存储和访问。
- **自定义异常重试：** 支持自定义的消息处理失败重试机制，提高消息处理的可靠性。
- **死信队列处理：** 集成死信队列管理，确保消息不会因处理失败而丢失。
- **延时队列支持：** 实现延时消息处理，使得定时任务和延迟执行变得简单易行。
- **高效的异常处理机制：** 强化的异常处理策略，确保队列的稳定运行。

## 安装

通过 Composer 安装 `webman-redis-queue`：

```bash
composer require solarseahorse/webman-redis-queue:^1.0.0
```

### 测试和反馈

我们非常欢迎并鼓励您在测试环境中尝试这个插件，并且分享您的使用体验。您的反馈对我们改进插件、修复潜在的问题以及发布未来的稳定版本非常重要。如果您在使用过程中遇到任何问题或有任何建议，请通过 [GitHub Issues](https://github.com/SolarSeahorse/webman-redis-queue/issues)
与我联系。

### 参与贡献

如果您对改进 webman-redis-queue 有兴趣，欢迎任何形式的贡献，包括但不限于：提交问题、提供反馈、或直接向代码库提交改进。您的贡献将帮助我们更快地推出稳定、功能丰富的正式版本。

## 配置

配置文件自动生成在 config/plugin/solarseahorse/webman-redis-queue目录下。

### 1. Redis配置 redis.php

```php
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
```

> 在webman集群下，每个节点需要连接同一个redis。

## 断线重连

> 注意：开启此选项能增加队列运行稳定性，但如果队列进程过多，redis恢复后可能造成突发大量连接数，因为每个进程都有一个redis连接。

默认开启，当Redis发生`重载`,`重启`等情况会尝试重连，超过最大重试次数后会报错并重启进程(webman默认行为)。

### 2. 日志配置 log.php

推荐为插件配置单独日志通道，参考链接 [webman日志](https://www.workerman.net/doc/webman/log.html "webman日志")

```php

<?php

return [
    'enable' => true, // 启用日志
    'handlers' => support\Log::channel('default') // 默认通道 default
];
```

在队列消费业务逻辑中可以这样使用日志,使用方法和官方的`Log`类使用方法一致。

```php

LogUtility::warning('Error:', [
      'data' => $consumerMessage->getData(),
      'errorMessage' => $e->getMessage()
]);

```

### 4. 队列配置 process.php

1. 在加载类名模式下，每个队列都拥有独立的运行进程。
2. 每个队列的配置和数据存储KEY都是独立的。
3. 不推荐目录模式是因为多个队列共享进程，其中某个队列出现异常可能影响到其他队列。
4. 队列的详细配置都在消费类中配置，配置文件只是基本的进程配置。

```php
<?php

return [
    'send-email' => [
        'handler' => SolarSeahorse\WebmanRedisQueue\Process\ConsumerProcess::class,
        'count' => 20, // 在目录模式中,目录下所有队列是共用进程
        'constructor' => [
            // 支持目录和类 推荐使用类名
            'consumer_source' => \App\queue\test\Email::class
        ]
    ]
];

```

## 定义消费类

插件对消费类对位置没有固定要求，符合加载规范即可。

教程以`app/queue/SendEmail.php`举例,目录和文件需自行创建。

继承 `SolarSeahorse\WebmanRedisQueue\Consumer`,配置连接标识,并实现抽象方法`consume`, 一个最基础的消费类就创建好了。

```php
<?php

namespace app\queue\test;

use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Interface\ConsumerMessageInterface;

class SendEmail extends Consumer
{
    // 连接标识，对应config/plugin/solarseahorse/webman-redis-queue/redis.php的配置
    protected string $connection = 'default';

    public function consume(ConsumerMessageInterface $consumerMessage)
    {
        // TODO: Implement consume() method.
    }
}
```

> 编写完成后需要在队列配置文件`process.php`中新增队列配置。

### 通过命令行创建

通过 `php webman solar:make:consumer` 命令可快速创建一个消费类。

示例操作：

```shell
webman % php webman solar:make:consumer

Please enter the name of the queue: sendCode

Please enter the number of processes (default 1): 1

Please enter the path to create the class in [app/queue]: app/queue/test
```

最终将会在 `app/queue/test` 目录中创建 `SendCode.php` 文件。

```php
<?php

namespace app\queue\test;

use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Interface\ConsumerMessageInterface;

class SendCode extends Consumer
{
    // 连接标识，对应config/plugin/solarseahorse/webman-redis-queue/redis.php的配置
    protected string $connection = 'default';

    // 消费
    public function consume(ConsumerMessageInterface $consumerMessage)
    {
        // TODO: Implement consume() method.

        // 获取消息ID
        $messageId = $consumerMessage->getMessageId();

        // 获取队列数据
        $data = $consumerMessage->getData();

        var_dump($messageId);
    }
}

```

队列配置文件`process.php`也会自动更新。

```php
<?php

return array (
  'sendCode' => 
  array (
    'handler' => 'SolarSeahorse\\WebmanRedisQueue\\Process\\ConsumerProcess',
    'count' => 1,
    'constructor' => 
    array (
      'consumer_source' => 'app\\queue\\test\\SendCode',
    ),
  ),
);
```

## 配置属性

### `protected string $connection = 'default';`

- 连接标识，用于指定 Redis 连接配置。

### `protected string $queueName = '';`

- 队列名称，默认自动生成。

### `protected string $groupName = '';`

- 队列分组名，默认自动生成。

### `protected string $streamKey = '';`

- Stream key，默认自动生成。

### `protected int $prefetchCount = 1;`

- 返回消息的最大数量。默认为1 不建议修改
- 消费速度可通过提高进程数并行处理消息，消费者每次读取多条数据是循环消费，极端情况如循环消费一半进程重启会造成大量消息挂起。

### `protected int $blockTime = 5000;`

- 当无消息时堵塞等待的毫秒数，也可作为无消息时的休眠时长。如果队列以延时队列为主，应与延时队列间隔相近。

### `protected float $consumerTimerInterval = 0.5;`

- 消费者处理间隔，消费完一条消息后的等待时间（秒）。

### `protected int $maxAttempts = 5;`

- 消费失败后的最大重试次数。

### `protected int $retrySeconds = 60;`

- 重试间隔（秒）。

### `protected bool $autoAck = true;`

- 是否自动确认消息。开启的同时同样建议在业务逻辑中显式调用 `ack`方法。

### `protected bool $autoDel = true;`

- 是否自动删除已确认成功的消息。

### `protected int $delayedQueueOnceHandlerCount = 128;`

- 延时队列每次处理数量，根据生产速率适当配置。

### `protected int $delayedMessagesTimerInterval = 1;`

- 延时消息处理间隔（秒）。

### `protected int $delayedMessagesMaxWorkerCount = 1;`

- 延时队列最大进程数，默认单线程，只会在一个进程开启延时队列处理。

### `protected string $delayedTaskSetKey = '';`

- 延时队列 SET KEY，默认自动生成。

### `protected string $delayedDataHashKey = '';`

- 延时队列 HASH KEY，默认自动生成。

### `protected string $waitingDeleteMessagesKey = '';`

- 消息挂起超时处理策略。`PENDING_PROCESSING_RETRY` 或 `PENDING_PROCESSING_IGNORE`。
- `PENDING_PROCESSING_RETRY ` 当消息挂起超时会进行异常重试，如果配置了数据库，极端情况`ACK`失败但业务逻辑处理完毕时，异常重试会被跳过。
- `PENDING_PROCESSING_IGNORE` 当消息挂起超时时，触发`死信处理`方便排查错误，除此之外只清理`pending`列表，不做其他处理。
- 默认 `PENDING_PROCESSING_RETRY` , 根据队列场景选择合适的处理策略，比如`发送短信验证码`
  ，当系统出现了崩溃等情况，恢复上线时，一般情况下这类消息时不需要恢复，此时重新给用户发送验证码没有意义，但因为`Redis
  Stream`特性，未ack的消息会在`pending`列表中不会丢失，这类场景就适合配置`PENDING_PROCESSING_IGNORE`

### `protected int $pendingTimout = 300;`

- 消息挂起超时时间（秒）。
- 在Redis Stream中当消息被消费者读取，但没有确认(ACK)时，消息会处于挂起状态进入`pending`列表。
- 如果消息处理缓慢，此值应尽可能调大，避免将正常处理的消息当成超时处理掉。

### `protected int $checkPendingTimerInterval = 60;`

- 检查 pending 列表的间隔时间（秒）。

### `protected int $onceCheckPendingCount = 50;`

- 每次检查 pending 列表的消息数量。

## 投递消息

通过`pushMessage`方法可快速向队列投递一条消息。

```php

/**
 * @param mixed|QueueMessageInterface $data
 * @return string|bool
 * @throws QueueMessagePushException
 */
 
 public function pushMessage(string|array|int|QueueMessageInterface $data): string|bool;

```

```php

// 消息内容，无需序列化
$message = [
    'dummy' => 'ok'
];

// 生产者工厂方法
$messageId = QueueProducerFactory::create(app\queue\test\SendEmail::class)
    ->pushMessage($message);

// 通过消费类工厂方法 创建一个生产者
$messageId = app\queue\test\SendEmail::createQueueProducer()->pushMessage($message);

// 投递QueueMessage对象
$message = app\queue\test\SendEmail::createQueueMessage($message);

// 或者通过QueueMessageFactory创建一条消息
$message = QueueMessageFactory::create(app\queue\test\SendEmail::class,$message);

// 修改队列数据
$message->setData(['dummy' => 'no']);

// 设置错误次数
$message->setFailCount(3);

// 通过上方两种方法投递均可
$messageId = app\queue\test\SendEmail::createQueueProducer()->pushMessage($message);

var_export($messageId); // 返回stream的字符串ID 或 false
```

有时候我们需要一次投递大量队列时，可以通过`pushMessages`方法，批量投递消息，此方法会开启`Redis`的`pipeline`
管道投递，提高与`redis`的交互性能。

```php

/**
 * @param array|QueueMessageInterface[] $dataArray
 * @return array|false
 * @throws QueueMessagePushException
 */
 
 public function pushMessages(array $dataArray): array|bool;

```

```php

// 投递5w条消息
$dataArr = array_fill(0, 50000, null);

for ($i = 0; $i < 50000; $i++) {
    $dataArr[$i] = ['dummy' => uniqid()];
}

$messageIds = app\queue\test\SendEmail::createQueueProducer()->pushMessages($dataArr);


// QueueMessage方式

for ($i = 0; $i < 50000; $i++) {

    $message = QueueMessageFactory::create(app\queue\test\SendEmail::class, ['dummy' => uniqid()]);

    //$message->setData(json_encode(['123']));
    //$message->setFailCount(1);
    // ....

    $dataArr[$i] = $message;
}

$messageIds = app\queue\test\SendEmail::createQueueProducer()->pushMessages($dataArr);

var_export($messageIds); // 返回Stream消息ID列表 或 false
        
```

> 数组投递实际是通过数组创建一个`QueueMessage`对象

## 延时消息

**延时消息的作用：**

1. 定时任务：
   延时消息可以用来实现定时任务。例如，你可能想在未来的某个时间点执行特定操作，如发送提醒、更新状态等。

2. 延迟处理：
   在某些情况下，立即处理消息并不理想或可能。延时消息允许应用程序延迟处理，直到最合适的时机。

3. 限流：
   延时消息可以帮助对系统内部的请求进行限流，防止在短时间内因大量请求而过载。

4. 解耦和异步处理：
   在复杂的系统中，延时消息可以用来解耦不同组件间的直接交互，提高系统的可扩展性和维护性。

通过 `scheduleDelayedMessage` 方法快速投递一条延时消息。

```php
    /**
     * @param mixed|QueueMessageInterface $data
     * @param int $delay
     * @param string $identifier
     * @return bool
     * @throws ScheduleDelayedMessageException
     */
    public function scheduleDelayedMessage(string|array|int|QueueMessageInterface $data, int $delay = 0, string $identifier = ''): bool;
```

```php

// 消息内容
$message = [
    'type' => 'warning',
    'to' => 'xxxx@email.com',
    'content' => '.....'
];

// 投递一条延时消息 60秒后处理
app\queue\test\SendEmail::createQueueProducer()->scheduleDelayedMessage($message, 60);

// QueueMessage对象

$message = app\queue\test\SendEmail::createQueueMessage($message);

// 设置延时
$message->setDelay(60);

// 投递一条延时消息 60秒后处理
app\queue\test\SendEmail::createQueueProducer()->scheduleDelayedMessage($message);

// 使用第二个参数会替换之前对象的延时设置
app\queue\test\SendEmail::createQueueProducer()->scheduleDelayedMessage($message,80);

```

如果我们想避免消息被重复发送等情况，通过延时队列的特性可以很简单实现。通过`scheduleDelayedMessage`
方法的第三个参数`identifier`传递一个自定义的延时消息ID，同样的消息ID,消息将会被替换，延时时间从修改开始重新计算。

> 如果消息已经进入stream队列将无法实现替换，必须在延时时间内，类似实现一个“防抖”效果，消息在时间段内发送多次最终只处理一次。

```php

// 消息内容
$message = [
    'type' => 'warning',
    'to' => 'xxxx@email.com',
    'content' => '.....'
];

// 通过type,to参数生成一个唯一ID
$identifier = md5(serialize([
    'type' => 'warning',
    'to' => 'xxxx@email.com',
]));

// 投递一条延时消息 60秒后处理
app\queue\test\SendEmail::createQueueProducer()->scheduleDelayedMessage($message, 60, $identifier);

// QueueMessage对象

$message = app\queue\test\SendEmail::createQueueMessage($message);

// 设置延时
$message->setDelay(60);

// 设置identifier
$message->setIdentifier($identifier);

// 投递一条延时消息 60秒后处理
app\queue\test\SendEmail::createQueueProducer()->scheduleDelayedMessage($message);

// 使用第二个参数会替换之前对象设置
app\queue\test\SendEmail::createQueueProducer()->scheduleDelayedMessage($message, 80, $identifier);

```

当一次需要投递大量延时消息时，可以通过`scheduleDelayedMessages`方法发送。

```php

// 投递10w条延时消息
$dataArr = array_fill(0, 100000, null);

for ($i = 0; $i < 100000; $i++) {
    $dataArr[$i] = [
        'delay' => 2, // 延时时间
        'data' => ['dummy' => uniqid()], // 队列数据
        'identifier' => '' // 自定义ID
    ];
}

// 批量投递
app\queue\test\SendEmail::createQueueProducer()->scheduleDelayedMessages($dataArr);

// QueueMessage对象

for ($i = 0; $i < 100000; $i++) {

    $message = app\queue\test\SendEmail::createQueueMessage(['dummy' => uniqid()]);

    // 设置延时
    $message->setDelay(60);

    // 设置identifier
    $message->setIdentifier('');

    $dataArr[$i] = $message;
}

// 批量投递
app\queue\test\SendEmail::createQueueProducer()->scheduleDelayedMessages($dataArr);

```

> 多redis只需要在队列配置`connection`连接标识，投递方式没有任何变化。

## 消费消息

消费消息时会调用消费类的`consume`方法，并传递一个实现`ConsumerMessageInterface`接口对象。

```
<?php

namespace app\queue\test;

use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Interface\ConsumerMessageInterface;

class SendEmail extends Consumer
{
    // 连接标识，对应redis.php的配置 默认default
    protected string $connection = 'default';

    public function consume(ConsumerMessageInterface $consumerMessage)
    {
        // TODO: Implement consume() method.

        // 获取消息ID
        $messageId = $consumerMessage->getMessageId();

        // 获取队列数据
        $data = $consumerMessage->getData();

        // 禁用错误重试 如果消费失败将不会异常重试
        $consumerMessage->disableFailRetry();

        // 手动触发错误重试，此方法会调用disableFailRetry方法，所以后续报错不会再触发异常重试。
        // 没有禁用错误重试的情况下，消费异常默认会调用此方法。
        $consumerMessage->triggerError(new \Exception('triggerError'));

        // 监听消费异常事件
        $consumerMessage->onError(function (\Throwable $e, ConsumerMessageInterface $consumerMessage) {
            // 这里可以处理消费异常逻辑

            // 禁用错误重试
            $consumerMessage->disableFailRetry();

            // 添加日志等等
            // 如果在消费方法中自行捕获 Throwable 此事件不会触发
        });

        // 业务逻辑执行完毕，ack确认消息 默认自动ack，但通常建议在业务逻辑中显式调用，比如ack失败进行事务回滚等等。
        $isAcked = $consumerMessage->ack();

        if (!$isAcked) {
            
        }

        // 或通过getAckStatus方法获取结果
        if (!$consumerMessage->getAckStatus()) {
            
        }

        // 获取原始队列消息 QueueMessage对象
        $queueMessage = $consumerMessage->getQueueMessage();

        // 获取消息错误次数...
        $failCount = $queueMessage->getFailCount();
        // 更多...
    }
}
```

上方示例主要演示可调用的方法，下面使用一个更加贴合实际的demo，更快了解消费业务逻辑的编写。

### 发送邮件验证码

场景特点：获取验证码的操作一般由用户手动触发，在这类场景中，错误重试应用户在前端UI倒计时结束后重新手动发起，如果业务出现崩溃，再次上线后重新发送验证码给用户已经没有意义了。我们可以通过配置适应这类场景，代码示例：

```
<?php

namespace app\queue\test;

use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Interface\ConsumerMessageInterface;

class SendEmail extends Consumer
{
    // 连接标识，对应redis.php的配置 默认default
    protected string $connection = 'default';

    // 将pending处理策略调整为PENDING_PROCESSING_IGNORE 消息挂起超时将不会进行重试
    protected int $pendingProcessingStrategy = self::PENDING_PROCESSING_IGNORE;

    public function consume(ConsumerMessageInterface $consumerMessage)
    {
        // TODO: Implement consume() method.

        // 获取消息ID
        $messageId = $consumerMessage->getMessageId();

        // 获取队列数据
        $data = $consumerMessage->getData();

        // 监听异常
        $consumerMessage->onError(function (\Throwable $e){

            // 记录邮件发送失败日志
        });
        
        // 禁用重试
        $consumerMessage->disableFailRetry();

        // 发送一封邮件 ....

        // 确认消息
        $consumerMessage->ack();
    }
}

```

### 自定义错误重试

消费类继承的抽象类`Consumer`默认实现了`handlerFailRetry`
方法，在触发异常重试时，会调用此方法，如果您想自定义错误重试逻辑，或加入更多自定义的处理，在本插件中可以轻松实现，并且每个队列都支持自定义配置。

```
/**
     * 处理错误重试 没有超过最大重试次数 会调用此方法
     * @param $messageId
     * @param ConsumerMessageInterface $consumerMessage
     * @param Throwable $e
     * @return bool
     * @throws ScheduleDelayedMessageException
     * @throws RedisException
     * @throws Throwable
     */
    public function handlerFailRetry($messageId, ConsumerMessageInterface $consumerMessage, Throwable $e): bool
    {
        $queueMessage = $consumerMessage->getQueueMessage();

        // 检查是否超过最大重试次数
        if ($queueMessage->getFailCount() >= $this->maxAttempts) {
            // 死信处理
            $this->handlerDeadLetterQueue($messageId, $consumerMessage, $e);
            return true;
        }

        $queueMessage->incrementFailCount(); // Fail count + 1

        // 计算下次重试时间
        $retrySeconds = $queueMessage->getFailCount() * $this->retrySeconds;

        // 更新下次重试时间
        $queueMessage->updateNextRetry($retrySeconds);

        // 设置消息延时
        $queueMessage->setDelay($retrySeconds);

        // 设置消息ID 避免重复任务
        $queueMessage->setIdentifier($messageId);

        // 重新发布至延时队列
        return self::createQueueProducer()->scheduleDelayedMessage($queueMessage);
    }
	
```

默认实现的代码如上，我们只需要重写此方法就可以自定义错误处理的业务逻辑。

代码示例：

```
<?php

namespace app\queue\test;

use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Interface\ConsumerMessageInterface;
use Throwable;

class SendEmail extends Consumer
{
    // 连接标识，对应redis.php的配置 默认default
    protected string $connection = 'default';

    // 将pending处理策略调整为PENDING_PROCESSING_IGNORE 消息挂起超时将不会进行重试
    protected int $pendingProcessingStrategy = self::PENDING_PROCESSING_IGNORE;

    public function consume(ConsumerMessageInterface $consumerMessage)
    {
        // TODO: Implement consume() method.

        // 获取消息ID
        $messageId = $consumerMessage->getMessageId();

        // 获取队列数据
        $data = $consumerMessage->getData();

        // 监听异常
        $consumerMessage->onError(function (\Throwable $e){

            // 记录邮件发送失败日志
        });

        // 禁用重试
        $consumerMessage->disableFailRetry();

        // 发送一封邮件 ....

        // 确认消息
        $consumerMessage->ack();
    }

    public function handlerFailRetry($messageId, ConsumerMessageInterface $consumerMessage, Throwable $e): bool
    {
        // 不改动原本的错误处理 也可以完全自定义实现。
        parent::handlerFailRetry($messageId, $consumerMessage, $e);

	   // 如果队列在业务数据库中还有一个tasks表进行调度，在这里可以更新task数据 比如 错误次数+1
    }
}

```

### 自定义死信处理

在`handlerFailRetry`方法中，默认有这一段：

```php

// 检查是否超过最大重试次数
if ($queueMessage->getFailCount() >= $this->maxAttempts) {
    // 死信处理
    $this->handlerDeadLetterQueue($messageId, $consumerMessage, $e);
    return true;
}

```

那么，我们如果需要自定义死信处理或加入额外的业务逻辑可以通过重写`handlerDeadLetterQueue`方法实现。

`protected int $pendingProcessingStrategy = self::PENDING_PROCESSING_IGNORE;`
当我们设置pending处理策略为`PENDING_PROCESSING_IGNORE`
时，消息如果挂起超时，将不会触发异常重试，而是直接调用死信处理。默认情况下，死信处理会新增一条日志，方便排查问题。

> 默认情况下需要配置有效的日志(log.php) 默认行为才有效。也可以通过重写方法完全自行实现，记录在业务的数据库中，这也是推荐的做法，可以针对业务实现更加灵活的异常处理。

```
    /**
     * 处理死信 超过最大重试次数或pending超时PENDING_PROCESSING_IGNORE策略 会调用此方法
     * @param $messageId
     * @param ConsumerMessageInterface $consumerMessage
     * @param Throwable $e
     * @return void
     */
    public function handlerDeadLetterQueue($messageId, ConsumerMessageInterface $consumerMessage, Throwable $e): void
    {
        $queueMessage = $consumerMessage->getQueueMessage();

        // 添加日志
        LogUtility::warning('dead_letter_queue: ', [
            'messageId' => $messageId,
            'message' => $queueMessage->toArray(),
            'failCount' => $queueMessage->getFailCount(),
            'errorMsg' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // 更多...
    }
```

代码示例：

```
public function handlerDeadLetterQueue($messageId, ConsumerMessageInterface $consumerMessage, Throwable $e): void
{
    // 保持默认行为
    parent::handlerDeadLetterQueue($messageId, $consumerMessage, $e); // TODO: Change the autogenerated stub

    // 如果队列在业务数据库中还有一个tasks表进行调度，在这里可以更新task数据
}
```

### 自定义pending超时处理

抽象类`Consumer`中，默认定义了`handlerPendingTimeoutMessages`方法，用于处理pending超时的消息。

消费者读取了一条消息后，消息会进入`pending`
列表，不会被当前和其他消费者再次读取，当业务逻辑没有执行完毕，服务出现掉线，崩溃时，消息并没有`ack`，消息会一直保存在`pending`
列表中，`pending`列表只能通过`ack`移除，如果长期不处理，可能造成`pending`
列表堆积，造成大量内存占用，当持续时间大于`$pendingTimout`属性的时间(默认300秒)，会调用此方法进行处理。

> 默认情况下，在`PENDING_PROCESSING_IGNORE`策略中，我们认为pending超时消息是死信，不会再次处理，`PENDING_PROCESSING_RETRY`
> 会进行异常重试。

```php

     /**
     * 处理消息挂起超时 当pending列表中有超时未ack的消息会触发此方法
     * @param string $messageId
     * @param ConsumerMessageInterface $consumerMessage
     * @param string $consumerName
     * @param int $elapsedTime
     * @param int $deliveryCount
     * @return void
     * @throws RedisException
     * @throws ScheduleDelayedMessageException
     * @throws Throwable
     */
    public function handlerPendingTimeoutMessages(string $messageId, ConsumerMessageInterface $consumerMessage, string $consumerName, int $elapsedTime, int $deliveryCount): void
    {
        switch ($this->getPendingProcessingStrategy()) {
            case self::PENDING_PROCESSING_IGNORE: // 忽略pending超时

                // 确认消息
                $consumerMessage->ack();

                // 触发死信处理
                $this->handlerDeadLetterQueue($messageId, $consumerMessage, new Exception(
                    'PENDING_PROCESSING_IGNORE: Message pending timeout.'
                ));
                break;
            case self::PENDING_PROCESSING_RETRY: // pending超时重试

                // 触发死信处理
                if ($deliveryCount + 1 > $this->getMaxAttempts()) {

                    // ack消息
                    $consumerMessage->ack();

                    $this->handlerDeadLetterQueue(
                        $messageId,
                        $consumerMessage,
                        new Exception(
                            'PENDING_PROCESSING_RETRY: The number of message delivery times exceeds the maximum number of retries.'
                        ));

                    return;
                }

                // 处理重试
                $handlerStatus = $this->handlerFailRetry(
                    $messageId,
                    $consumerMessage,
                    new Exception('PENDING_PROCESSING_RETRY: Message pending timeout retry.')
                );

                if ($handlerStatus) {
                    $consumerMessage->ack();
                }
                break;
        }
    }

```

## 获取队列的redis连接

有时候我们需要操作或维护队列时，可以直接获取队列的Redis连接进行操作，比如编写自定义脚本等。

```php

// 获取队列的Redis连接
$sendCode = new app\queue\test\SendCode();

$redisConnection = $sendCode->getRedisConnection();

// 使用方法和phpredis扩展一致

$redisConnection->xLen();

$redisConnection->sAdd();

// 在消费类中可以直接使用$this->getRedisConnection();

....更多

```

## 命令行

### `php webman solar:make:consumer`

- 创建一个消费者
- 它将引导你创建一个基本的消费者类

### `php webman solar:remove:consumer`

- 移除一个消费者
- 它将引导你移除消费者类
- 注意：它会移除redis中关于此消费者的所有数据，如果你只是想移除类和配置，请不要使用此命令。

### `php webman solar:clean:redis:data`

- 清理某个消费者的Redis数据
- 它将引导你清理redis数据
- 注意：它将删除redis中关于此消费者的所有数据，请谨慎操作。

### `php webman solar:consumer:list`

- 获取当前全部消费者信息，包含如下信息：
- `Key` 标识
- `Handler` 进程类
- `Count` 进程数
- `Consumer` 消费者类名
- `Stream Length` 当前队列总长度(不包含Pending列表中的数量)
- `Delay Set Length` 当前延时队列任务数
- `Pending List Length` Pending列表长度,已读取但未ack的消息会在此列表中
- `Active` Stream最近写入消息的时间

```
+-----------+--------------------------------------------------------+-------+--------------------------+---------------+------------------+---------------------+---------------+
| Key       | Handler                                                | Count | Consumer                 | Stream Length | Delay Set Length | Pending List Length | Last Active   |
+-----------+--------------------------------------------------------+-------+--------------------------+---------------+------------------+---------------------+---------------+
| SendCode  | SolarSeahorse\WebmanRedisQueue\Process\ConsumerProcess | 20    | app\queue\test\SendCode  | 1996          | 950              | >=500               | 1 seconds ago |
| SendEmail | SolarSeahorse\WebmanRedisQueue\Process\ConsumerProcess | 20    | app\queue\test\SendEmail | 0             | 0                | 0                   | unknown       |
+-----------+--------------------------------------------------------+-------+--------------------------+---------------+------------------+---------------------+---------------+
```

## 处理历史消息

**使用场景：**

1. 在极端情况下业务执行完毕并且ack成功，但是删除消息时出现异常，消息保留在`stream`中，一般少量数据时我们无需在意，但如果堆积数量过大可能造成内存占用和性能问题。
2. 当你需要处理历史消息，或者重新处理之前已经处理过的消息。
3. 当你需要对 Stream 中的历史数据进行分析或生成报告。

> 当`autoDel`属性为`true`
> 时，消息会自动删除，无法对历史数据进行处理和分析，如果业务需要对历史队列消息进行回溯请设置为`false`

**代码示例：**

这里我们使用了`webman`中[自定义脚本](https://www.workerman.net/doc/webman/others/scripts.html "自定义脚本")
的编写，可以将脚本加入定时任务中，定期清理或处理历史消息。

> 下方代码只是示例，请确保在测试环境充分测试。

```php

<?php

use SolarSeahorse\WebmanRedisQueue\Queue\QueueMessage;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../support/bootstrap.php';


// 获取队列的Redis连接
$sendCode = new app\queue\test\SendCode();

$redisConnection = $sendCode->getRedisConnection();

// 使用方法和phpredis扩展一致
$streamKey = $sendCode->getStreamKey();
$start = '-'; // 表示从 Stream 的最开始读取
$end = '+';   // 表示读取到 Stream 的最末尾
$count = 100;  // 指定要读取的消息数量

// 读取Stream列表，不包括pending
$messages = $redisConnection->xRange($streamKey, $start, $end, $count);

$deleteMessageIds = [];

foreach ($messages as $messageId => $message) {

    // 解析原始消息内容
    $messageArr = QueueMessage::parseRawMessage($message);

    if (!$messageArr) { // 未知消息
        $deleteMessageIds[] = $messageId;
        continue;
    }

    // 转换为QueueMessage方便操作
    $queueMessage = QueueMessage::createFromArray($messageArr);

    // 通过获取消息时间戳，如果消息已经存在超过1个小时 标记删除。
    if (time() - $queueMessage->getTimestamp() > 3600) {
        $deleteMessageIds[] = $messageId;
    }

}

// 批量删除消息
$redisConnection->xDel($streamKey, $deleteMessageIds);

```

## 在其他项目投递消息

目前插件没有实现在其他项目投递的标准实现，可通过业务需求开发队列提交接口实现。
