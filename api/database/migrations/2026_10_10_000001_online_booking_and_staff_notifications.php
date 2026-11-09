<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Online booking (docs/reservas.md) and the staff notification bell.
 *
 * - appointments.status gains 'requested': an online booking holds its slot
 *   until the clinic accepts (→ scheduled) or rejects it (→ cancelled).
 * - appointments.booking_details: what the person typed (name, phone, pet…).
 *   Owner/pet are only linked or created when the clinic accepts, so a
 *   stranger's form never creates or reveals clients.
 * - staff_notifications: one row per person (own read state), per clinic.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE appointments DROP CONSTRAINT appointments_status_check');
        DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_check CHECK (status IN ('requested', 'scheduled', 'confirmed', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show'))");

        Schema::table('appointments', function (Blueprint $table) {
            $table->jsonb('booking_details')->nullable();
        });

        Schema::create('staff_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title', 150);
            $table->string('body', 300)->nullable();
            $table->string('url', 300)->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['clinic_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_notifications');

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('booking_details');
        });

        DB::statement("UPDATE appointments SET status = 'cancelled' WHERE status = 'requested'");
        DB::statement('ALTER TABLE appointments DROP CONSTRAINT appointments_status_check');
        DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_check CHECK (status IN ('scheduled', 'confirmed', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show'))");
    }
};
