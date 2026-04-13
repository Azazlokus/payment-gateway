<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', static function (Blueprint $table) {
            $table->string('payment_method_id')->nullable()->after('external_id')
                ->comment('ID сохранённого метода оплаты YooKassa (для recurring)');
        });
    }

    public function down(): void
    {
        Schema::table('payments', static function (Blueprint $table) {
            $table->dropColumn('payment_method_id');
        });
    }
};
