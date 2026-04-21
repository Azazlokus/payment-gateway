<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crypto_deposits', function (Blueprint $table) {
            // memo is not used for address-based chains (BTC, TRX, USDT_TRC20)
            $table->string('memo', 20)->nullable()->unique()->change();
            // BTC/TRON addresses can be up to 62 chars; give headroom
            $table->string('deposit_address', 128)->change();
        });
    }

    public function down(): void
    {
        Schema::table('crypto_deposits', function (Blueprint $table) {
            $table->string('memo', 20)->nullable(false)->change();
            $table->string('deposit_address', 100)->change();
        });
    }
};
