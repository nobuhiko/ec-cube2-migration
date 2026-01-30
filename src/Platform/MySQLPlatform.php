<?php

declare(strict_types=1);

namespace Eccube2\Migration\Platform;

use Eccube2\Migration\Schema\Table;

class MySQLPlatform extends AbstractPlatform
{
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
}
