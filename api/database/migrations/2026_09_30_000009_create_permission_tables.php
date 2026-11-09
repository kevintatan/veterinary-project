<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spatie laravel-permission tables with teams = clinics.
 * Adapted from the package stub: the team key (clinic_id) and model_id are
 * UUIDs here. Roles/permissions keep bigint ids (package-internal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('guard_name', 40);
            $table->timestampsTz();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            // null = global role template (admin, veterinarian, receptionist), assignable in any clinic.
            $table->foreignUuid('clinic_id')->nullable()->index()->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('guard_name', 40);
            $table->timestampsTz();

            $table->unique(['clinic_id', 'name', 'guard_name']);
        });
        // NULLs are distinct in unique indexes: enforce uniqueness of global roles separately.
        DB::statement('CREATE UNIQUE INDEX roles_global_name_unique ON roles (name, guard_name) WHERE clinic_id IS NULL');

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->string('model_type');
            $table->uuid('model_id');
            $table->foreignUuid('clinic_id')->index()->constrained('clinics')->cascadeOnDelete();

            $table->index(['model_id', 'model_type']);
            $table->primary(['clinic_id', 'permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('model_type');
            $table->uuid('model_id');
            $table->foreignUuid('clinic_id')->index()->constrained('clinics')->cascadeOnDelete();

            $table->index(['model_id', 'model_type']);
            $table->primary(['clinic_id', 'role_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id']);
        });

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
