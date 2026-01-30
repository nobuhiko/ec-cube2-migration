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
    use DatabaseConnectionTrait;

    protected static $defaultName = 'migrate:status';
    protected static $defaultDescription = 'Show migration status';

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // Preload migration classes before EC-CUBE autoloader takes over
        class_exists(\Eccube2\Migration\Migrator::class);
        \Eccube2\Init::init();
    }

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
            [$connection, $dbType] = $this->getConnection();
            $migrator = new Migrator($connection, $dbType, $input->getOption('path'));
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
}
