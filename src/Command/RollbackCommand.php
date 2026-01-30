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
    use DatabaseConnectionTrait;

    protected static $defaultName = 'migrate:rollback';
    protected static $defaultDescription = 'Rollback the last migration(s)';

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // Preload migration classes before EC-CUBE autoloader takes over
        class_exists(\Eccube2\Migration\Migrator::class);
        class_exists(\Eccube2\Migration\Migration::class);
        class_exists(\Eccube2\Migration\Platform\MySQLPlatform::class);
        class_exists(\Eccube2\Migration\Platform\PostgreSQLPlatform::class);
        class_exists(\Eccube2\Migration\Platform\SQLitePlatform::class);
        class_exists(\Eccube2\Migration\Schema\Table::class);
        class_exists(\Eccube2\Migration\Schema\Column::class);
        \Eccube2\Init::init();
    }

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
            [$connection, $dbType] = $this->getConnection();
            $migrator = new Migrator($connection, $dbType, $input->getOption('path'));
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
}
