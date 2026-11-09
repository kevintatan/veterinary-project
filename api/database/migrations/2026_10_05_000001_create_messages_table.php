<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single log of every message sent or received (docs/database.md §10):
 * manual WhatsApp links opened by staff (Fase 4a), automatic reminders and
 * bot conversations (4c/4d). Append-only except for the delivery status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Null only for inbound messages not yet routed to a clinic (shared number).
            $table->foreignUuid('clinic_id')->nullable()->index()->constrained('clinics')->restrictOnDelete();
            $table->uuid('owner_id')->nullable()->index();
            $table->foreign(['clinic_id', 'owner_id'])->references(['clinic_id', 'id'])->on('owners')->restrictOnDelete();

            $table->string('channel', 20);    // whatsapp, sms, email
            $table->string('direction', 10);  // outbound, inbound
            $table->string('provider', 20);   // manual (wa.me link), fake (development), meta
            $table->string('purpose', 40)->nullable(); // appointment_reminder, next_control, login_code, custom…
            $table->string('to_phone', 20)->nullable();
            $table->string('from_phone', 20)->nullable();
            $table->uuid('template_id')->nullable(); // FK added with message_templates (4c)
            $table->text('body')->nullable();
            $table->jsonb('payload')->nullable();
            $table->string('provider_message_id')->nullable()->unique(); // Meta wamid: webhook idempotency
            // opened (manual link), queued, sent, delivered, read, failed, received
            $table->string('status', 20);
            $table->string('pricing_category', 20)->nullable();
            $table->decimal('cost_usd', 10, 5)->nullable();
            $table->text('error')->nullable();
            $table->nullableUuidMorphs('related');
            $table->foreignUuid('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();

            $table->index(['clinic_id', 'owner_id', 'created_at']);
        });

        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_direction_check CHECK (direction IN ('outbound', 'inbound'))");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_channel_check CHECK (channel IN ('whatsapp', 'sms', 'email'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
