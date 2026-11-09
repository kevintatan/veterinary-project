<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda (docs/database.md §5 services + §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->string('name', 120);
            $table->string('category', 20);
            $table->unsignedSmallInteger('duration_minutes');
            $table->decimal('price', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('is_bookable_online')->default(false);
            $table->string('color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->tenantKey();
        });
        DB::statement("ALTER TABLE services ADD CONSTRAINT services_category_check CHECK (category IN ('consultation', 'vaccination', 'deworming', 'grooming', 'surgery', 'lab', 'imaging', 'hospitalization', 'other'))");
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_check CHECK (duration_minutes BETWEEN 5 AND 720)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_price_check CHECK (price >= 0 AND tax_rate >= 0)');
        DB::statement('CREATE UNIQUE INDEX services_name_unique ON services (clinic_id, lower(name))');

        // Regular opening hours: per branch (user_id NULL) or per professional.
        Schema::create('schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('branch_id', 'branches', onDelete: 'cascade');
            $table->uuid('user_id')->nullable()->index();
            $table->unsignedTinyInteger('weekday'); // ISO-8601: 1 = Monday … 7 = Sunday
            $table->time('start_time');
            $table->time('end_time');
            $table->timestampsTz();

            $table->foreign(['clinic_id', 'user_id'])->references(['clinic_id', 'user_id'])->on('clinic_user')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE schedules ADD CONSTRAINT schedules_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE schedules ADD CONSTRAINT schedules_time_check CHECK (end_time > start_time)');

        // Exceptions: holidays, vacations, trainings. NULL branch/user = whole clinic.
        Schema::create('schedule_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('branch_id', 'branches', nullable: true, onDelete: 'cascade');
            $table->uuid('user_id')->nullable()->index();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason', 150);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->foreign(['clinic_id', 'user_id'])->references(['clinic_id', 'user_id'])->on('clinic_user')->cascadeOnDelete();
            $table->index(['clinic_id', 'starts_at']);
        });
        DB::statement('ALTER TABLE schedule_blocks ADD CONSTRAINT schedule_blocks_time_check CHECK (ends_at > starts_at)');

        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('branch_id', 'branches');
            $table->tenantForeign('owner_id', 'owners', nullable: true);
            $table->tenantForeign('pet_id', 'pets', nullable: true);
            $table->tenantForeign('service_id', 'services', nullable: true);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->boolean('is_time_unset')->default(false); // attended by order of arrival
            $table->boolean('is_home_visit')->default(false);
            $table->string('home_address')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->string('source', 20)->default('staff');
            $table->text('notes_for_owner')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->tenantKey();
            $table->index(['clinic_id', 'starts_at']);
            $table->index(['clinic_id', 'branch_id', 'starts_at']);
            $table->index(['clinic_id', 'status', 'starts_at']);
        });
        DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_time_check CHECK (ends_at > starts_at)');
        DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_check CHECK (status IN ('scheduled', 'confirmed', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show'))");
        DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_source_check CHECK (source IN ('staff', 'online_booking', 'whatsapp'))");

        // Encargados (N:M). Only members of the clinic can be assigned.
        Schema::create('appointment_user', function (Blueprint $table) {
            $table->clinic();
            $table->tenantForeign('appointment_id', 'appointments', onDelete: 'cascade');
            $table->uuid('user_id')->index();
            $table->string('role', 10)->default('primary');

            $table->primary(['appointment_id', 'user_id']);
            $table->foreign(['clinic_id', 'user_id'])->references(['clinic_id', 'user_id'])->on('clinic_user')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE appointment_user ADD CONSTRAINT appointment_user_role_check CHECK (role IN ('primary', 'assistant'))");

        // Status history (append-only): base for WhatsApp confirmations and no-show metrics.
        Schema::create('appointment_status_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('appointment_id', 'appointments', onDelete: 'cascade');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20);
            $table->string('note')->nullable();
            $table->timestampTz('created_at');
        });
        DB::statement("ALTER TABLE appointment_status_changes ADD CONSTRAINT appointment_status_changes_channel_check CHECK (channel IN ('staff', 'whatsapp', 'online', 'system'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_status_changes');
        Schema::dropIfExists('appointment_user');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('schedule_blocks');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('services');
    }
};
