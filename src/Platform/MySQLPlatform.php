<?php

declare(strict_types=1);

namespace Eccube2\Migration\Platform;

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
}
