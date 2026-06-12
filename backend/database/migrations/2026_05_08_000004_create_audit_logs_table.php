<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', static function (Blueprint $table): void {
            $table->id();
            $table->string('action');              // payment.created, payment.cancelled, etc.
            $table->string('subject_type')->nullable();  // App\Contexts\Payments\...Payment
            $table->string('subject_id')->nullable();    // payment UUID
            $table->ipAddress('ip')->nullable();
            $table->string('api_key_hint')->nullable();  // last 4 chars of used key
            $table->json('metadata')->nullable();        // extra context
            $table->timestamp('created_at');
        });

        // Индексы для фильтрации в UI
        Schema::table('audit_logs', static function (Blueprint $table): void {
            $table->index('action');
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
            $table->index('ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
