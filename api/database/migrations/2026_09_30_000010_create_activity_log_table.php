<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spatie activitylog table + clinic_id (docs/database.md D10).
 * Morph ids are strings because subjects can be UUID models or Spatie roles (bigint).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            // Nullable: platform-level actions (e.g. registering a new clinic) happen outside a tenant.
            $table->foreignUuid('clinic_id')->nullable()->constrained('clinics')->cascadeOnDelete();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 36)->nullable();
            $table->string('event')->nullable();
            $table->string('causer_type')->nullable();
            $table->string('causer_id', 36)->nullable();
            $table->jsonb('attribute_changes')->nullable();
            $table->jsonb('properties')->nullable();
            $table->timestampsTz();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['causer_type', 'causer_id']);
            $table->index(['clinic_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
