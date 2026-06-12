<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Отдельное хранилище доменных событий крипто-контекста. Раньше события
        // CryptoDeposit/CryptoRefund писались в payment_events (контекст Payments),
        // у которой FK на payments.id — депозитные payment_id туда не входят и
        // нарушали внешний ключ. Крипта — самостоятельный bounded context, поэтому
        // у неё своя таблица событий с FK на crypto_deposits.
        Schema::create('crypto_deposit_events', static function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('deposit_id')->index();
            $table->string('event_name', 100)->index();
            $table->json('event_data');
            $table->timestamp('occurred_at');

            $table->unique(['deposit_id', 'event_id']);

            $table->foreign('deposit_id')
                ->references('id')
                ->on('crypto_deposits')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_deposit_events');
    }
};
