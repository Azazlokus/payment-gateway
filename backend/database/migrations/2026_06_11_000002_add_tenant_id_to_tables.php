<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var string[] */
    private array $tables = [
        'payments',
        'payment_events',
        'disputes',
        'refunds',
        'payment_links',
        'crypto_deposits',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->ulid('tenant_id')->nullable()->after('id');
                    $t->index('tenant_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropIndex(['tenant_id']);
                    $t->dropColumn('tenant_id');
                });
            }
        }
    }
};
