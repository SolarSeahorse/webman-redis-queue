<?php

namespace SolarSeahorse\WebmanRedisQueue\Commands;

use SolarSeahorse\WebmanRedisQueue\Process\ConsumerProcess;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class MakeConsumerCommand extends Command
{
    protected static string $defaultName = 'solar:make:consumer';
    protected static string $defaultDescription = 'Creates a new queue consumer class.';

    protected function configure()
    {
        $this->setDescription('Creates a new queue consumer class with configuration.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $helper = $this->getHelper('question');

            $queueNameQuestion = new Question('Please enter the name of the queue: ');
            $queueName = $helper->ask($input, $output, $queueNameQuestion);

            CommandUtils::validateQueueName($queueName);

            $queueName = CommandUtils::parseQueueName($queueName);

            $processCountQuestion = new Question('Please enter the number of processes (default 1): ', '1');
            $processCount = $helper->ask($input, $output, $processCountQuestion);

            $defaultPath = 'app/queue';
            $pathQuestion = new Question("Please enter the path to create the class in [$defaultPath]: ", $defaultPath);
            $path = $helper->ask($input, $output, $pathQuestion);

            $namespace = CommandUtils::pathToNamespace($path);

            $fullClassName = "{$namespace}\\{$queueName}";

            if (CommandUtils::validateQueueNameExists($queueName)) {
                throw new \InvalidArgumentException("Error: Queue name '$queueName' already exists.");
            }

            CommandUtils::setProcessConfig($queueName, [
                'handler' => ConsumerProcess::class,
                'count' => (int) $processCount,
                'constructor' => [
                    'consumer_source' => $fullClassName
                ]
            ], true);

            $fullPath = CommandUtils::getFullPath($path, $queueName);

            $this->createConsumer($namespace, $queueName, $fullPath);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

    private function createConsumer($namespace, $class, $file): void
    {
        $path = pathinfo($file, PATHINFO_DIRNAME);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $controller_content = <<<EOF
<?php

namespace $namespace;

use SolarSeahorse\WebmanRedisQueue\Consumer;
use SolarSeahorse\WebmanRedisQueue\Interface\ConsumerMessageInterface;

class $class extends Consumer
{
    // 连接标识，对应config/plugin/solarseahorse/webman-redis-queue/redis.php的配置
    protected string \$connection = 'default';

    // 消费
    public function consume(ConsumerMessageInterface \$consumerMessage)
    {
        // TODO: Implement consume() method.

        // 获取消息ID
        \$messageId = \$consumerMessage->getMessageId();

        // 获取队列数据
        \$data = \$consumerMessage->getData();

        var_dump(\$messageId);
    }
}

EOF;
        file_put_contents($file, $controller_content);
    }
}