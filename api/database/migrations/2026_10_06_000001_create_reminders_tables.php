<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Automatic reminders (Fase 4c, docs/database.md §10): the clinic's rules,
 * the scheduled instances and the monthly usage per clinic (plan limits, cost).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->string('trigger', 20);              // appointment, next_control
            $table->string('record_type', 30)->nullable(); // next_control only: vaccination, deworming… (null = any)
            $table->integer('offset_minutes');          // negative = before the event
            $table->string('channel', 20)->default('whatsapp');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->tenantKey();
        });
        DB::statement("ALTER TABLE reminder_rules ADD CONSTRAINT reminder_rules_trigger_check CHECK (trigger IN ('appointment', 'next_control'))");

        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->uuidMorphs('remindable');           // appointment | clinical_record
            $table->tenantForeign('owner_id', 'owners', nullable: true);
            $table->tenantForeign('rule_id', 'reminder_rules', nullable: true, onDelete: 'set null');
            $table->string('channel', 20);
            $table->string('purpose', 40);              // appointment_reminder, next_control
            // The appointment start / control date it was planned for: a reschedule plans a new reminder.
            $table->timestampTz('event_at');
            $table->timestampTz('scheduled_for');
            // pending → queued → sending → sent | failed; or cancelled | skipped_* (no_consent, no_phone, deceased, answered, limit, plan)
            $table->string('status', 30)->default('pending');
            $table->uuid('message_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'scheduled_for']); // the dispatcher's query
        });

        Schema::create('usage_counters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->char('period', 7);                  // 2026-10
            $table->string('metric', 40);               // whatsapp_utility, whatsapp_service, sms, email…
            $table->unsignedBigInteger('quantity')->default(0);
            $table->decimal('cost', 14, 4)->default(0);
            $table->timestampsTz();
            $table->unique(['clinic_id', 'period', 'metric']);
        });

        // Default rules for the clinics that already exist (new ones get them on registration).
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            foreach ([['appointment', null, -1440], ['next_control', null, -10080]] as [$trigger, $type, $offset]) {
                DB::table('reminder_rules')->insert([
                    'id' => (string) Str::uuid7(), 'clinic_id' => $clinicId, 'trigger' => $trigger, 'record_type' => $type,
                    'offset_minutes' => $offset, 'channel' => 'whatsapp', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('reminder_rules');
        Schema::dropIfExists('usage_counters');
    }
};
