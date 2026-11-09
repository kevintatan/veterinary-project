<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('slug', 80)->unique();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('timezone', 40)->default('America/Bogota');
            $table->char('currency', 3)->default('COP');
            $table->string('status', 20)->default('active');
            $table->jsonb('settings')->default('{}');
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE clinics ADD CONSTRAINT clinics_status_check CHECK (status IN ('active', 'suspended'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};
