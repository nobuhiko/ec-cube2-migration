<?php

declare(strict_types=1);

namespace Eccube2\Migration\Platform;

use Eccube2\Migration\Schema\Table;

class PostgreSQLPlatform extends AbstractPlatform
{
    public function getName(): string
    {
        return 'pgsql';
    }

    protected function getTypeMap(): array
    {
        return [
            'serial'    => 'INT',
            'integer'   => 'INTEGER',
            'smallint'  => 'SMALLINT',
            'bigint'    => 'BIGINT',
            'text'      => 'TEXT',
            'string'    => 'VARCHAR(%d)',
            'char'      => 'CHAR(%d)',
            'decimal'   => 'NUMERIC(%d,%d)',
            'float'     => 'REAL',
            'date'      => 'DATE',
            'time'      => 'TIME',
            'timestamp' => 'TIMESTAMP',
            'boolean'   => 'SMALLINT',
            'blob'      => 'BYTEA',
        ];
    }

    protected function getAutoIncrementSyntax(): string
    {
        // PostgreSQL uses sequences, not AUTO_INCREMENT syntax
        return 'NOT NULL PRIMARY KEY';
    }

    public function createSequence(Table $table): ?string
    {
        foreach ($table->getColumns() as $column) {
            if ($column->getType() === 'serial' && $column->isPrimary()) {
                $sequenceName = $this->getSequenceName($table->getName(), $column->getName());
                return sprintf(
                    "CREATE SEQUENCE %s START WITH 1 INCREMENT BY 1",
                    $sequenceName
                );
            }
        }
        return null;
    }

    public function dropSequence(string $tableName): ?string
    {
        // We need to know the column name, assuming convention: {table}_id
        $columnName = $this->guessSerialColumnName($tableName);
        if ($columnName !== null) {
            $sequenceName = $this->getSequenceName($tableName, $columnName);
            return sprintf('DROP SEQUENCE IF EXISTS %s', $sequenceName);
        }
        return null;
    }

    public function createTable(Table $table): string
    {
        $sql = parent::createTable($table);

        // Add DEFAULT nextval() for serial columns
        foreach ($table->getColumns() as $column) {
            if ($column->getType() === 'serial' && $column->isPrimary()) {
                $sequenceName = $this->getSequenceName($table->getName(), $column->getName());
                // The default is set via ALTER TABLE after sequence creation
                break;
            }
        }

        return $sql;
    }

    /**
     * Get additional SQL to set default for serial column after table creation
     */
    public function getSerialDefaultSql(Table $table): ?string
    {
        foreach ($table->getColumns() as $column) {
            if ($column->getType() === 'serial' && $column->isPrimary()) {
                $sequenceName = $this->getSequenceName($table->getName(), $column->getName());
                return sprintf(
                    "ALTER TABLE %s ALTER COLUMN %s SET DEFAULT nextval('%s')",
                    $table->getName(),
                    $column->getName(),
                    $sequenceName
                );
            }
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
            $baseName = substr($tableName, 4); // Remove 'dtb_'
            return $baseName . '_id';
        }
        if (strpos($tableName, 'mtb_') === 0) {
            $baseName = substr($tableName, 4); // Remove 'mtb_'
            return $baseName . '_id';
        }
        return null;
    }

    protected function translateDefaultExpression(string $expression): string
    {
        if ($expression === 'CURRENT_TIMESTAMP' || $expression === 'CURRENT_TIMESTAMP()') {
            return 'CURRENT_TIMESTAMP';
        }
        return $expression;
    }
}
