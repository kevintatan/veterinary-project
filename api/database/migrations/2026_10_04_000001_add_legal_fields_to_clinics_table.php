<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legal identity for documents (PDF letterhead now, electronic invoicing later).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('legal_name', 200)->nullable()->after('name');
            $table->string('tax_id', 20)->nullable()->after('legal_name'); // NIT with verification digit, e.g. 901234567-8
            $table->string('address')->nullable()->after('phone');
            $table->string('website', 150)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['legal_name', 'tax_id', 'address', 'website']);
        });
    }
};
