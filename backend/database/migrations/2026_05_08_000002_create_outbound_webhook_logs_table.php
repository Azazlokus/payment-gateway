<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Лог исходящих уведомлений (outbound webhooks).
 *
 * Каждая строка — одна попытка отправки на notification_url клиента.
 * Позволяет дебажить проблемы интеграции: что отправили, что получили в ответ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_webhook_logs', static function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('payment_id', 26)->index();
            $table->string('url');
            $table->json('payload');
            $table->unsignedSmallInteger('attempt')->default(1);

            // Результат
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable(); // время ответа в мс
            $table->boolean('success')->default(false)->index();
            $table->text('error')->nullable(); // сообщение об ошибке если exception

            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_webhook_logs');
    }
};
