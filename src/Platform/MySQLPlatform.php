<?php

declare(strict_types=1);

namespace Eccube2\Migration\Platform;

use Eccube2\Migration\Schema\Table;

class MySQLPlatform extends AbstractPlatform
{
    /** @var array<string, array<string, string>> Cache of table column types */
    private array $columnTypeCache = [];

    public function getName(): string
    {
        return 'mysqli';
    }

    protected function getTypeMap(): array
    {
        return [
            'serial'    => 'INT',
            'integer'   => 'INT',
            'smallint'  => 'SMALLINT',
            'bigint'    => 'BIGINT',
            'text'      => 'TEXT',
            'string'    => 'VARCHAR(%d)',
            'char'      => 'CHAR(%d)',
            'decimal'   => 'DECIMAL(%d,%d)',
            'float'     => 'FLOAT',
            'date'      => 'DATE',
            'time'      => 'TIME',
            'timestamp' => 'DATETIME',
            'boolean'   => 'SMALLINT',
            'blob'      => 'BLOB',
        ];
    }

    protected function getAutoIncrementSyntax(): string
    {
        return 'NOT NULL AUTO_INCREMENT PRIMARY KEY';
    }

    protected function isPrimaryKeyInline(): bool
    {
        return true;
    }

    protected function getTableOptions(): string
    {
        return ' ENGINE=InnoDB DEFAULT CHARSET=utf8';
    }

    protected function translateDefaultExpression(string $expression): string
    {
        if ($expression === 'CURRENT_TIMESTAMP' || $expression === 'CURRENT_TIMESTAMP()') {
            return 'CURRENT_TIMESTAMP';
        }
        return $expression;
    }

    protected function buildDropIndex(string $tableName, string $indexName): string
    {
        return sprintf('DROP INDEX %s ON %s', $indexName, $tableName);
    }

    public function createSequence(Table $table): ?string
    {
        foreach ($table->getColumns() as $column) {
            if ($column->getType() === 'serial' && $column->isPrimary()) {
                $sequenceName = $this->getSequenceName($table->getName(), $column->getName());
                return sprintf(
                    'CREATE TABLE %s (
    sequence INT NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (sequence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8',
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

    public function createIndexes(Table $table): array
    {
        $statements = [];
        $textColumns = $this->getTextColumnsFromTable($table);

        foreach ($table->getIndexes() as $index) {
            $type = $index['unique'] ? 'UNIQUE INDEX' : 'INDEX';
            $columns = $this->applyTextColumnPrefix($index['columns'], $textColumns);
            $statements[] = sprintf(
                'CREATE %s %s ON %s (%s)',
                $type,
                $index['name'],
                $table->getName(),
                implode(', ', $columns)
            );
        }

        return $statements;
    }

    public function alterTable(Table $table): array
    {
        $statements = [];
        $textColumns = null; // Lazy load

        foreach ($table->getOperations() as $operation) {
            switch ($operation['type']) {
                case 'addColumn':
                    $statements[] = sprintf(
                        'ALTER TABLE %s ADD COLUMN %s',
                        $table->getName(),
                        $this->buildColumnDefinition($operation['column'])
                    );
                    break;

                case 'dropColumn':
                    $statements[] = sprintf(
                        'ALTER TABLE %s DROP COLUMN %s',
                        $table->getName(),
                        $operation['name']
                    );
                    break;

                case 'renameColumn':
                    $statements[] = sprintf(
                        'ALTER TABLE %s RENAME COLUMN %s TO %s',
                        $table->getName(),
                        $operation['oldName'],
                        $operation['newName']
                    );
                    break;

                case 'addIndex':
                    if ($textColumns === null) {
                        $textColumns = $this->getTextColumnsFromDatabase($table->getName());
                        // Also include columns being added in this migration
                        $textColumns = array_merge($textColumns, $this->getTextColumnsFromTable($table));
                    }
                    $type = $operation['unique'] ? 'UNIQUE INDEX' : 'INDEX';
                    $columns = $this->applyTextColumnPrefix($operation['columns'], $textColumns);
                    $statements[] = sprintf(
                        'CREATE %s %s ON %s (%s)',
                        $type,
                        $operation['name'],
                        $table->getName(),
                        implode(', ', $columns)
                    );
                    break;

                case 'dropIndex':
                    $statements[] = $this->buildDropIndex($table->getName(), $operation['name']);
                    break;
            }
        }

        return $statements;
    }

    /**
     * Get TEXT type columns from Table object (for new tables)
     * @return string[]
     */
    private function getTextColumnsFromTable(Table $table): array
    {
        $textColumns = [];
        foreach ($table->getColumns() as $column) {
            if ($column->getType() === 'text') {
                $textColumns[] = $column->getName();
            }
        }
        return $textColumns;
    }

    /**
     * Get TEXT type columns from database (for existing tables)
     * @return string[]
     */
    private function getTextColumnsFromDatabase(string $tableName): array
    {
        if (isset($this->columnTypeCache[$tableName])) {
            return $this->columnTypeCache[$tableName];
        }

        $textColumns = [];

        if ($this->connection === null) {
            return $textColumns;
        }

        try {
            $sql = sprintf('SHOW COLUMNS FROM %s', $tableName);

            if ($this->connection instanceof \SC_Query) {
                $rows = $this->connection->getAll($sql);
                foreach ($rows as $row) {
                    $type = strtoupper($row['Type'] ?? $row['type'] ?? '');
                    if (strpos($type, 'TEXT') !== false) {
                        $textColumns[] = $row['Field'] ?? $row['field'];
                    }
                }
            } elseif ($this->connection instanceof \PDO) {
                $stmt = $this->connection->query($sql);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $type = strtoupper($row['Type'] ?? $row['type'] ?? '');
                    if (strpos($type, 'TEXT') !== false) {
                        $textColumns[] = $row['Field'] ?? $row['field'];
                    }
                }
            }
        } catch (\Exception $e) {
            // Table may not exist yet, return empty
        }

        $this->columnTypeCache[$tableName] = $textColumns;
        return $textColumns;
    }

    /**
     * Apply (255) prefix to TEXT columns that don't already have a length specified
     * @param string[] $columns
     * @param string[] $textColumns
     * @return string[]
     */
    private function applyTextColumnPrefix(array $columns, array $textColumns): array
    {
        return array_map(function (string $column) use ($textColumns): string {
            // Already has length specified (e.g., "memo(100)")
            if (strpos($column, '(') !== false) {
                return $column;
            }

            // Check if this column is TEXT type
            if (in_array($column, $textColumns, true)) {
                return $column . '(255)';
            }

            return $column;
        }, $columns);
    }
}
