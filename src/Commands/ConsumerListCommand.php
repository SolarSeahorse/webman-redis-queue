<?php

namespace SolarSeahorse\WebmanRedisQueue\Commands;

use DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsumerListCommand extends Command
{
    protected static $defaultName = 'solar:consumer:list';
    protected static $defaultDescription = 'Get all consumers.';

    protected function configure()
    {
        $this->setDescription('Get all consumers with configuration.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = CommandUtils::getProcessConfig();

            $table = new Table($output);

            $table->setHeaders(['Key', 'Handler', 'Count', 'Consumer', 'Stream Length', 'Delay Set Length', 'Pending List Length', 'Active']);

            foreach ($config as $key => $value) {

                $consumerInstance = CommandUtils::getConsumerInstanceByQueueName($key);

                $redisConnection = $consumerInstance->getRedisConnection();

                $streamLength = $redisConnection->xLen($consumerInstance->getStreamKey());
                $delaySetLength = $redisConnection->zCard($consumerInstance->getDelayedTaskSetKey());

                $streamInfo = $redisConnection->xInfo('STREAM', $consumerInstance->getStreamKey());

                $lastGeneratedId = $streamInfo['last-generated-id'];

                $timestampMs = explode('-', $lastGeneratedId)[0];

                $pendingMessages = $consumerInstance->getRedisConnection()->xPending(
                    $consumerInstance->getStreamKey(),
                    $consumerInstance->getGroupName(),
                    '-',
                    '+',
                    500
                );

                $pendingListLength = !$pendingMessages ? 0 : '>=' . count($pendingMessages);

                $table->addRow([
                    $key,
                    $value['handler'] ?? 'N/A',
                    $value['count'] ?? 'N/A',
                    $value['constructor']['consumer_source'] ?? 'N/A',
                    $streamLength,
                    $delaySetLength,
                    $pendingListLength,
                    $timestampMs ? $this->humanizeTimestamp($timestampMs) : 'unknown'
                ]);
            }

            $table->render();

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            var_export($e->getMessage());

            return Command::FAILURE;
        }
    }

    private function humanizeTimestamp($timestampMs): string
    {
        $secondsAgo = (microtime(true) * 1000 - $timestampMs) / 1000;

        if ($secondsAgo < 1) {
            return round($secondsAgo * 1000) . ' milliseconds ago';
        } elseif ($secondsAgo < 60) {
            return round($secondsAgo) . ' seconds ago';
        } elseif ($secondsAgo < 3600) {
            return round($secondsAgo / 60) . ' minutes ago';
        } elseif ($secondsAgo < 86400) {
            return round($secondsAgo / 3600) . ' hours ago';
        } else {
            return round($secondsAgo / 86400) . ' days ago';
        }
    }
}