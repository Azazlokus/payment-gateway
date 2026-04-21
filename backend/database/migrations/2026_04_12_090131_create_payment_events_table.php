<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', static function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->ulid('payment_id')->index();
            $table->string('event_name', 100)->index();
            $table->json('event_data');
            $table->timestamp('occurred_at');

            $table->unique(['payment_id', 'event_id']);

            $table->foreign('payment_id')
                ->references('id')
                ->on('payments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
