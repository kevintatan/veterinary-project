<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bot state per phone (docs/database.md §10). Platform table: with the shared
 * number a conversation belongs to the phone, not to a clinic; active_clinic_id
 * is the clinic it is talking to right now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone', 20)->unique();                 // E.164
            $table->foreignUuid('active_clinic_id')->nullable()->index()->constrained('clinics')->nullOnDelete();
            $table->uuid('owner_id')->nullable()->index();          // owner in active_clinic_id
            $table->string('state', 30)->default('idle');           // idle, handoff_human
            $table->jsonb('context')->nullable();
            $table->timestampTz('last_inbound_at')->nullable();     // Meta's 24 h customer-service window
            $table->timestampTz('needs_attention_since')->nullable(); // free text the bot couldn't handle
            $table->uuid('assigned_user_id')->nullable();
            $table->timestampsTz();

            $table->index(['active_clinic_id', 'needs_attention_since']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
