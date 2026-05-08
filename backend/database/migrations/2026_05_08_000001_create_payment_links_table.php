<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment Links — ссылки на оплату без API.
 *
 * Создаётся один раз, имеет TTL и лимит использований.
 * После оплаты или истечения срока становится неактивной.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_links', static function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('token', 64)->unique()->index();
            $table->unsignedInteger('amount');
            $table->char('currency', 3)->default('RUB');
            $table->string('description');
            $table->string('provider', 50)->default('yookassa');
            $table->string('return_url')->nullable();
            $table->json('metadata')->nullable();

            // Ограничения использования
            $table->unsignedSmallInteger('max_uses')->default(1);
            $table->unsignedSmallInteger('uses')->default(0);
            $table->timestamp('expires_at')->nullable();

            // Последний созданный платёж через эту ссылку
            $table->string('last_payment_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_links');
    }
};
