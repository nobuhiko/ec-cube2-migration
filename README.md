# EC-CUBE 2 Migration

EC-CUBE 2系用の軽量データベースマイグレーションツール

## 特徴

- **フル抽象化**: 1つのマイグレーションファイルで MySQL, PostgreSQL, SQLite3 に対応
- **軽量**: 依存は `symfony/console` のみ
- **EC-CUBE 2 統合**: MDB2/SC_Query をそのまま使用可能
- **CLI ツール**: 独立した実行ファイル、または ec-cube2/cli と統合可能

## インストール

```bash
composer require ec-cube2/migration
```

## 使い方

### マイグレーションの作成

```bash
# 新しいマイグレーションファイルを作成
php vendor/bin/eccube-migrate migrate:create CreateLoginAttemptTable
```

生成されるファイル: `data/migrations/Version20260130123456_CreateLoginAttemptTable.php`

### マイグレーションの実行

```bash
# 未実行のマイグレーションを全て実行
php vendor/bin/eccube-migrate migrate

# ステータス確認
php vendor/bin/eccube-migrate migrate:status

# ロールバック（1つ戻す）
php vendor/bin/eccube-migrate migrate:rollback

# ロールバック（3つ戻す）
php vendor/bin/eccube-migrate migrate:rollback --steps=3
```

## マイグレーションの書き方

### テーブル作成

```php
<?php

use Eccube2\Migration\Migration;
use Eccube2\Migration\Schema\Table;

class Version20260130000001_CreateLoginAttemptTable extends Migration
{
    public function up(): void
    {
        $this->create('dtb_login_attempt', function (Table $table) {
            // カラム定義
            $table->serial('login_attempt_id')->primary();
            $table->text('login_id')->notNull();
            $table->text('ip_address')->nullable();
            $table->smallint('result')->notNull();
            $table->timestamp('create_date')->default('CURRENT_TIMESTAMP');

            // インデックス
            $table->index(['login_id', 'create_date']);
        });
    }

    public function down(): void
    {
        $this->drop('dtb_login_attempt');
    }
}
```

### 既存テーブルの変更

```php
public function up(): void
{
    $this->table('dtb_customer', function (Table $table) {
        // カラム追加
        $table->addColumn('reset_token', 'text')->nullable();
        $table->addColumn('reset_token_expire', 'timestamp')->nullable();

        // インデックス追加
        $table->addIndex(['reset_token']);
    });
}

public function down(): void
{
    $this->table('dtb_customer', function (Table $table) {
        $table->dropIndex('idx_dtb_customer_reset_token');
        $table->dropColumn('reset_token_expire');
        $table->dropColumn('reset_token');
    });
}
```

### 生SQLの実行（DB別に異なる場合）

```php
public function up(): void
{
    // 全DBで同じSQL
    $this->sql("UPDATE dtb_baseinfo SET shop_name = 'New Name'");

    // DB別に異なるSQL
    $this->sql(
        "SELECT DATE_ADD(NOW(), INTERVAL 1 DAY)",        // MySQL
        "SELECT NOW() + INTERVAL '1 day'",              // PostgreSQL
        "SELECT datetime('now', '+1 day')"              // SQLite
    );
}
```

## 対応するカラム型

| 抽象型 | MySQL | PostgreSQL | SQLite |
|--------|-------|------------|--------|
| `serial()` | INT AUTO_INCREMENT | SERIAL | INTEGER PRIMARY KEY |
| `integer()` | INT | INTEGER | INTEGER |
| `smallint()` | SMALLINT | SMALLINT | INTEGER |
| `bigint()` | BIGINT | BIGINT | INTEGER |
| `text()` | TEXT | TEXT | TEXT |
| `string($n)` | VARCHAR(n) | VARCHAR(n) | TEXT |
| `decimal($p,$s)` | DECIMAL(p,s) | NUMERIC(p,s) | REAL |
| `timestamp()` | DATETIME | TIMESTAMP | TEXT |
| `boolean()` | SMALLINT | SMALLINT | INTEGER |
| `blob()` | BLOB | BYTEA | BLOB |

## カラム修飾子

```php
$table->text('name')
    ->notNull()              // NOT NULL
    ->nullable()             // NULL許可（デフォルト）
    ->default('value')       // デフォルト値
    ->primary();             // 主キー
```

## ec-cube2/cli との統合

ec-cube2/cli がインストールされている場合、マイグレーションコマンドは自動的に登録されます:

```bash
php data/vendor/bin/eccube migrate
php data/vendor/bin/eccube migrate:status
php data/vendor/bin/eccube migrate:rollback
php data/vendor/bin/eccube migrate:create MyMigration
```

## ライセンス

LGPL-3.0-or-later
