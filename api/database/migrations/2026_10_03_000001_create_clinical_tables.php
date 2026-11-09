<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historia clínica (docs/database.md §7): parent clinical_records + 1:1 detail
 * tables sharing its id. Records are never deleted: they are voided and locked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('branch_id', 'branches');
            $table->tenantForeign('pet_id', 'pets');
            $table->string('type', 20);
            $table->timestampTz('occurred_at');
            $table->uuid('author_id')->index();
            $table->tenantForeign('appointment_id', 'appointments', nullable: true);
            $table->uuid('parent_record_id')->nullable()->index();
            $table->date('next_control_at')->nullable();
            $table->string('next_control_note', 255)->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->foreignUuid('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->boolean('ai_assisted')->default(false);
            $table->boolean('visible_to_owner')->default(false);
            $table->timestampsTz();

            $table->tenantKey();
            // The author must be a member of the clinic.
            $table->foreign(['clinic_id', 'author_id'])->references(['clinic_id', 'user_id'])->on('clinic_user')->restrictOnDelete();
            $table->foreign(['clinic_id', 'parent_record_id'])->references(['clinic_id', 'id'])->on('clinical_records')->restrictOnDelete();
            $table->index(['clinic_id', 'pet_id', 'occurred_at']);
            $table->index(['clinic_id', 'type', 'occurred_at']);
        });
        DB::statement("ALTER TABLE clinical_records ADD CONSTRAINT clinical_records_type_check CHECK (type IN ('consultation', 'vaccination', 'deworming', 'prescription', 'procedure', 'follow_up', 'note'))");
        DB::statement('ALTER TABLE clinical_records ADD CONSTRAINT clinical_records_void_check CHECK ((voided_at IS NULL) = (void_reason IS NULL))');
        DB::statement('CREATE INDEX clinical_records_next_control ON clinical_records (clinic_id, next_control_at) WHERE next_control_at IS NOT NULL AND voided_at IS NULL');

        // Detail tables: id = clinical_records.id (composite FK keeps them in the same clinic).
        $detail = function (string $name, Closure $columns): void {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->uuid('id')->primary();
                $table->clinic();
                $columns($table);
                $table->timestampsTz();

                $table->foreign(['clinic_id', 'id'])->references(['clinic_id', 'id'])->on('clinical_records')->cascadeOnDelete();
            });
        };

        $detail('consultations', function (Blueprint $table) {
            $table->string('reason', 255);
            $table->text('subjective')->nullable();      // S: anamnesis
            $table->text('objective')->nullable();       // O: findings
            $table->text('assessment')->nullable();      // I: interpretation / diagnosis
            $table->text('plan_treatment')->nullable();  // P: therapeutic plan
            $table->text('plan_diagnostic')->nullable(); // P: diagnostic plan
        });

        $detail('vaccinations', function (Blueprint $table) {
            $table->foreignUuid('vaccine_id')->nullable()->constrained('vaccines')->restrictOnDelete();
            $table->string('vaccine_name', 120);
            $table->string('manufacturer', 80)->nullable();
            $table->string('lot_number', 60)->nullable();
            $table->date('lot_expires_on')->nullable();
            $table->unsignedTinyInteger('dose_number')->nullable();
            $table->string('route', 30)->nullable();
        });

        $detail('dewormings', function (Blueprint $table) {
            $table->string('kind', 10);
            $table->string('product_name', 120);
            $table->string('dose', 60)->nullable();
        });
        DB::statement("ALTER TABLE dewormings ADD CONSTRAINT dewormings_kind_check CHECK (kind IN ('internal', 'external', 'both'))");

        $detail('prescriptions', function (Blueprint $table) {
            $table->string('diagnosis', 255)->nullable();
            $table->text('instructions')->nullable();
        });

        $detail('procedures', function (Blueprint $table) {
            $table->foreignUuid('procedure_type_id')->nullable()->constrained('procedure_types')->restrictOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->text('premedication')->nullable();
            $table->text('anesthesia')->nullable();
            $table->text('complications')->nullable();
            $table->text('outcome')->nullable();
        });

        $detail('follow_ups', function (Blueprint $table) {
            $table->string('kind', 20);
            $table->string('reason', 255);
            $table->text('details')->nullable();
        });
        DB::statement("ALTER TABLE follow_ups ADD CONSTRAINT follow_ups_kind_check CHECK (kind IN ('phone_call', 'in_person', 'post_op', 'other'))");

        $detail('clinical_notes', function (Blueprint $table) {
            $table->text('body');
        });

        // N rows per record.
        $child = function (string $name, Closure $columns): void {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->uuid('id')->primary();
                $table->clinic();
                $table->tenantForeign('clinical_record_id', 'clinical_records', onDelete: 'cascade');
                $columns($table);
                $table->timestampsTz();
            });
        };

        $child('vital_signs', function (Blueprint $table) {
            $table->decimal('weight_kg', 7, 3)->nullable();
            $table->decimal('temperature_c', 4, 1)->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('capillary_refill_s', 3, 1)->nullable();
            $table->unsignedTinyInteger('spo2_pct')->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->unsignedSmallInteger('glucose_mg_dl')->nullable();
            $table->unsignedTinyInteger('body_condition_score')->nullable();
            $table->string('mucous_membranes', 15)->nullable();
            $table->string('hydration', 10)->nullable();
            $table->unsignedTinyInteger('pain_score')->nullable();
            $table->string('notes', 500)->nullable();

            $table->unique('clinical_record_id');
        });
        DB::statement('ALTER TABLE vital_signs ADD CONSTRAINT vital_signs_ranges_check CHECK (
            (weight_kg IS NULL OR weight_kg > 0)
            AND (temperature_c IS NULL OR temperature_c BETWEEN 25 AND 45)
            AND (body_condition_score IS NULL OR body_condition_score BETWEEN 1 AND 9)
            AND (pain_score IS NULL OR pain_score BETWEEN 0 AND 10)
            AND (spo2_pct IS NULL OR spo2_pct <= 100))');
        DB::statement("ALTER TABLE vital_signs ADD CONSTRAINT vital_signs_mucous_check CHECK (mucous_membranes IN ('pink', 'pale', 'cyanotic', 'icteric', 'hyperemic'))");
        DB::statement("ALTER TABLE vital_signs ADD CONSTRAINT vital_signs_hydration_check CHECK (hydration IN ('normal', 'mild', 'moderate', 'severe'))");

        $child('physical_exam_findings', function (Blueprint $table) {
            $table->string('system', 20);
            $table->string('status', 15);
            $table->string('notes', 1000)->nullable();

            $table->unique(['clinical_record_id', 'system']);
        });
        DB::statement("ALTER TABLE physical_exam_findings ADD CONSTRAINT physical_exam_findings_status_check CHECK (status IN ('not_evaluated', 'normal', 'abnormal'))");

        $child('consultation_diagnoses', function (Blueprint $table) {
            $table->string('kind', 15);
            $table->string('description', 255);
            $table->string('code', 30)->nullable(); // future: VeNom coding
            $table->unsignedSmallInteger('sort')->default(0);
        });
        DB::statement("ALTER TABLE consultation_diagnoses ADD CONSTRAINT consultation_diagnoses_kind_check CHECK (kind IN ('presumptive', 'differential', 'definitive'))");

        $child('prescription_items', function (Blueprint $table) {
            $table->string('medication', 150);
            $table->string('presentation', 100)->nullable();
            $table->string('dose', 100);
            $table->string('frequency', 100);
            $table->string('duration', 100)->nullable();
            $table->string('route', 50)->nullable();
            $table->string('quantity', 50)->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->string('attachable_type', 60);
            $table->uuid('attachable_id');
            $table->string('disk', 20);
            $table->string('path');
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('category', 20)->default('other');
            $table->boolean('visible_to_owner')->default(false);
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['attachable_type', 'attachable_id']);
        });
        DB::statement("ALTER TABLE attachments ADD CONSTRAINT attachments_category_check CHECK (category IN ('image', 'lab_result', 'imaging', 'document', 'other'))");
    }

    public function down(): void
    {
        foreach (['attachments', 'prescription_items', 'consultation_diagnoses', 'physical_exam_findings', 'vital_signs',
            'clinical_notes', 'follow_ups', 'procedures', 'prescriptions', 'dewormings', 'vaccinations', 'consultations', 'clinical_records'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
