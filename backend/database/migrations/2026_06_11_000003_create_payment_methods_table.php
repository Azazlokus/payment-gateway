<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->nullable()->index();
            $table->string('customer_id')->index();
            $table->string('provider');
            $table->string('type');
            $table->text('token');
            $table->string('last4', 4);
            $table->string('brand');
            $table->string('expires_at')->nullable();
            $table->string('fingerprint')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'fingerprint'], 'pm_customer_fingerprint_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
