<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time codes of the owner portal (Fase 4e): today only to reset a
 * forgotten password, sent by email (free). Platform table, like
 * tutor_accounts. Only the HMAC of the code is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_login_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tutor_account_id')->constrained('tutor_accounts')->cascadeOnDelete();
            $table->string('purpose', 30)->default('password_reset');
            $table->string('channel', 20)->default('email');
            $table->string('code_hash', 64);
            $table->timestampTz('expires_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();

            $table->index(['tutor_account_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_login_codes');
    }
};
