<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->foreignUuid('plan_id')->index()->constrained('plans')->restrictOnDelete();
            $table->string('status', 20);
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('current_period_start')->nullable();
            $table->timestampTz('current_period_end')->nullable();
            $table->timestampTz('canceled_at')->nullable();
            $table->string('billing_provider', 30)->nullable();
            $table->string('billing_reference')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_status_check CHECK (status IN ('trialing', 'active', 'past_due', 'canceled', 'expired'))");
        // History is allowed, but only one subscription can be in force per clinic.
        DB::statement("CREATE UNIQUE INDEX subscriptions_one_current_per_clinic ON subscriptions (clinic_id) WHERE status IN ('trialing', 'active', 'past_due')");
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
