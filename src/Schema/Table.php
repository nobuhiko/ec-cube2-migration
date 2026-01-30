<?php

declare(strict_types=1);

namespace Eccube2\Migration\Schema;

class Table
{
    private string $name;
    private array $columns = [];
    private array $indexes = [];
    private ?string $primaryKey = null;
    private bool $isAlter;

    /** @var array Operations for ALTER TABLE */
    private array $operations = [];

    public function __construct(string $name, bool $isAlter = false)
    {
        $this->name = $name;
        $this->isAlter = $isAlter;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getIndexes(): array
    {
        return $this->indexes;
    }

    public function getPrimaryKey(): ?string
    {
        return $this->primaryKey;
    }

    public function getOperations(): array
    {
        return $this->operations;
    }

    public function isAlter(): bool
    {
        return $this->isAlter;
    }

    // =====================
    // Integer Types
    // =====================

    /**
     * Add serial (auto-increment) primary key column.
     * Column name is automatically derived from table name following EC-CUBE convention:
     * - dtb_customer -> customer_id
     * - dtb_login_attempt -> login_attempt_id
     */
    public function serial(): Column
    {
        $columnName = $this->deriveSerialColumnName();
        $column = $this->addColumn($columnName, 'serial');
        $column->primary();
        $this->primaryKey = $columnName;
        return $column;
    }

    /**
     * Derive serial column name from table name.
     * EC-CUBE convention: dtb_xxx -> xxx_id, mtb_xxx -> xxx_id
     */
    private function deriveSerialColumnName(): string
    {
        $tableName = $this->name;

        if (strpos($tableName, 'dtb_') === 0) {
            return substr($tableName, 4) . '_id';
        }
        if (strpos($tableName, 'mtb_') === 0) {
            return substr($tableName, 4) . '_id';
        }

        // Fallback: table_name -> table_name_id
        return $tableName . '_id';
    }

    public function integer(string $name): Column
    {
        return $this->addColumn($name, 'integer');
    }

    public function smallint(string $name): Column
    {
        return $this->addColumn($name, 'smallint');
    }

    public function bigint(string $name): Column
    {
        return $this->addColumn($name, 'bigint');
    }

    // =====================
    // String Types
    // =====================

    public function text(string $name): Column
    {
        return $this->addColumn($name, 'text');
    }

    public function string(string $name, int $length = 255): Column
    {
        return $this->addColumn($name, 'string', ['length' => $length]);
    }

    public function char(string $name, int $length = 1): Column
    {
        return $this->addColumn($name, 'char', ['length' => $length]);
    }

    // =====================
    // Numeric Types
    // =====================

    public function decimal(string $name, int $precision = 10, int $scale = 2): Column
    {
        return $this->addColumn($name, 'decimal', [
            'precision' => $precision,
            'scale' => $scale,
        ]);
    }

    public function float(string $name): Column
    {
        return $this->addColumn($name, 'float');
    }

    // =====================
    // Date/Time Types
    // =====================

    public function date(string $name): Column
    {
        return $this->addColumn($name, 'date');
    }

    public function time(string $name): Column
    {
        return $this->addColumn($name, 'time');
    }

    public function timestamp(string $name): Column
    {
        return $this->addColumn($name, 'timestamp');
    }

    // =====================
    // Other Types
    // =====================

    public function boolean(string $name): Column
    {
        return $this->addColumn($name, 'boolean');
    }

    public function blob(string $name): Column
    {
        return $this->addColumn($name, 'blob');
    }

    // =====================
    // Column Operations (for ALTER TABLE)
    // =====================

    public function addColumn(string $name, string $type, array $options = []): Column
    {
        $column = new Column($name, $type, $options);
        $this->columns[$name] = $column;

        if ($this->isAlter) {
            $this->operations[] = ['type' => 'addColumn', 'column' => $column];
        }

        return $column;
    }

    public function dropColumn(string $name): self
    {
        $this->operations[] = ['type' => 'dropColumn', 'name' => $name];
        return $this;
    }

    public function renameColumn(string $oldName, string $newName): self
    {
        $this->operations[] = [
            'type' => 'renameColumn',
            'oldName' => $oldName,
            'newName' => $newName,
        ];
        return $this;
    }

    // =====================
    // Primary Key
    // =====================

    public function primary(string $column): self
    {
        $this->primaryKey = $column;
        if (isset($this->columns[$column])) {
            $this->columns[$column]->primary();
        }
        return $this;
    }

    // =====================
    // Indexes
    // =====================

    public function index(array $columns, ?string $name = null): self
    {
        $indexName = $name ?? $this->generateIndexName($columns);
        $this->indexes[] = [
            'name' => $indexName,
            'columns' => $columns,
            'unique' => false,
        ];

        if ($this->isAlter) {
            $this->operations[] = [
                'type' => 'addIndex',
                'name' => $indexName,
                'columns' => $columns,
                'unique' => false,
            ];
        }

        return $this;
    }

    public function unique(array $columns, ?string $name = null): self
    {
        $indexName = $name ?? $this->generateIndexName($columns, 'uniq');
        $this->indexes[] = [
            'name' => $indexName,
            'columns' => $columns,
            'unique' => true,
        ];

        if ($this->isAlter) {
            $this->operations[] = [
                'type' => 'addIndex',
                'name' => $indexName,
                'columns' => $columns,
                'unique' => true,
            ];
        }

        return $this;
    }

    public function dropIndex(string $name): self
    {
        $this->operations[] = ['type' => 'dropIndex', 'name' => $name];
        return $this;
    }

    public function addIndex(array $columns, ?string $name = null): self
    {
        return $this->index($columns, $name);
    }

    private function generateIndexName(array $columns, string $prefix = 'idx'): string
    {
        return $prefix . '_' . $this->name . '_' . implode('_', $columns);
    }
}
