<?php

declare(strict_types=1);

namespace Eccube2\Migration;

use Eccube2\Migration\Platform\MySQLPlatform;
use Eccube2\Migration\Platform\PlatformInterface;
use Eccube2\Migration\Platform\PostgreSQLPlatform;
use Eccube2\Migration\Platform\SQLitePlatform;

class Migrator
{
    private $connection;
    private PlatformInterface $platform;
    private string $migrationsPath;
    private string $migrationsTable = 'dtb_migration';

    public function __construct($connection, string $dbType, string $migrationsPath)
    {
        $this->connection = $connection;
        $this->platform = $this->createPlatform($dbType);
        $this->platform->setConnection($connection);
        $this->migrationsPath = $migrationsPath;
    }

    /**
     * Run migrations from web installer using DSN array
     *
     * @param array $dsn DSN array with keys: phptype, hostspec, database, username, password, port
     * @param string $migrationsPath Path to migrations directory
     * @return array{success: bool, message: string, executed: string[]}
     */
    public static function runFromWebInstaller(array $dsn, string $migrationsPath): array
    {
        $result = ['success' => false, 'message' => '', 'executed' => []];

        try {
            // Prefer SC_Query if available (EC-CUBE context with MDB2)
            if (class_exists('\SC_Query')) {
                $connection = new \SC_Query($dsn, false, true);
            } else {
                // Fallback to PDO for standalone/test context
                $connection = self::createPdoFromDsn($dsn);
            }

            $migrator = new self($connection, $dsn['phptype'], $migrationsPath);
            $result['executed'] = $migrator->migrate();
            $result['success'] = true;
            $result['message'] = count($result['executed']) . ' migration(s) executed';
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Create PDO connection from EC-CUBE DSN array
     *
     * @param array $dsn DSN array with keys: phptype, hostspec, database, username, password, port
     * @return \PDO
     */
    private static function createPdoFromDsn(array $dsn): \PDO
    {
        $dbType = $dsn['phptype'];
        $host = $dsn['hostspec'] ?? '127.0.0.1';
        $database = $dsn['database'];
        $username = $dsn['username'] ?? null;
        $password = $dsn['password'] ?? null;
        $port = $dsn['port'] ?? null;

        switch ($dbType) {
            case 'mysqli':
            case 'mysql':
                $portPart = $port ? ";port={$port}" : '';
                $pdoDsn = "mysql:host={$host};dbname={$database}{$portPart};charset=utf8";
                break;
            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                $portPart = $port ? ";port={$port}" : '';
                $pdoDsn = "pgsql:host={$host};dbname={$database}{$portPart}";
                break;
            case 'sqlite3':
            case 'sqlite':
                $pdoDsn = "sqlite:{$database}";
                break;
            default:
                throw new \InvalidArgumentException("Unsupported database type: {$dbType}");
        }

        $pdo = new \PDO($pdoDsn, $username, $password);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private function createPlatform(string $dbType): PlatformInterface
    {
        switch ($dbType) {
            case 'mysqli':
            case 'mysql':
                return new MySQLPlatform();
            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                return new PostgreSQLPlatform();
            case 'sqlite3':
            case 'sqlite':
                return new SQLitePlatform();
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported database type: %s', $dbType));
        }
    }

    public function getPlatform(): PlatformInterface
    {
        return $this->platform;
    }

    /**
     * Run all pending migrations
     * @return string[] List of executed migration versions
     */
    public function migrate(): array
    {
        $this->ensureMigrationTable();

        $executed = $this->getExecutedMigrations();
        $available = $this->getAvailableMigrations();

        // Cast keys to strings for comparison (PHP converts numeric string keys to integers)
        $availableVersions = array_map('strval', array_keys($available));
        $pending = array_diff($availableVersions, $executed);
        sort($pending);

        $executedVersions = [];
        foreach ($pending as $version) {
            // Use original key (may be integer) for array access
            $key = array_search($version, $availableVersions);
            $originalKey = array_keys($available)[$key];
            $this->runMigration($available[$originalKey], 'up');
            $this->markAsExecuted($version);
            $executedVersions[] = $version;
        }

        return $executedVersions;
    }

    /**
     * Rollback last migration(s)
     * @return string[] List of rolled back migration versions
     */
    public function rollback(int $steps = 1): array
    {
        $this->ensureMigrationTable();

        $executed = $this->getExecutedMigrations();
        $available = $this->getAvailableMigrations();

        // Get last N executed migrations
        $toRollback = array_slice(array_reverse($executed), 0, $steps);

        $rolledBack = [];
        foreach ($toRollback as $version) {
            if (isset($available[$version])) {
                $this->runMigration($available[$version], 'down');
                $this->markAsNotExecuted($version);
                $rolledBack[] = $version;
            }
        }

        return $rolledBack;
    }

    /**
     * Get migration status
     * @return array{version: string, executed: bool, name: string}[]
     */
    public function getStatus(): array
    {
        $this->ensureMigrationTable();

        $executed = $this->getExecutedMigrations();
        $available = $this->getAvailableMigrations();

        $status = [];
        foreach ($available as $version => $class) {
            // Cast version to string for comparison (PHP converts numeric string keys to integers)
            $versionStr = (string) $version;
            $status[] = [
                'version' => $versionStr,
                'executed' => in_array($versionStr, $executed, true),
                'name' => $class,
            ];
        }

        return $status;
    }

    /**
     * Get list of executed migration versions
     * @return string[]
     */
    public function getExecutedMigrations(): array
    {
        $sql = sprintf(
            'SELECT version FROM %s ORDER BY version ASC',
            $this->migrationsTable
        );

        return $this->fetchColumn($sql);
    }

    /**
     * Get available migration classes
     * @return array<string, string> version => class name
     */
    public function getAvailableMigrations(): array
    {
        $migrations = [];

        if (!is_dir($this->migrationsPath)) {
            return $migrations;
        }

        $files = glob($this->migrationsPath . '/Version*.php');
        foreach ($files as $file) {
            require_once $file;

            $className = pathinfo($file, PATHINFO_FILENAME);

            // Extract version from class name (e.g., Version20260130001 -> 20260130001)
            // Keep version as string for consistent comparison with executed migrations
            if (preg_match('/^Version(\d+)/', $className, $matches)) {
                $version = (string) $matches[1];

                // Try to find the full class name with namespace
                $fullClassName = $this->findMigrationClass($className);
                if ($fullClassName !== null) {
                    $migrations[$version] = $fullClassName;
                }
            }
        }

        ksort($migrations);
        return $migrations;
    }

    private function findMigrationClass(string $shortName): ?string
    {
        // Check with namespace
        $candidates = [
            'Eccube2\\Migration\\Migrations\\' . $shortName,
            $shortName,
        ];

        foreach ($candidates as $className) {
            if (class_exists($className)) {
                return $className;
            }
        }

        // Search in declared classes
        foreach (get_declared_classes() as $class) {
            if (str_ends_with($class, '\\' . $shortName) || $class === $shortName) {
                return $class;
            }
        }

        return null;
    }

    private function runMigration(string $className, string $direction): void
    {
        $migration = new $className();

        if (!$migration instanceof Migration) {
            throw new \RuntimeException(sprintf(
                'Migration class %s must extend %s',
                $className,
                Migration::class
            ));
        }

        $migration->setConnection($this->connection);
        $migration->setPlatform($this->platform);

        if ($direction === 'up') {
            $migration->up();
        } else {
            $migration->down();
        }
    }

    private function ensureMigrationTable(): void
    {
        $sql = $this->getCreateMigrationTableSql();
        $this->execute($sql);
    }

    private function getCreateMigrationTableSql(): string
    {
        $dbType = $this->platform->getName();

        switch ($dbType) {
            case 'mysqli':
                return sprintf(
                    'CREATE TABLE IF NOT EXISTS %s (
                        migration_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        version VARCHAR(255) NOT NULL UNIQUE,
                        executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
                    $this->migrationsTable
                );

            case 'pgsql':
                return sprintf(
                    'CREATE TABLE IF NOT EXISTS %s (
                        migration_id SERIAL PRIMARY KEY,
                        version VARCHAR(255) NOT NULL UNIQUE,
                        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )',
                    $this->migrationsTable
                );

            case 'sqlite3':
                return sprintf(
                    "CREATE TABLE IF NOT EXISTS %s (
                        migration_id INTEGER PRIMARY KEY,
                        version TEXT NOT NULL UNIQUE,
                        executed_at TEXT DEFAULT (datetime('now','localtime'))
                    )",
                    $this->migrationsTable
                );

            default:
                throw new \RuntimeException('Unsupported database type');
        }
    }

    private function markAsExecuted(string $version): void
    {
        $sql = sprintf(
            "INSERT INTO %s (version) VALUES ('%s')",
            $this->migrationsTable,
            addslashes($version)
        );
        $this->execute($sql);
    }

    private function markAsNotExecuted(string $version): void
    {
        $sql = sprintf(
            "DELETE FROM %s WHERE version = '%s'",
            $this->migrationsTable,
            addslashes($version)
        );
        $this->execute($sql);
    }

    private function execute(string $sql): void
    {
        if ($this->connection instanceof \SC_Query) {
            $this->connection->query($sql);
        } elseif ($this->connection instanceof \PDO) {
            $this->connection->exec($sql);
        } elseif (is_callable([$this->connection, 'exec'])) {
            $this->connection->exec($sql);
        } else {
            throw new \RuntimeException('Unsupported connection type');
        }
    }

    /**
     * @return string[]
     */
    private function fetchColumn(string $sql): array
    {
        if ($this->connection instanceof \SC_Query) {
            $result = $this->connection->getAll($sql);
            return array_column($result, 'version');
        }

        if ($this->connection instanceof \PDO) {
            $stmt = $this->connection->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        }

        throw new \RuntimeException('Unsupported connection type');
    }
}
