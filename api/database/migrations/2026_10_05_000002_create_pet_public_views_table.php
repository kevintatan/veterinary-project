<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scans of a pet's public card (QR), docs/database.md §10b: "your pet was
 * scanned 10 min ago" in lost mode, and abuse detection. No raw IPs stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_public_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('pet_id', 'pets', onDelete: 'cascade');
            $table->boolean('in_lost_mode')->default(false);
            $table->string('ip_hash', 64); // sha256(ip + app key): counts unique visitors without storing the IP
            $table->string('user_agent', 255)->nullable();
            $table->timestampTz('viewed_at');

            $table->index(['pet_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_public_views');
    }
};
