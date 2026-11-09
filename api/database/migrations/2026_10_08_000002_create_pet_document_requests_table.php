<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remote signing (Documentos y firmas, D3): a one-use link the clinic sends to
 * the owner. Only the sha256 of the token is stored. The owner also proves
 * who they are with the last 4 digits of their ID document (5 attempts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_document_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('pet_document_id', 'pet_documents', onDelete: 'cascade');
            $table->string('role', 20)->default('owner');
            $table->string('token_hash', 64)->unique();
            $table->string('channel', 20);                    // whatsapp, email, link
            $table->timestampTz('expires_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_document_requests');
    }
};
