<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('payment_id')->index();
            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            $table->string('status', 20)->index(); // Filed, Won, Lost
            $table->unsignedInteger('amount');
            $table->char('currency', 3)->default('RUB');
            $table->string('reason');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
