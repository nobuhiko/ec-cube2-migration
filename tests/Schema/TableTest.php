<?php

declare(strict_types=1);

namespace Eccube2\Migration\Tests\Schema;

use Eccube2\Migration\Schema\Table;
use PHPUnit\Framework\TestCase;

class TableTest extends TestCase
{
    public function testTableName(): void
    {
        $table = new Table('dtb_test');

        $this->assertSame('dtb_test', $table->getName());
        $this->assertFalse($table->isAlter());
    }

    public function testTableIsAlter(): void
    {
        $table = new Table('dtb_test', true);

        $this->assertTrue($table->isAlter());
    }

    public function testSerialColumn(): void
    {
        $table = new Table('dtb_test');
        $column = $table->serial();

        // Column name is automatically derived from table name
        $this->assertSame('test_id', $column->getName());
        $this->assertSame('serial', $column->getType());
        // Serial is automatically primary key
        $this->assertTrue($column->isPrimary());
    }

    public function testSerialColumnNaming(): void
    {
        // dtb_customer -> customer_id
        $table1 = new Table('dtb_customer');
        $this->assertSame('customer_id', $table1->serial()->getName());

        // dtb_login_attempt -> login_attempt_id
        $table2 = new Table('dtb_login_attempt');
        $this->assertSame('login_attempt_id', $table2->serial()->getName());

        // mtb_status -> status_id
        $table3 = new Table('mtb_status');
        $this->assertSame('status_id', $table3->serial()->getName());
    }

    public function testIntegerColumn(): void
    {
        $table = new Table('dtb_test');
        $column = $table->integer('count');

        $this->assertSame('count', $column->getName());
        $this->assertSame('integer', $column->getType());
    }

    public function testSmallintColumn(): void
    {
        $table = new Table('dtb_test');
        $column = $table->smallint('status');

        $this->assertSame('status', $column->getName());
        $this->assertSame('smallint', $column->getType());
    }

    public function testBigintColumn(): void
    {
        $table = new Table('dtb_test');
        $column = $table->bigint('big_number');

        $this->assertSame('big_number', $column->getName());
        $this->assertSame('bigint', $column->getType());
    }

    public function testTextColumn(): void
    {
        $table = new Table('dtb_test');
        $column = $table->text('description');

        $this->assertSame('description', $column->getName());
        $this->assertSame('text', $column->getType());
    }

    public function testStringColumn(): void
    {
        $table = new Table('dtb_test');
        $column = $table->string('email', 255);

        $this->assertSame('email', $column->getName());
        $this->assertSame('string', $column->getType());
        $this->assertSame(255, $column->getOption('length'));
    }

    public function testCharColumn(): void
    {
        $table = new Table('dtb_test');
        $column = $table->char('code', 3);

        $this->assertSame('code', $column->getName());
        $this->assertSame('char', $column->getType());
        $this->assertSame(3, $column->getOption('length'));
    }

    public function testDecimalColumn(): void
    {
        $table = new Table('dtb_test');
        $column = $table->decimal('price', 12, 2);

        $this->assertSame('price', $column->getName());
        $this->assertSame('decimal', $column->getType());
        $this->assertSame(12, $column->getOption('precision'));
        $this->assertSame(2, $column->getOption('scale'));
    }

    public function testFloatColumn(): void
    {
        $table = new Table('dtb_test');
        $column = $table->float('rate');

        $this->assertSame('rate', $column->getName());
        $this->assertSame('float', $column->getType());
    }

    public function testDateColumn(): void
    {
        $table = new Table('dtb_test');
        $column = $table->date('birth_date');

        $this->assertSame('birth_date', $column->getName());
        $this->assertSame('date', $column->getType());
    }

    public function testTimeColumn(): void
    {
        $table = new Table('dtb_test');
        $column = $table->time('start_time');

        $this->assertSame('start_time', $column->getName());
        $this->assertSame('time', $column->getType());
    }

    public function testTimestampColumn(): void
    {
        $table = new Table('dtb_test');
        $column = $table->timestamp('create_date');

        $this->assertSame('create_date', $column->getName());
        $this->assertSame('timestamp', $column->getType());
    }

    public function testBooleanColumn(): void
    {
        $table = new Table('dtb_test');
        $column = $table->boolean('is_active');

        $this->assertSame('is_active', $column->getName());
        $this->assertSame('boolean', $column->getType());
    }

    public function testBlobColumn(): void
    {
        $table = new Table('dtb_test');
        $column = $table->blob('data');

        $this->assertSame('data', $column->getName());
        $this->assertSame('blob', $column->getType());
    }

    public function testPrimaryKey(): void
    {
        $table = new Table('dtb_test');
        $table->serial();

        $columns = $table->getColumns();
        $this->assertTrue($columns['test_id']->isPrimary());
        $this->assertSame('test_id', $table->getPrimaryKey());
    }

    public function testIndex(): void
    {
        $table = new Table('dtb_test');
        $table->text('name');
        $table->index(['name']);

        $indexes = $table->getIndexes();
        $this->assertCount(1, $indexes);
        $this->assertSame('idx_dtb_test_name', $indexes[0]['name']);
        $this->assertSame(['name'], $indexes[0]['columns']);
        $this->assertFalse($indexes[0]['unique']);
    }

    public function testCompositeIndex(): void
    {
        $table = new Table('dtb_test');
        $table->text('first_name');
        $table->text('last_name');
        $table->index(['first_name', 'last_name']);

        $indexes = $table->getIndexes();
        $this->assertCount(1, $indexes);
        $this->assertSame('idx_dtb_test_first_name_last_name', $indexes[0]['name']);
        $this->assertSame(['first_name', 'last_name'], $indexes[0]['columns']);
    }

    public function testUniqueIndex(): void
    {
        $table = new Table('dtb_test');
        $table->text('email');
        $table->unique(['email']);

        $indexes = $table->getIndexes();
        $this->assertCount(1, $indexes);
        $this->assertSame('uniq_dtb_test_email', $indexes[0]['name']);
        $this->assertTrue($indexes[0]['unique']);
    }

    public function testCustomIndexName(): void
    {
        $table = new Table('dtb_test');
        $table->text('name');
        $table->index(['name'], 'my_custom_index');

        $indexes = $table->getIndexes();
        $this->assertSame('my_custom_index', $indexes[0]['name']);
    }

    public function testAlterTableOperations(): void
    {
        $table = new Table('dtb_test', true);
        $table->addColumn('new_column', 'text')->nullable();
        $table->dropColumn('old_column');
        $table->renameColumn('old_name', 'new_name');
        $table->addIndex(['new_column']);
        $table->dropIndex('old_index');

        $operations = $table->getOperations();
        $this->assertCount(5, $operations);

        $this->assertSame('addColumn', $operations[0]['type']);
        $this->assertSame('dropColumn', $operations[1]['type']);
        $this->assertSame('renameColumn', $operations[2]['type']);
        $this->assertSame('addIndex', $operations[3]['type']);
        $this->assertSame('dropIndex', $operations[4]['type']);
    }
}
