<?php

declare(strict_types=1);

namespace Eccube2\Migration\Tests;

use Eccube2\Migration\Migrator;
use PHPUnit\Framework\TestCase;

class MigratorTest extends TestCase
{
    private string $migrationsPath;
    private string $dbPath;

    protected function setUp(): void
    {
        $this->migrationsPath = __DIR__ . '/migrations';
        $this->dbPath = sys_get_temp_dir() . '/test_migrator_' . uniqid() . '.sqlite';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    public function testRunFromWebInstallerWithSqlite(): void
    {
        $dsn = [
            'phptype' => 'sqlite3',
            'database' => $this->dbPath,
        ];

        $result = Migrator::runFromWebInstaller($dsn, $this->migrationsPath);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['executed']);
        $this->assertStringContainsString('migration(s) executed', $result['message']);
    }

    public function testRunFromWebInstallerWithInvalidDsn(): void
    {
        $dsn = [
            'phptype' => 'mysql',
            'hostspec' => 'invalid-host-that-does-not-exist',
            'database' => 'invalid_db',
            'username' => 'invalid_user',
            'password' => 'invalid_pass',
        ];

        $result = Migrator::runFromWebInstaller($dsn, $this->migrationsPath);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['message']);
        $this->assertEmpty($result['executed']);
    }

    public function testRunFromWebInstallerWithNoMigrations(): void
    {
        $dsn = [
            'phptype' => 'sqlite3',
            'database' => $this->dbPath,
        ];

        $emptyMigrationsPath = sys_get_temp_dir() . '/empty_migrations_' . uniqid();
        mkdir($emptyMigrationsPath);

        try {
            $result = Migrator::runFromWebInstaller($dsn, $emptyMigrationsPath);

            $this->assertTrue($result['success']);
            $this->assertEmpty($result['executed']);
            $this->assertSame('0 migration(s) executed', $result['message']);
        } finally {
            rmdir($emptyMigrationsPath);
        }
    }

    public function testRunFromWebInstallerCreatesTablesCorrectly(): void
    {
        $dsn = [
            'phptype' => 'sqlite3',
            'database' => $this->dbPath,
        ];

        $result = Migrator::runFromWebInstaller($dsn, $this->migrationsPath);

        $this->assertTrue($result['success']);

        // Verify tables were created
        $pdo = new \PDO('sqlite:' . $this->dbPath);
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertContains('dtb_migration', $tables);
        $this->assertContains('dtb_login_attempt', $tables);
    }

    public function testRunFromWebInstallerIsIdempotent(): void
    {
        $dsn = [
            'phptype' => 'sqlite3',
            'database' => $this->dbPath,
        ];

        // Run twice
        $result1 = Migrator::runFromWebInstaller($dsn, $this->migrationsPath);
        $result2 = Migrator::runFromWebInstaller($dsn, $this->migrationsPath);

        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);

        // Second run should execute no migrations
        $this->assertEmpty($result2['executed']);
        $this->assertSame('0 migration(s) executed', $result2['message']);
    }
}
