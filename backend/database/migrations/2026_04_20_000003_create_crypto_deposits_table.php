<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_deposits', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('payment_id')->index();
            $table->string('status', 32)->index();
            $table->string('asset', 32);
            $table->unsignedBigInteger('expected_units');
            $table->unsignedBigInteger('actual_units')->nullable();
            $table->unsignedBigInteger('fiat_amount_kopecks');
            $table->string('deposit_address', 100);
            $table->string('memo', 20)->unique();
            $table->string('tx_hash', 128)->nullable()->unique();
            $table->timestamp('expires_at');
            $table->unsignedInteger('created_at_ts');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_deposits');
    }
};
