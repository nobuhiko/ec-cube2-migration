<?php

declare(strict_types=1);

namespace Eccube2\Migration\Command;

use Eccube2\Migration\Migrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateCommand extends Command
{
    protected static $defaultName = 'migrate';
    protected static $defaultDescription = 'Run pending database migrations';

    protected function configure(): void
    {
        $this
            ->setName('migrate')
            ->setDescription('Run pending database migrations')
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
            $executed = $migrator->migrate();

            if (empty($executed)) {
                $io->success('No pending migrations.');
                return Command::SUCCESS;
            }

            $io->success(sprintf('Executed %d migration(s):', count($executed)));
            foreach ($executed as $version) {
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
        // Try to load EC-CUBE configuration
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
