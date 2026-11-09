<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consents and authorizations signed on screen (Documentos y firmas).
 *
 * The document freezes its text when created (snapshot of clinic, owner, pet,
 * vet and the rendered paragraphs); the first signature locks it and records
 * its content hash; when every required signer has signed, the PDF is
 * generated once, hashed and kept. Never deleted: voided with a reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('pet_id', 'pets');
            $table->tenantForeign('owner_id', 'owners');
            $table->string('type', 40);                       // DocumentTypes code
            $table->unsignedSmallInteger('template_version');
            $table->string('title', 200);
            $table->jsonb('data');                            // case fields
            $table->jsonb('snapshot');                        // clinic/owner/pet/vet values + rendered blocks
            $table->string('status', 20)->default('draft');   // draft, signing, signed, voided
            $table->uuid('vet_id');                           // responsible vet (signs as "vet")
            $table->string('content_hash', 64)->nullable();   // sha256 of what was signed (set at 1st signature)
            $table->string('pdf_path')->nullable();
            $table->string('pdf_sha256', 64)->nullable();
            $table->timestampTz('signed_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->foreignUuid('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->tenantKey();
            $table->foreign(['clinic_id', 'vet_id'])->references(['clinic_id', 'user_id'])->on('clinic_user')->restrictOnDelete();
            $table->index(['clinic_id', 'pet_id', 'created_at']);
        });

        Schema::create('pet_document_signatures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('pet_document_id', 'pet_documents', onDelete: 'cascade');
            $table->string('role', 20);                       // owner, vet, witness
            $table->string('signer_name', 150);
            $table->string('signer_document', 40)->nullable();
            $table->foreignUuid('signer_user_id')->nullable()->constrained('users')->nullOnDelete(); // vet
            $table->string('image_path');                     // PNG of the stroke
            $table->string('method', 20)->default('in_person'); // in_person, remote (D3)
            $table->string('content_hash', 64);               // what this person signed
            $table->string('ip', 45)->nullable();             // evidence (Ley 527: reliability of the signature)
            $table->string('user_agent', 300)->nullable();
            $table->foreignUuid('collected_by')->nullable()->constrained('users')->nullOnDelete(); // staff session where it was signed
            $table->timestampTz('signed_at');
            $table->timestampsTz();

            $table->unique(['pet_document_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_document_signatures');
        Schema::dropIfExists('pet_documents');
    }
};
