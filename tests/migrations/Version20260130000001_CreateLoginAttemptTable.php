<?php

declare(strict_types=1);

use Eccube2\Migration\Migration;
use Eccube2\Migration\Schema\Table;

/**
 * Example migration: Create login attempt tracking table
 */
class Version20260130000001_CreateLoginAttemptTable extends Migration
{
    public function up(): void
    {
        $this->create('dtb_login_attempt', function (Table $table) {
            $table->serial();
            $table->text('login_id')->notNull();
            $table->text('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->smallint('result')->notNull();
            $table->timestamp('create_date')->default('CURRENT_TIMESTAMP');

            $table->index(['login_id', 'create_date']);
            $table->index(['ip_address']);
        });
    }

    public function down(): void
    {
        $this->drop('dtb_login_attempt');
    }
}
