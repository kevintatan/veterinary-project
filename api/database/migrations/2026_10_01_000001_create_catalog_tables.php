<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mixed catalogs (docs/database.md D9): clinic_id NULL = global (seeded by
 * Zooetia, read-only for clinics); clinic_id set = the clinic's own entry.
 */
return new class extends Migration
{
    private const NIL = "'00000000-0000-0000-0000-000000000000'";

    public function up(): void
    {
        Schema::create('species', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('clinic_id')->nullable()->index()->constrained('clinics')->cascadeOnDelete();
            $table->string('code', 30)->nullable(); // stable key for global species (canine, feline…)
            $table->string('name', 60);
            $table->unsignedSmallInteger('sort')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX species_global_code_unique ON species (code) WHERE clinic_id IS NULL AND code IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX species_name_unique ON species (COALESCE(clinic_id, '.self::NIL.'), lower(name))');

        Schema::create('breeds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('clinic_id')->nullable()->index()->constrained('clinics')->cascadeOnDelete();
            $table->foreignUuid('species_id')->index()->constrained('species')->restrictOnDelete();
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX breeds_name_unique ON breeds (COALESCE(clinic_id, '.self::NIL.'), species_id, lower(name))');

        Schema::create('vaccines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('clinic_id')->nullable()->index()->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('manufacturer', 80)->nullable();
            $table->string('protects_against', 255)->nullable();
            $table->jsonb('species_ids')->default('[]');
            $table->unsignedSmallInteger('default_booster_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX vaccines_name_unique ON vaccines (COALESCE(clinic_id, '.self::NIL.'), lower(name))');

        Schema::create('procedure_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('clinic_id')->nullable()->index()->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('category', 20);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE procedure_types ADD CONSTRAINT procedure_types_category_check CHECK (category IN ('surgery', 'procedure', 'diagnostic'))");
        DB::statement('CREATE UNIQUE INDEX procedure_types_name_unique ON procedure_types (COALESCE(clinic_id, '.self::NIL.'), lower(name))');

        Schema::create('clinical_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('clinic_id')->nullable()->index()->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 30);
            $table->jsonb('species_ids')->default('[]'); // [] = any species
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE clinical_alerts ADD CONSTRAINT clinical_alerts_type_check CHECK (type IN ('infectious_disease', 'chronic_condition', 'allergy', 'behavior', 'other'))");
        DB::statement('CREATE UNIQUE INDEX clinical_alerts_name_unique ON clinical_alerts (COALESCE(clinic_id, '.self::NIL.'), lower(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_alerts');
        Schema::dropIfExists('procedure_types');
        Schema::dropIfExists('vaccines');
        Schema::dropIfExists('breeds');
        Schema::dropIfExists('species');
    }
};
