<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform table: commercial plans shared by every clinic.
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->decimal('price_monthly', 14, 2);
            $table->decimal('price_yearly', 14, 2)->nullable();
            $table->jsonb('features')->default('{}');
            $table->jsonb('limits')->default('{}');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
