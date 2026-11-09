<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Design chosen for the pet's printed card (template, color, what to show),
 * so a reprint looks the same. Null = the clinic's default design.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->jsonb('card_design')->nullable()->after('public_contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn('card_design');
        });
    }
};
