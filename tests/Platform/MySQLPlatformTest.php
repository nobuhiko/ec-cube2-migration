<?php

declare(strict_types=1);

namespace Eccube2\Migration\Tests\Platform;

use Eccube2\Migration\Platform\MySQLPlatform;
use Eccube2\Migration\Schema\Table;
use PHPUnit\Framework\TestCase;

class MySQLPlatformTest extends TestCase
{
    private MySQLPlatform $platform;

    protected function setUp(): void
    {
        $this->platform = new MySQLPlatform();
    }

    public function testGetName(): void
    {
        $this->assertSame('mysqli', $this->platform->getName());
    }

    public function testCreateTableWithSerial(): void
    {
        $table = new Table('dtb_test');
        $table->serial('test_id')->primary();
        $table->text('name')->notNull();

        $sql = $this->platform->createTable($table);

        $this->assertStringContainsString('CREATE TABLE dtb_test', $sql);
        $this->assertStringContainsString('test_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY', $sql);
        $this->assertStringContainsString('name TEXT NOT NULL', $sql);
        $this->assertStringContainsString('ENGINE=InnoDB', $sql);
    }

    public function testCreateTableWithTimestamp(): void
    {
        $table = new Table('dtb_test');
        $table->serial('test_id')->primary();
        $table->timestamp('create_date')->default('CURRENT_TIMESTAMP');

        $sql = $this->platform->createTable($table);

        $this->assertStringContainsString('create_date DATETIME DEFAULT CURRENT_TIMESTAMP', $sql);
    }

    public function testCreateTableWithVarchar(): void
    {
        $table = new Table('dtb_test');
        $table->serial('test_id')->primary();
        $table->string('email', 255)->notNull();

        $sql = $this->platform->createTable($table);

        $this->assertStringContainsString('email VARCHAR(255) NOT NULL', $sql);
    }

    public function testCreateTableWithDecimal(): void
    {
        $table = new Table('dtb_test');
        $table->serial('test_id')->primary();
        $table->decimal('price', 12, 2)->notNull();

        $sql = $this->platform->createTable($table);

        $this->assertStringContainsString('price DECIMAL(12,2) NOT NULL', $sql);
    }

    public function testCreateIndex(): void
    {
        $table = new Table('dtb_test');
        $table->serial('test_id')->primary();
        $table->text('name');
        $table->index(['name']);

        $indexes = $this->platform->createIndexes($table);

        $this->assertCount(1, $indexes);
        $this->assertStringContainsString('CREATE INDEX idx_dtb_test_name ON dtb_test (name)', $indexes[0]);
    }

    public function testCreateUniqueIndex(): void
    {
        $table = new Table('dtb_test');
        $table->serial('test_id')->primary();
        $table->text('email');
        $table->unique(['email']);

        $indexes = $this->platform->createIndexes($table);

        $this->assertCount(1, $indexes);
        $this->assertStringContainsString('CREATE UNIQUE INDEX uniq_dtb_test_email ON dtb_test (email)', $indexes[0]);
    }

    public function testDropTable(): void
    {
        $sql = $this->platform->dropTable('dtb_test');

        $this->assertSame('DROP TABLE IF EXISTS dtb_test', $sql);
    }

    public function testAlterTableAddColumn(): void
    {
        $table = new Table('dtb_test', true);
        $table->addColumn('new_column', 'text')->nullable();

        $sqls = $this->platform->alterTable($table);

        $this->assertCount(1, $sqls);
        $this->assertStringContainsString('ALTER TABLE dtb_test ADD COLUMN new_column TEXT', $sqls[0]);
    }

    public function testAlterTableDropColumn(): void
    {
        $table = new Table('dtb_test', true);
        $table->dropColumn('old_column');

        $sqls = $this->platform->alterTable($table);

        $this->assertCount(1, $sqls);
        $this->assertStringContainsString('ALTER TABLE dtb_test DROP COLUMN old_column', $sqls[0]);
    }

    public function testColumnTypes(): void
    {
        $this->assertSame('INT', $this->platform->getColumnType('integer'));
        $this->assertSame('SMALLINT', $this->platform->getColumnType('smallint'));
        $this->assertSame('BIGINT', $this->platform->getColumnType('bigint'));
        $this->assertSame('TEXT', $this->platform->getColumnType('text'));
        $this->assertSame('DATETIME', $this->platform->getColumnType('timestamp'));
        $this->assertSame('SMALLINT', $this->platform->getColumnType('boolean'));
        $this->assertSame('BLOB', $this->platform->getColumnType('blob'));
    }

    public function testCreateSequenceReturnsNull(): void
    {
        $table = new Table('dtb_test');
        $table->serial('test_id')->primary();

        $this->assertNull($this->platform->createSequence($table));
    }
}
