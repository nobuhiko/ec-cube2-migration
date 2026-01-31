<?php

declare(strict_types=1);

namespace Eccube2\Migration\Platform;

use Eccube2\Migration\Schema\Table;

interface PlatformInterface
{
    /**
     * Get platform name (mysqli, pgsql, sqlite3)
     */
    public function getName(): string;

    /**
     * Generate CREATE TABLE SQL
     */
    public function createTable(Table $table): string;

    /**
     * Generate DROP TABLE SQL
     */
    public function dropTable(string $tableName): string;

    /**
     * Generate CREATE INDEX SQL statements
     * @return string[]
     */
    public function createIndexes(Table $table): array;

    /**
     * Generate ALTER TABLE SQL statements
     * @return string[]
     */
    public function alterTable(Table $table): array;

    /**
     * Generate CREATE SEQUENCE SQL (for PostgreSQL)
     */
    public function createSequence(Table $table): ?string;

    /**
     * Generate DROP SEQUENCE SQL (for PostgreSQL)
     */
    public function dropSequence(string $tableName): ?string;

    /**
     * Generate ALTER TABLE SET DEFAULT nextval() SQL (for PostgreSQL)
     */
    public function getSerialDefaultSql(Table $table): ?string;

    /**
     * Get SQL type for abstract column type
     */
    public function getColumnType(string $abstractType, array $options = []): string;

    /**
     * Get default value SQL
     */
    public function getDefaultValue($value): string;

    /**
     * Set database connection for runtime queries
     * @param \SC_Query|\PDO|mixed $connection
     */
    public function setConnection($connection): void;
}
