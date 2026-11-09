<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tutores y mascotas (docs/database.md §4).
 * *_search columns hold lowercase, accent-free text maintained by the models,
 * indexed with pg_trgm for fast typo-tolerant search.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Trusted extension (PG13+): the database owner can create it.
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('owners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->string('document_type', 10)->nullable();
            $table->string('document_number', 20)->nullable();
            $table->string('full_name', 150);
            $table->string('name_search', 150);
            $table->string('email')->nullable();
            $table->string('mobile_phone', 20)->nullable(); // E.164, WhatsApp key
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('municipality_code', 10)->nullable();
            $table->string('neighborhood', 80)->nullable();
            $table->string('alt_contact_name', 150)->nullable();
            $table->string('alt_contact_phone', 20)->nullable();
            $table->string('referral_source', 30)->nullable();
            $table->text('important_notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->tenantKey();
            $table->index(['clinic_id', 'mobile_phone']);
        });
        DB::statement("ALTER TABLE owners ADD CONSTRAINT owners_document_type_check CHECK (document_type IN ('CC', 'CE', 'NIT', 'PAS', 'TI', 'PPT', 'NUIP', 'DIE'))");
        DB::statement('CREATE UNIQUE INDEX owners_document_unique ON owners (clinic_id, document_type, document_number) WHERE document_number IS NOT NULL AND deleted_at IS NULL');
        DB::statement('CREATE INDEX owners_name_search_trgm ON owners USING gin (name_search gin_trgm_ops)');

        // Habeas Data evidence (Ley 1581). Append-only: current state = latest row per type.
        Schema::create('owner_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('owner_id', 'owners', onDelete: 'cascade');
            $table->string('type', 30);
            $table->boolean('granted');
            $table->string('policy_version', 20)->nullable();
            $table->string('source', 20);
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('evidence')->nullable();
            $table->timestampTz('created_at');

            $table->index(['owner_id', 'type', 'created_at']);
        });
        DB::statement("ALTER TABLE owner_consents ADD CONSTRAINT owner_consents_type_check CHECK (type IN ('data_processing', 'channel_whatsapp', 'channel_sms', 'channel_email', 'marketing'))");
        DB::statement("ALTER TABLE owner_consents ADD CONSTRAINT owner_consents_source_check CHECK (source IN ('in_person', 'online_booking', 'whatsapp', 'owner_portal', 'imported'))");

        Schema::create('pets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('owner_id', 'owners');
            $table->string('name', 80);
            $table->string('name_search', 80);
            $table->foreignUuid('species_id')->constrained('species')->restrictOnDelete();
            $table->foreignUuid('breed_id')->nullable()->constrained('breeds')->restrictOnDelete();
            $table->string('breed_other', 80)->nullable();
            $table->string('sex', 10)->default('unknown');
            $table->date('birth_date')->nullable();
            $table->boolean('birth_date_is_estimated')->default(false);
            $table->string('color', 60)->nullable();
            $table->string('size', 10)->nullable();
            $table->string('reproductive_status', 10)->default('unknown');
            $table->string('temperament', 15)->nullable();
            $table->string('diet', 120)->nullable();
            $table->string('microchip', 30)->nullable();
            $table->decimal('weight_kg', 7, 3)->nullable(); // cache of the latest weight
            $table->string('photo_path')->nullable();
            $table->boolean('is_service_animal')->default(false);
            $table->boolean('is_emotional_support')->default(false);
            $table->date('deceased_at')->nullable();
            $table->string('deceased_reason')->nullable();
            $table->text('important_notes')->nullable();
            // Pet card QR (docs/database.md §10b): random, regenerable, never the UUID.
            $table->string('public_token', 32)->unique();
            $table->boolean('public_profile_enabled')->default(true);
            $table->timestampTz('lost_mode_enabled_at')->nullable();
            $table->string('public_contact_phone', 20)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->tenantKey();
        });
        DB::statement("ALTER TABLE pets ADD CONSTRAINT pets_sex_check CHECK (sex IN ('male', 'female', 'unknown'))");
        DB::statement("ALTER TABLE pets ADD CONSTRAINT pets_size_check CHECK (size IN ('toy', 'small', 'medium', 'large', 'giant'))");
        DB::statement("ALTER TABLE pets ADD CONSTRAINT pets_reproductive_status_check CHECK (reproductive_status IN ('intact', 'neutered', 'unknown'))");
        DB::statement("ALTER TABLE pets ADD CONSTRAINT pets_temperament_check CHECK (temperament IN ('very_docile', 'docile', 'reserved', 'reactive', 'aggressive'))");
        DB::statement('ALTER TABLE pets ADD CONSTRAINT pets_weight_check CHECK (weight_kg > 0)');
        DB::statement('CREATE UNIQUE INDEX pets_microchip_unique ON pets (clinic_id, microchip) WHERE microchip IS NOT NULL AND deleted_at IS NULL');
        DB::statement('CREATE INDEX pets_name_search_trgm ON pets USING gin (name_search gin_trgm_ops)');

        Schema::create('pet_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('pet_id', 'pets', onDelete: 'cascade');
            $table->string('type', 30);
            $table->foreignUuid('clinical_alert_id')->nullable()->constrained('clinical_alerts')->restrictOnDelete();
            $table->string('description', 255);
            $table->string('severity', 10)->default('medium');
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE pet_alerts ADD CONSTRAINT pet_alerts_type_check CHECK (type IN ('infectious_disease', 'chronic_condition', 'allergy', 'behavior', 'other'))");
        DB::statement("ALTER TABLE pet_alerts ADD CONSTRAINT pet_alerts_severity_check CHECK (severity IN ('low', 'medium', 'high'))");

        Schema::create('pet_lifestyles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('pet_id', 'pets', onDelete: 'cascade');
            $table->string('housing', 120)->nullable();
            $table->string('food_amount', 60)->nullable();
            $table->string('feeding_frequency', 60)->nullable();
            $table->string('bath_frequency', 60)->nullable();
            $table->string('other_pets', 255)->nullable();
            $table->date('last_heat_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique('pet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_lifestyles');
        Schema::dropIfExists('pet_alerts');
        Schema::dropIfExists('pets');
        Schema::dropIfExists('owner_consents');
        Schema::dropIfExists('owners');
    }
};
