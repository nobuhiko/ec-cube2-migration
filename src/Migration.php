<?php

declare(strict_types=1);

namespace Eccube2\Migration;

use Eccube2\Migration\Platform\PlatformInterface;
use Eccube2\Migration\Schema\Table;

abstract class Migration
{
    protected PlatformInterface $platform;
    protected $connection;

    public function setConnection($connection): void
    {
        $this->connection = $connection;
    }

    public function setPlatform(PlatformInterface $platform): void
    {
        $this->platform = $platform;
    }

    abstract public function up(): void;

    abstract public function down(): void;

    /**
     * Create a new table
     */
    protected function create(string $tableName, callable $callback): void
    {
        $table = new Table($tableName);
        $callback($table);

        $sql = $this->platform->createTable($table);
        $this->execute($sql);

        foreach ($this->platform->createIndexes($table) as $indexSql) {
            $this->execute($indexSql);
        }

        $sequenceSql = $this->platform->createSequence($table);
        if ($sequenceSql !== null) {
            $this->execute($sequenceSql);
        }
    }

    /**
     * Drop a table
     */
    protected function drop(string $tableName): void
    {
        $sql = $this->platform->dropTable($tableName);
        $this->execute($sql);

        $sequenceSql = $this->platform->dropSequence($tableName);
        if ($sequenceSql !== null) {
            $this->execute($sequenceSql);
        }
    }

    /**
     * Modify an existing table
     */
    protected function table(string $tableName, callable $callback): void
    {
        $table = new Table($tableName, true);
        $callback($table);

        foreach ($this->platform->alterTable($table) as $sql) {
            $this->execute($sql);
        }
    }

    /**
     * Execute raw SQL with optional DB-specific variants
     */
    protected function sql(string $sql, ?string $pgsql = null, ?string $sqlite = null): void
    {
        $dbType = $this->platform->getName();

        if ($dbType === 'pgsql' && $pgsql !== null) {
            $this->execute($pgsql);
        } elseif ($dbType === 'sqlite3' && $sqlite !== null) {
            $this->execute($sqlite);
        } else {
            $this->execute($sql);
        }
    }

    /**
     * Execute SQL statement
     */
    protected function execute(string $sql): void
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
     * Get migration version from class name
     */
    public function getVersion(): string
    {
        $className = (new \ReflectionClass($this))->getShortName();
        if (preg_match('/^Version(\d+)/', $className, $matches)) {
            return $matches[1];
        }
        return $className;
    }
}
