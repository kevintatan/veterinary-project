<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->string('name', 120);
            $table->string('address')->nullable();
            $table->string('municipality_code', 10)->nullable();
            $table->string('phone', 20)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->tenantKey();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
