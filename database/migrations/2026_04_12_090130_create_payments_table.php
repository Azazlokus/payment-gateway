<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', static function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('idempotency_key', 36)->unique();
            $table->string('external_id')->nullable()->index();
            $table->string('provider', 50);
            $table->unsignedInteger('amount');
            $table->char('currency', 3)->default('RUB');
            $table->string('description');
            $table->string('status', 30)->index();
            $table->string('confirmation_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
