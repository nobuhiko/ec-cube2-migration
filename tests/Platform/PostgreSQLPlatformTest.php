<?php

declare(strict_types=1);

namespace Eccube2\Migration\Tests\Platform;

use Eccube2\Migration\Platform\PostgreSQLPlatform;
use Eccube2\Migration\Schema\Table;
use PHPUnit\Framework\TestCase;

class PostgreSQLPlatformTest extends TestCase
{
    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->platform = new PostgreSQLPlatform();
    }

    public function testGetName(): void
    {
        $this->assertSame('pgsql', $this->platform->getName());
    }

    public function testCreateTableWithSerial(): void
    {
        $table = new Table('dtb_test');
        $table->serial();
        $table->text('name')->notNull();

        $sql = $this->platform->createTable($table);

        $this->assertStringContainsString('CREATE TABLE dtb_test', $sql);
        $this->assertStringContainsString('test_id INT NOT NULL PRIMARY KEY', $sql);
        $this->assertStringContainsString('name TEXT NOT NULL', $sql);
        // PostgreSQL doesn't use ENGINE=InnoDB
        $this->assertStringNotContainsString('ENGINE=', $sql);
    }

    public function testCreateSequence(): void
    {
        $table = new Table('dtb_test');
        $table->serial();

        $sql = $this->platform->createSequence($table);

        $this->assertNotNull($sql);
        $this->assertStringContainsString('CREATE SEQUENCE IF NOT EXISTS dtb_test_test_id_seq', $sql);
        $this->assertStringContainsString('START WITH 1', $sql);
    }

    public function testDropSequence(): void
    {
        $sql = $this->platform->dropSequence('dtb_test');

        $this->assertNotNull($sql);
        $this->assertStringContainsString('DROP SEQUENCE IF EXISTS dtb_test_test_id_seq', $sql);
    }

    public function testCreateTableWithTimestamp(): void
    {
        $table = new Table('dtb_test');
        $table->serial();
        $table->timestamp('create_date')->default('CURRENT_TIMESTAMP');

        $sql = $this->platform->createTable($table);

        $this->assertStringContainsString('create_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP', $sql);
    }

    public function testCreateTableWithDecimal(): void
    {
        $table = new Table('dtb_test');
        $table->serial();
        $table->decimal('price', 12, 2)->notNull();

        $sql = $this->platform->createTable($table);

        $this->assertStringContainsString('price NUMERIC(12,2) NOT NULL', $sql);
    }

    public function testColumnTypes(): void
    {
        $this->assertSame('INTEGER', $this->platform->getColumnType('integer'));
        $this->assertSame('SMALLINT', $this->platform->getColumnType('smallint'));
        $this->assertSame('BIGINT', $this->platform->getColumnType('bigint'));
        $this->assertSame('TEXT', $this->platform->getColumnType('text'));
        $this->assertSame('TIMESTAMP', $this->platform->getColumnType('timestamp'));
        $this->assertSame('SMALLINT', $this->platform->getColumnType('boolean'));
        $this->assertSame('BYTEA', $this->platform->getColumnType('blob'));
    }

    public function testCreateIndex(): void
    {
        $table = new Table('dtb_test');
        $table->serial();
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

    public function testAlterTableAddColumn(): void
    {
        $table = new Table('dtb_test', true);
        $table->addColumn('new_column', 'text')->nullable();

        $sqls = $this->platform->alterTable($table);

        $this->assertCount(1, $sqls);
        $this->assertStringContainsString('ALTER TABLE dtb_test ADD COLUMN new_column TEXT', $sqls[0]);
    }

    public function testAlterTableRenameColumn(): void
    {
        $table = new Table('dtb_test', true);
        $table->renameColumn('old_name', 'new_name');

        $sqls = $this->platform->alterTable($table);

        $this->assertCount(1, $sqls);
        $this->assertStringContainsString('ALTER TABLE dtb_test RENAME COLUMN old_name TO new_name', $sqls[0]);
    }

    public function testGetSerialDefaultSql(): void
    {
        $table = new Table('dtb_login_attempt');
        $table->serial();

        $sql = $this->platform->getSerialDefaultSql($table);

        $this->assertNotNull($sql);
        $this->assertStringContainsString('ALTER TABLE dtb_login_attempt', $sql);
        $this->assertStringContainsString('ALTER COLUMN login_attempt_id', $sql);
        $this->assertStringContainsString("SET DEFAULT nextval('dtb_login_attempt_login_attempt_id_seq')", $sql);
    }

    public function testGetSerialDefaultSqlReturnsNullForNonSerialTable(): void
    {
        $table = new Table('dtb_test');
        $table->integer('id')->primary();
        $table->text('name');

        $sql = $this->platform->getSerialDefaultSql($table);

        $this->assertNull($sql);
    }

    public function testSequenceNamingFollowsEcCubeConvention(): void
    {
        // EC-CUBE convention: dtb_{table}_{column}_seq
        $table = new Table('dtb_customer');
        $table->serial();

        $sequenceSql = $this->platform->createSequence($table);
        $defaultSql = $this->platform->getSerialDefaultSql($table);

        $this->assertStringContainsString('dtb_customer_customer_id_seq', $sequenceSql);
        $this->assertStringContainsString('dtb_customer_customer_id_seq', $defaultSql);
    }
}
