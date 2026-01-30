<?php

declare(strict_types=1);

namespace Eccube2\Migration\Command;

use Eccube2\Migration\Migrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RollbackCommand extends Command
{
    protected static $defaultName = 'migrate:rollback';
    protected static $defaultDescription = 'Rollback the last migration(s)';

    protected function configure(): void
    {
        $this
            ->setName('migrate:rollback')
            ->setDescription('Rollback the last migration(s)')
            ->addOption(
                'steps',
                's',
                InputOption::VALUE_REQUIRED,
                'Number of migrations to rollback',
                '1'
            )
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED,
                'Path to migrations directory',
                'data/migrations'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $migrator = $this->createMigrator($input->getOption('path'));
            $steps = (int) $input->getOption('steps');

            $rolledBack = $migrator->rollback($steps);

            if (empty($rolledBack)) {
                $io->warning('No migrations to rollback.');
                return Command::SUCCESS;
            }

            $io->success(sprintf('Rolled back %d migration(s):', count($rolledBack)));
            foreach ($rolledBack as $version) {
                $io->writeln(sprintf('  - Version%s', $version));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function createMigrator(string $path): Migrator
    {
        if (defined('DB_TYPE') && class_exists('SC_Query')) {
            $connection = \SC_Query::getSingletonInstance();
            $dbType = DB_TYPE;
        } else {
            throw new \RuntimeException(
                'EC-CUBE environment not detected. ' .
                'Please run this command from EC-CUBE root directory or configure database connection.'
            );
        }

        return new Migrator($connection, $dbType, $path);
    }
}
