<?php

declare(strict_types=1);

namespace Eccube2\Migration\Command;

use Eccube2\Migration\Migrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StatusCommand extends Command
{
    protected static $defaultName = 'migrate:status';
    protected static $defaultDescription = 'Show migration status';

    protected function configure(): void
    {
        $this
            ->setName('migrate:status')
            ->setDescription('Show migration status')
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
            $status = $migrator->getStatus();

            if (empty($status)) {
                $io->warning('No migrations found.');
                return Command::SUCCESS;
            }

            $rows = [];
            $pending = 0;
            foreach ($status as $item) {
                $statusText = $item['executed'] ? '<info>Executed</info>' : '<comment>Pending</comment>';
                $rows[] = [
                    $item['version'],
                    $statusText,
                    $this->extractClassName($item['name']),
                ];
                if (!$item['executed']) {
                    $pending++;
                }
            }

            $io->table(['Version', 'Status', 'Migration'], $rows);

            $io->writeln(sprintf(
                'Total: %d migrations (%d executed, %d pending)',
                count($status),
                count($status) - $pending,
                $pending
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function extractClassName(string $fullName): string
    {
        $parts = explode('\\', $fullName);
        return end($parts);
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
