<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_user', function (Blueprint $table) {
            $table->clinic();
            $table->tenantForeign('branch_id', 'branches', onDelete: 'cascade');
            $table->uuid('user_id')->index();
            $table->timestampsTz();

            $table->primary(['branch_id', 'user_id']);
            // The user must be a member of the same clinic.
            $table->foreign(['clinic_id', 'user_id'])
                ->references(['clinic_id', 'user_id'])
                ->on('clinic_user')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_user');
    }
};
