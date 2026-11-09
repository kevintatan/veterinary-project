<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner portal (Fase 4e, docs/fase-4.md "Acceso del tutor").
 *
 * The person who signs in is a platform account (one per phone), linked to
 * the `owners` record of each clinic that invited them. Access is created
 * ONCE through a clinic's invitation link; then the session stays open.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Platform table (no clinic_id): the same person can be an owner in several clinics.
        Schema::create('tutor_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('email', 255)->unique();     // login (lowercase)
            $table->string('phone', 20)->unique();      // E.164, taken from the invitation's owner: it's the identity
            $table->string('password');
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('tutor_account_owner', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->foreignUuid('tutor_account_id')->constrained('tutor_accounts')->cascadeOnDelete();
            $table->tenantForeign('owner_id', 'owners', onDelete: 'cascade');
            $table->string('link_method', 30)->default('invitation');
            $table->timestampTz('linked_at');
            $table->timestampsTz();

            $table->unique(['clinic_id', 'owner_id']); // an owner record belongs to one account
        });

        Schema::create('tutor_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('owner_id', 'owners', onDelete: 'cascade');
            $table->string('token_hash', 64)->unique(); // sha256 of the link token; the token itself is never stored
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->foreignUuid('tutor_account_id')->nullable()->constrained('tutor_accounts')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_invitations');
        Schema::dropIfExists('tutor_account_owner');
        Schema::dropIfExists('tutor_accounts');
    }
};
