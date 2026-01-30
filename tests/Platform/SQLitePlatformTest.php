<?php

declare(strict_types=1);

namespace Eccube2\Migration\Tests\Platform;

use Eccube2\Migration\Platform\SQLitePlatform;
use Eccube2\Migration\Schema\Table;
use PHPUnit\Framework\TestCase;

class SQLitePlatformTest extends TestCase
{
    private SQLitePlatform $platform;

    protected function setUp(): void
    {
        $this->platform = new SQLitePlatform();
    }

    public function testGetName(): void
    {
        $this->assertSame('sqlite3', $this->platform->getName());
    }

    public function testCreateTableWithSerial(): void
    {
        $table = new Table('dtb_test');
        $table->serial('test_id')->primary();
        $table->text('name')->notNull();

        $sql = $this->platform->createTable($table);

        $this->assertStringContainsString('CREATE TABLE dtb_test', $sql);
        // SQLite uses INTEGER PRIMARY KEY for auto-increment
        $this->assertStringContainsString('test_id INTEGER PRIMARY KEY', $sql);
        $this->assertStringContainsString('name TEXT NOT NULL', $sql);
    }

    public function testCreateTableWithTimestamp(): void
    {
        $table = new Table('dtb_test');
        $table->serial('test_id')->primary();
        $table->timestamp('create_date')->default('CURRENT_TIMESTAMP');

        $sql = $this->platform->createTable($table);

        // SQLite uses TEXT for timestamps and datetime() function
        $this->assertStringContainsString('create_date TEXT', $sql);
        $this->assertStringContainsString("datetime('now','localtime')", $sql);
    }

    public function testCreateTableWithVarchar(): void
    {
        $table = new Table('dtb_test');
        $table->serial('test_id')->primary();
        $table->string('email', 255)->notNull();

        $sql = $this->platform->createTable($table);

        // SQLite stores VARCHAR as TEXT
        $this->assertStringContainsString('email TEXT NOT NULL', $sql);
    }

    public function testColumnTypes(): void
    {
        // SQLite has limited type affinity
        $this->assertSame('INTEGER', $this->platform->getColumnType('integer'));
        $this->assertSame('INTEGER', $this->platform->getColumnType('smallint'));
        $this->assertSame('INTEGER', $this->platform->getColumnType('bigint'));
        $this->assertSame('TEXT', $this->platform->getColumnType('text'));
        $this->assertSame('TEXT', $this->platform->getColumnType('string', ['length' => 255]));
        $this->assertSame('TEXT', $this->platform->getColumnType('timestamp'));
        $this->assertSame('INTEGER', $this->platform->getColumnType('boolean'));
        $this->assertSame('BLOB', $this->platform->getColumnType('blob'));
        $this->assertSame('REAL', $this->platform->getColumnType('decimal', ['precision' => 10, 'scale' => 2]));
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

    public function testDropTable(): void
    {
        $sql = $this->platform->dropTable('dtb_test');

        $this->assertSame('DROP TABLE IF EXISTS dtb_test', $sql);
    }

    public function testCreateSequenceReturnsNull(): void
    {
        $table = new Table('dtb_test');
        $table->serial('test_id')->primary();

        // SQLite doesn't use sequences
        $this->assertNull($this->platform->createSequence($table));
    }

    public function testDropSequenceReturnsNull(): void
    {
        // SQLite doesn't use sequences
        $this->assertNull($this->platform->dropSequence('dtb_test'));
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
        // Modern SQLite (3.35.0+) supports DROP COLUMN
        $this->assertStringContainsString('ALTER TABLE dtb_test DROP COLUMN old_column', $sqls[0]);
    }
}
