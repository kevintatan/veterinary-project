<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5a · V3: what a sale line came from (docs/inventario.md).
 * - appointment_id: "Cobrar" from an appointment (one charge per appointment).
 * - clinical_record_id: the vaccine / deworming / procedure that used the product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->tenantForeign('appointment_id', 'appointments', nullable: true);
            $table->tenantForeign('clinical_record_id', 'clinical_records', nullable: true);
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropForeign(['clinic_id', 'appointment_id']);
            $table->dropForeign(['clinic_id', 'clinical_record_id']);
            $table->dropColumn(['appointment_id', 'clinical_record_id']);
        });
    }
};
