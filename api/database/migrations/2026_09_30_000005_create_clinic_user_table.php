<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Membership: which users work in which clinic.
        Schema::create('clinic_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->foreignUuid('user_id')->index()->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->boolean('shows_in_agenda')->default(true);
            $table->string('agenda_color', 7)->nullable();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampsTz();

            // Also the target of branch_user's composite FK: only members can be assigned to branches.
            $table->unique(['clinic_id', 'user_id']);
        });

        DB::statement("ALTER TABLE clinic_user ADD CONSTRAINT clinic_user_status_check CHECK (status IN ('active', 'disabled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_user');
    }
};
