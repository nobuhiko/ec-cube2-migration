<?php

declare(strict_types=1);

namespace Eccube2\Migration\Platform;

use Eccube2\Migration\Schema\Table;

class SQLitePlatform extends AbstractPlatform
{
    public function getName(): string
    {
        return 'sqlite3';
    }

    protected function getTypeMap(): array
    {
        return [
            'serial'    => 'INTEGER',
            'integer'   => 'INTEGER',
            'smallint'  => 'INTEGER',
            'bigint'    => 'INTEGER',
            'text'      => 'TEXT',
            'string'    => 'TEXT',       // SQLite doesn't enforce VARCHAR length
            'char'      => 'TEXT',
            'decimal'   => 'REAL',
            'float'     => 'REAL',
            'date'      => 'TEXT',       // SQLite stores dates as TEXT
            'time'      => 'TEXT',
            'timestamp' => 'TEXT',
            'boolean'   => 'INTEGER',
            'blob'      => 'BLOB',
        ];
    }

    protected function getAutoIncrementSyntax(): string
    {
        // SQLite: INTEGER PRIMARY KEY is auto-increment by default
        return 'PRIMARY KEY';
    }

    protected function isPrimaryKeyInline(): bool
    {
        return true;
    }

    protected function translateDefaultExpression(string $expression): string
    {
        if ($expression === 'CURRENT_TIMESTAMP' || $expression === 'CURRENT_TIMESTAMP()') {
            return "(datetime('now','localtime'))";
        }
        return $expression;
    }

    /**
     * SQLite has limited ALTER TABLE support.
     * DROP COLUMN and RENAME COLUMN were added in SQLite 3.35.0 (2021-03-12)
     * For older versions, we'd need to recreate the table.
     */
    protected function buildDropColumn(string $tableName, string $columnName): array
    {
        // Modern SQLite (3.35.0+) supports DROP COLUMN
        return [sprintf('ALTER TABLE %s DROP COLUMN %s', $tableName, $columnName)];
    }

    protected function buildRenameColumn(string $tableName, string $oldName, string $newName): string
    {
        // Modern SQLite (3.25.0+) supports RENAME COLUMN
        return sprintf('ALTER TABLE %s RENAME COLUMN %s TO %s', $tableName, $oldName, $newName);
    }

    public function getColumnType(string $abstractType, array $options = []): string
    {
        // SQLite ignores length constraints on TEXT, but we'll accept them
        $typeMap = $this->getTypeMap();

        if (!isset($typeMap[$abstractType])) {
            throw new \InvalidArgumentException(sprintf('Unknown column type: %s', $abstractType));
        }

        return $typeMap[$abstractType];
    }

    public function createSequence(Table $table): ?string
    {
        foreach ($table->getColumns() as $column) {
            if ($column->getType() === 'serial' && $column->isPrimary()) {
                $sequenceName = $this->getSequenceName($table->getName(), $column->getName());
                return sprintf(
                    'CREATE TABLE IF NOT EXISTS %s (
    sequence INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL
)',
                    $sequenceName
                );
            }
        }
        return null;
    }

    public function dropSequence(string $tableName): ?string
    {
        $columnName = $this->guessSerialColumnName($tableName);
        if ($columnName !== null) {
            $sequenceName = $this->getSequenceName($tableName, $columnName);
            return sprintf('DROP TABLE IF EXISTS %s', $sequenceName);
        }
        return null;
    }

    private function getSequenceName(string $tableName, string $columnName): string
    {
        return sprintf('%s_%s_seq', $tableName, $columnName);
    }

    private function guessSerialColumnName(string $tableName): ?string
    {
        // EC-CUBE convention: dtb_xxx -> xxx_id
        if (strpos($tableName, 'dtb_') === 0) {
            $baseName = substr($tableName, 4);
            return $baseName . '_id';
        }
        if (strpos($tableName, 'mtb_') === 0) {
            $baseName = substr($tableName, 4);
            return $baseName . '_id';
        }
        return null;
    }
}
