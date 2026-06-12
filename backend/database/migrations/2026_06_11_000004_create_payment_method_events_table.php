<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_method_events', static function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->ulid('payment_method_id')->index();
            $table->string('event_name', 100)->index();
            $table->json('event_data');
            $table->timestamp('occurred_at');

            $table->unique(['payment_method_id', 'event_id']);

            $table->foreign('payment_method_id')
                ->references('id')
                ->on('payment_methods')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_events');
    }
};
