<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_refund_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('deposit_id')->index();
            $table->string('to_address');
            $table->unsignedBigInteger('amount_units');
            $table->string('asset', 20);
            $table->string('status', 20)->default('pending')->index();
            $table->string('tx_hash')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_refund_requests');
    }
};
