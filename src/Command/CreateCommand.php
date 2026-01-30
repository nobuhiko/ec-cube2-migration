<?php

declare(strict_types=1);

namespace Eccube2\Migration\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateCommand extends Command
{
    protected static $defaultName = 'migrate:create';
    protected static $defaultDescription = 'Create a new migration file';

    protected function configure(): void
    {
        $this
            ->setName('migrate:create')
            ->setDescription('Create a new migration file')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Migration name (e.g., CreateLoginAttemptTable)'
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

        $name = $input->getArgument('name');
        $path = $input->getOption('path');

        // Generate version timestamp
        $version = date('YmdHis');
        $className = 'Version' . $version . '_' . $name;
        $fileName = $className . '.php';
        $filePath = rtrim($path, '/') . '/' . $fileName;

        // Ensure directory exists
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        // Generate migration content
        $content = $this->generateMigrationContent($className);

        if (file_exists($filePath)) {
            $io->error(sprintf('Migration file already exists: %s', $filePath));
            return Command::FAILURE;
        }

        file_put_contents($filePath, $content);

        $io->success(sprintf('Created migration: %s', $filePath));

        return Command::SUCCESS;
    }

    private function generateMigrationContent(string $className): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

use Eccube2\Migration\Migration;
use Eccube2\Migration\Schema\Table;

class {$className} extends Migration
{
    public function up(): void
    {
        // Example: Create a new table
        // \$this->create('dtb_example', function (Table \$table) {
        //     \$table->serial('example_id')->primary();
        //     \$table->text('name')->notNull();
        //     \$table->timestamp('create_date')->default('CURRENT_TIMESTAMP');
        //     \$table->timestamp('update_date')->nullable();
        //
        //     \$table->index(['name']);
        // });

        // Example: Modify existing table
        // \$this->table('dtb_customer', function (Table \$table) {
        //     \$table->addColumn('new_column', 'text')->nullable();
        //     \$table->addIndex(['new_column']);
        // });
    }

    public function down(): void
    {
        // Example: Drop table
        // \$this->drop('dtb_example');

        // Example: Remove column
        // \$this->table('dtb_customer', function (Table \$table) {
        //     \$table->dropIndex('idx_dtb_customer_new_column');
        //     \$table->dropColumn('new_column');
        // });
    }
}

PHP;
    }
}
