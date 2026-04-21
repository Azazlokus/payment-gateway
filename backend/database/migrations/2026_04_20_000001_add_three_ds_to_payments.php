<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('three_ds_required')->default(false)->after('confirmation_url');
            $table->string('three_ds_challenge_url')->nullable()->after('three_ds_required');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['three_ds_required', 'three_ds_challenge_url']);
        });
    }
};
