<?php

declare(strict_types=1);

namespace Eccube2\Migration\Tests\Schema;

use Eccube2\Migration\Schema\Column;
use PHPUnit\Framework\TestCase;

class ColumnTest extends TestCase
{
    public function testColumnBasicProperties(): void
    {
        $column = new Column('test_column', 'text');

        $this->assertSame('test_column', $column->getName());
        $this->assertSame('text', $column->getType());
        $this->assertTrue($column->isNullable()); // Default is nullable
        $this->assertFalse($column->isPrimary());
        $this->assertFalse($column->hasDefault());
    }

    public function testColumnWithOptions(): void
    {
        $column = new Column('email', 'string', ['length' => 255]);

        $this->assertSame(255, $column->getOption('length'));
        $this->assertNull($column->getOption('nonexistent'));
        $this->assertSame('default', $column->getOption('nonexistent', 'default'));
    }

    public function testNotNull(): void
    {
        $column = new Column('name', 'text');
        $column->notNull();

        $this->assertFalse($column->isNullable());
    }

    public function testNullable(): void
    {
        $column = new Column('name', 'text');
        $column->notNull();
        $column->nullable();

        $this->assertTrue($column->isNullable());
    }

    public function testPrimary(): void
    {
        $column = new Column('id', 'serial');
        $column->primary();

        $this->assertTrue($column->isPrimary());
        $this->assertFalse($column->isNullable()); // Primary keys are NOT NULL
    }

    public function testDefaultString(): void
    {
        $column = new Column('status', 'text');
        $column->default('active');

        $this->assertTrue($column->hasDefault());
        $this->assertSame('active', $column->getDefault());
    }

    public function testDefaultInteger(): void
    {
        $column = new Column('count', 'integer');
        $column->default(0);

        $this->assertTrue($column->hasDefault());
        $this->assertSame(0, $column->getDefault());
    }

    public function testDefaultNull(): void
    {
        $column = new Column('optional', 'text');
        $column->default(null);

        $this->assertTrue($column->hasDefault());
        $this->assertNull($column->getDefault());
    }

    public function testDefaultCurrentTimestamp(): void
    {
        $column = new Column('create_date', 'timestamp');
        $column->default('CURRENT_TIMESTAMP');

        $this->assertTrue($column->hasDefault());
        $this->assertSame('CURRENT_TIMESTAMP', $column->getDefault());
    }

    public function testUnsigned(): void
    {
        $column = new Column('count', 'integer');
        $column->unsigned();

        $this->assertTrue($column->getOption('unsigned'));
    }

    public function testFluentInterface(): void
    {
        $column = new Column('price', 'decimal', ['precision' => 10, 'scale' => 2]);

        $result = $column->notNull()->default(0);

        $this->assertSame($column, $result);
        $this->assertFalse($column->isNullable());
        $this->assertTrue($column->hasDefault());
        $this->assertSame(0, $column->getDefault());
    }
}
