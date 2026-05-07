<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Добавляет soft delete на таблицу payments.
 *
 * Платежи не удаляются физически — финансовые записи должны оставаться
 * для аудита и возможного восстановления. Soft delete позволяет "скрыть"
 * запись из обычных запросов, сохраняя её в БД.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', static function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', static function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
