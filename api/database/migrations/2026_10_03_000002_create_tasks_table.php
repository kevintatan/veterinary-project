<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tareas pendientes (docs/database.md §8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->tenantForeign('owner_id', 'owners', nullable: true);
            $table->tenantForeign('pet_id', 'pets', nullable: true);
            $table->uuid('assignee_id')->nullable()->index();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('due_at')->nullable();
            $table->string('status', 15)->default('pending');
            $table->timestampTz('completed_at')->nullable();
            $table->foreignUuid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type', 60)->nullable(); // clinical_record, appointment…
            $table->uuid('source_id')->nullable();
            $table->timestampsTz();

            // Only members of the clinic can be assigned.
            $table->foreign(['clinic_id', 'assignee_id'])->references(['clinic_id', 'user_id'])->on('clinic_user')->restrictOnDelete();
            $table->index(['clinic_id', 'status', 'due_at']);
            $table->index(['source_type', 'source_id']);
        });
        DB::statement("ALTER TABLE tasks ADD CONSTRAINT tasks_status_check CHECK (status IN ('pending', 'done', 'discarded'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
