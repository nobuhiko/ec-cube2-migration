<?php

declare(strict_types=1);

namespace Eccube2\Migration\Platform;

use Eccube2\Migration\Schema\Column;
use Eccube2\Migration\Schema\Table;

abstract class AbstractPlatform implements PlatformInterface
{
    abstract public function getName(): string;

    abstract protected function getTypeMap(): array;

    abstract protected function getAutoIncrementSyntax(): string;

    public function createTable(Table $table): string
    {
        $columns = [];
        $primaryKey = null;

        foreach ($table->getColumns() as $column) {
            $columnDef = $this->buildColumnDefinition($column);
            $columns[] = $columnDef;

            if ($column->isPrimary()) {
                $primaryKey = $column->getName();
            }
        }

        // Add PRIMARY KEY constraint
        if ($primaryKey !== null && !$this->isPrimaryKeyInline()) {
            $columns[] = sprintf('PRIMARY KEY (%s)', $primaryKey);
        }

        $sql = sprintf(
            "CREATE TABLE %s (\n    %s\n)",
            $table->getName(),
            implode(",\n    ", $columns)
        );

        return $sql . $this->getTableOptions();
    }

    public function dropTable(string $tableName): string
    {
        return sprintf('DROP TABLE IF EXISTS %s', $tableName);
    }

    public function createIndexes(Table $table): array
    {
        $statements = [];

        foreach ($table->getIndexes() as $index) {
            $type = $index['unique'] ? 'UNIQUE INDEX' : 'INDEX';
            $statements[] = sprintf(
                'CREATE %s %s ON %s (%s)',
                $type,
                $index['name'],
                $table->getName(),
                implode(', ', $index['columns'])
            );
        }

        return $statements;
    }

    public function alterTable(Table $table): array
    {
        $statements = [];

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
                    $statements = array_merge(
                        $statements,
                        $this->buildDropColumn($table->getName(), $operation['name'])
                    );
                    break;

                case 'renameColumn':
                    $statements[] = $this->buildRenameColumn(
                        $table->getName(),
                        $operation['oldName'],
                        $operation['newName']
                    );
                    break;

                case 'addIndex':
                    $type = $operation['unique'] ? 'UNIQUE INDEX' : 'INDEX';
                    $statements[] = sprintf(
                        'CREATE %s %s ON %s (%s)',
                        $type,
                        $operation['name'],
                        $table->getName(),
                        implode(', ', $operation['columns'])
                    );
                    break;

                case 'dropIndex':
                    $statements[] = $this->buildDropIndex($table->getName(), $operation['name']);
                    break;
            }
        }

        return $statements;
    }

    public function createSequence(Table $table): ?string
    {
        return null;
    }

    public function dropSequence(string $tableName): ?string
    {
        return null;
    }

    public function getSerialDefaultSql(Table $table): ?string
    {
        return null;
    }

    protected function buildColumnDefinition(Column $column): string
    {
        $parts = [
            $column->getName(),
            $this->getColumnType($column->getType(), $column->getOptions()),
        ];

        // Handle PRIMARY KEY for serial types (auto-increment syntax includes NOT NULL)
        $hasNotNull = false;
        if ($column->isPrimary() && $column->getType() === 'serial') {
            $parts[] = $this->getAutoIncrementSyntax();
            $hasNotNull = true; // getAutoIncrementSyntax() includes NOT NULL
        } elseif ($column->isPrimary() && $this->isPrimaryKeyInline()) {
            $parts[] = 'PRIMARY KEY';
        }

        // NOT NULL (skip if already added by auto-increment syntax)
        if (!$hasNotNull && !$column->isNullable()) {
            $parts[] = 'NOT NULL';
        }

        // DEFAULT
        if ($column->hasDefault()) {
            $parts[] = 'DEFAULT ' . $this->getDefaultValue($column->getDefault());
        }

        return implode(' ', $parts);
    }

    public function getColumnType(string $abstractType, array $options = []): string
    {
        $typeMap = $this->getTypeMap();

        if (!isset($typeMap[$abstractType])) {
            throw new \InvalidArgumentException(sprintf('Unknown column type: %s', $abstractType));
        }

        $type = $typeMap[$abstractType];

        // Handle parameterized types
        if ($abstractType === 'string') {
            $length = $options['length'] ?? 255;
            return sprintf($type, $length);
        }

        if ($abstractType === 'char') {
            $length = $options['length'] ?? 1;
            return sprintf($type, $length);
        }

        if ($abstractType === 'decimal') {
            $precision = $options['precision'] ?? 10;
            $scale = $options['scale'] ?? 2;
            return sprintf($type, $precision, $scale);
        }

        return $type;
    }

    public function getDefaultValue($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        // Handle SQL expressions like CURRENT_TIMESTAMP
        if (is_string($value) && preg_match('/^[A-Z_]+(\(\))?$/', $value)) {
            return $this->translateDefaultExpression($value);
        }

        return sprintf("'%s'", addslashes((string) $value));
    }

    protected function translateDefaultExpression(string $expression): string
    {
        return $expression;
    }

    protected function isPrimaryKeyInline(): bool
    {
        return false;
    }

    protected function getTableOptions(): string
    {
        return '';
    }

    /**
     * @return string[]
     */
    protected function buildDropColumn(string $tableName, string $columnName): array
    {
        return [sprintf('ALTER TABLE %s DROP COLUMN %s', $tableName, $columnName)];
    }

    protected function buildRenameColumn(string $tableName, string $oldName, string $newName): string
    {
        return sprintf('ALTER TABLE %s RENAME COLUMN %s TO %s', $tableName, $oldName, $newName);
    }

    protected function buildDropIndex(string $tableName, string $indexName): string
    {
        return sprintf('DROP INDEX %s', $indexName);
    }
}
