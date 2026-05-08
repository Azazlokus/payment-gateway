<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Таблица отдельных возвратов по платежу.
 *
 * Один платёж может иметь несколько частичных возвратов.
 * Каждый возврат — отдельная строка с суммой, причиной и статусом.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', static function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('payment_id', 26)->index();
            $table->string('external_id')->nullable(); // ID возврата у провайдера
            $table->unsignedInteger('amount');
            $table->char('currency', 3)->default('RUB');
            $table->string('reason')->nullable();
            $table->string('status', 30)->default('pending'); // pending|succeeded|failed
            $table->string('idempotency_key', 36)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
