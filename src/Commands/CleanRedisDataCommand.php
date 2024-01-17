<?php

namespace SolarSeahorse\WebmanRedisQueue\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class CleanRedisDataCommand extends Command
{
    protected static string $defaultName = 'solar:clean:redis:data';
    protected static string $defaultDescription = 'Clean Queue redis data.';

    protected function configure()
    {
        $this->setDescription('Clean Queue redis data with configuration.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $helper = $this->getHelper('question');

            $queueNameQuestion = new Question('Please enter the name of the queue to remove: ');
            $queueName = $helper->ask($input, $output, $queueNameQuestion);

            CommandUtils::validateQueueName($queueName);

            $queueName = CommandUtils::parseQueueName($queueName);

            if (!CommandUtils::validateQueueNameExists($queueName)) {
                throw new \InvalidArgumentException("Error: Queue name '$queueName' does not exist.");
            }

            $confirmRedisDeletion = new ConfirmationQuestion("Do you want to delete all Redis data associated with '$queueName'? [y/N] ", false);
            if (!$helper->ask($input, $output, $confirmRedisDeletion)) {
                $output->writeln('Operation aborted.');
                return Command::SUCCESS;
            }

            $consumer_source = CommandUtils::getProcessConsumerSource($queueName);
            $result = CommandUtils::cleanQueueRedisData($consumer_source);

            $output->writeln("<info>" . var_export($result, true) . "</info>");

            return Command::SUCCESS;
        } catch (\Throwable $e) {

            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

}