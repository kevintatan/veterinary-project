<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The only way to join a clinic (docs/database.md §3, safeguard 7).
        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->string('email');
            $table->string('role', 40);
            $table->jsonb('branch_ids')->nullable(); // null = every active branch
            $table->string('token_hash', 64)->unique();
            $table->foreignUuid('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->foreignUuid('accepted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['clinic_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
