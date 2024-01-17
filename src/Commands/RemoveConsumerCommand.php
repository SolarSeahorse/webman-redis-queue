<?php

namespace SolarSeahorse\WebmanRedisQueue\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class RemoveConsumerCommand extends Command
{
    protected static string $defaultName = 'solar:remove:consumer';
    protected static string $defaultDescription = 'Removes a specified queue and optionally its Redis data.';

    protected function configure()
    {
        $this->setDescription('Removes a specified queue and optionally its Redis data.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $helper = $this->getHelper('question');

            $queueNameQuestion = new Question('Please enter the name of the queue to remove: ');
            $queueName = $helper->ask($input, $output, $queueNameQuestion);

            CommandUtils::validateQueueName($queueName);

            $confirmation = new ConfirmationQuestion("Are you sure you want to remove the queue '$queueName'? [y/N] ", false);
            if (!$helper->ask($input, $output, $confirmation)) {
                $output->writeln('Operation aborted.');
                return Command::SUCCESS;
            }

            $queueName = CommandUtils::parseQueueName($queueName);

            if (!CommandUtils::validateQueueNameExists($queueName)) {
                throw new \InvalidArgumentException("Error: Queue name '$queueName' does not exist.");
            }

            $consumer_source = CommandUtils::getProcessConsumerSource($queueName);

            $result = CommandUtils::cleanQueueRedisData($consumer_source);

            $output->writeln("<info>" . var_export($result, true) . "</info>");

            $classFilePath = CommandUtils::classNameToPath($consumer_source);

            unlink($classFilePath);

            CommandUtils::removeProcessConfig($queueName, true);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}