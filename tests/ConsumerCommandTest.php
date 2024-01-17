<?php

namespace SolarSeahorse\Tests;

use SolarSeahorse\WebmanRedisQueue\Commands\CleanRedisDataCommand;
use SolarSeahorse\WebmanRedisQueue\Commands\CommandUtils;
use SolarSeahorse\WebmanRedisQueue\Commands\MakeConsumerCommand;
use SolarSeahorse\WebmanRedisQueue\Commands\RemoveConsumerCommand;
use SolarSeahorse\WebmanRedisQueue\Process\ConsumerProcess;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use PHPUnit\Framework\TestCase;

class ConsumerCommandTest extends TestCase
{
    public function testExecute()
    {
        // test make:consumer
        [$queueName, $classFilePath] = $this->makeConsumerCommand();

        // test clean:redis:data
        $this->cleanRedisDataCommand($queueName);

        // test remove:consumer
        $this->removeConsumerCommand($queueName, $classFilePath);
    }

    public function makeConsumerCommand(): array
    {
        $application = new Application();
        $application->add(new MakeConsumerCommand('solar:make:consumer'));

        $command = $application->find('solar:make:consumer');
        $commandTester = new CommandTester($command);

        $queueName = 'TestQueue';
        $path = 'app/queue/tests';
        $processCount = 1;

        $commandTester->setInputs([$queueName, $processCount, $path]);
        $commandTester->execute([]);

        $classFilePath = CommandUtils::getFullPath($path, $queueName);

        $this->assertTrue(file_exists($classFilePath));

        $namespace = CommandUtils::pathToNamespace($path);

        $fullClassName = "{$namespace}\\{$queueName}";

        $this->assertTrue(ConsumerProcess::isConsumerClassValid($fullClassName));

        $config = CommandUtils::getProcessConfig($queueName);

        $this->assertIsArray($config);

        $this->assertArrayHasKey('handler', $config);

        $this->assertArrayHasKey('count', $config);

        $this->assertArrayHasKey('constructor', $config);

        $this->assertIsArray($config['constructor']);

        $this->assertArrayHasKey('consumer_source', $config['constructor']);

        $this->assertEquals($fullClassName, $config['constructor']['consumer_source']);

        return [$queueName, $classFilePath];
    }

    public function cleanRedisDataCommand($queueName): void
    {
        $application = new Application();

        $application->add(new CleanRedisDataCommand('solar:clean:redis:data'));

        $command = $application->find('solar:clean:redis:data');

        $consumerInstance = CommandUtils::getConsumerInstanceByQueueName($queueName);

        $this->pushTestQueueData($consumerInstance);

        $this->assertGreaterThan(0, $this->getStreamKeyLen($consumerInstance));

        $commandTester = new CommandTester($command);

        $commandTester->setInputs([$queueName, 'y']);

        $commandTester->execute([]);

        $this->assertEquals(0, $this->getStreamKeyLen($consumerInstance));

        $consumerInstance::createQueueProducer()->pushMessage(['dummy' => '456']);

        $this->assertGreaterThan(0, $this->getStreamKeyLen($consumerInstance));
    }

    public function removeConsumerCommand($queueName, $classFilePath): void
    {
        $application = new Application();

        $application->add(new RemoveConsumerCommand('solar:remove:consumer'));
        $command = $application->find('solar:remove:consumer');
        $commandTester = new CommandTester($command);

        $consumerInstance = CommandUtils::getConsumerInstanceByQueueName($queueName);

        $this->pushTestQueueData($consumerInstance);

        $this->assertGreaterThan(0, $this->getStreamKeyLen($consumerInstance));

        $commandTester->setInputs([$queueName, 'y']);
        $commandTester->execute([]);

        $this->assertFalse(file_exists($classFilePath));

        $this->assertNull(CommandUtils::getProcessConfig($queueName));

        $this->assertEquals(0, $this->getStreamKeyLen($consumerInstance));
    }

    private function pushTestQueueData($consumerInstance)
    {
        $consumerInstance::createQueueProducer()->pushMessage(['dummy' => '123']);
    }

    private function getStreamKeyLen($consumerInstance): bool|int|\Redis
    {
        return $consumerInstance->getRedisConnection()->xLen($consumerInstance->getStreamKey());
    }
}