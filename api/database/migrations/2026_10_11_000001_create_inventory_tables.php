<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5a · V1: products and inventory (docs/inventario.md, docs/database.md §8).
 *
 * - products: what the clinic sells or uses. Prices include IVA (tax_rate is
 *   informative, for reports).
 * - inventory_stocks: current quantity per branch, updated in the same
 *   transaction as each movement. May go negative (sell/apply first, register
 *   the purchase later): it is flagged, not blocked.
 * - inventory_movements: append-only ledger (purchase, adjustment, transfers…);
 *   stock can always be rebuilt from it. lot_closed_at is the only field that
 *   changes later: it hides an expiry alert once the lot was handled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->string('name', 150);
            $table->string('name_key', 150); // lowercased in PHP (accents too): duplicate check
            $table->string('sku', 60)->nullable();
            $table->string('kind', 15)->default('product');
            $table->string('unit', 15)->default('unit');
            $table->decimal('sale_price', 14, 2)->default(0);
            $table->decimal('cost_price', 14, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('tracks_stock')->default(true);
            $table->decimal('min_stock', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['clinic_id', 'is_active', 'name']);
        });
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_kind_check CHECK (kind IN ('product', 'medication', 'vaccine', 'supply'))");
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_unit_check CHECK (unit IN ('unit', 'box', 'ml', 'tablet', 'dose', 'g', 'kg', 'bottle', 'pipette'))");
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_money_check CHECK (sale_price >= 0 AND (cost_price IS NULL OR cost_price >= 0) AND tax_rate >= 0 AND tax_rate <= 100 AND min_stock >= 0)');
        DB::statement('CREATE UNIQUE INDEX products_sku_unique ON products (clinic_id, sku) WHERE sku IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX products_name_unique ON products (clinic_id, name_key)');

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('branch_id', 'branches');
            $table->tenantForeign('product_id', 'products');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->timestampsTz();

            $table->unique(['branch_id', 'product_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('branch_id', 'branches');
            $table->tenantForeign('product_id', 'products');
            $table->string('type', 20);
            $table->decimal('quantity', 12, 3);       // signed: + in, - out
            $table->decimal('balance_after', 12, 3);  // branch stock right after this movement
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->string('lot_number', 60)->nullable();
            $table->date('expires_on')->nullable();
            $table->string('reason', 20)->nullable(); // adjustments: count, damage, loss, expired, other
            $table->string('note', 255)->nullable();
            $table->uuid('transfer_group')->nullable(); // the out/in pair of a transfer between branches
            $table->string('reference_type', 60)->nullable(); // sale, clinical_record… (V2/V3)
            $table->uuid('reference_id')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('occurred_at');
            $table->timestampTz('lot_closed_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['clinic_id', 'product_id', 'occurred_at']);
            $table->index(['clinic_id', 'expires_on']);
            $table->index(['reference_type', 'reference_id']);
        });
        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_type_check CHECK (type IN ('initial', 'purchase', 'adjustment', 'transfer_out', 'transfer_in', 'sale', 'sale_void', 'clinical_use'))");
        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_reason_check CHECK (reason IS NULL OR reason IN ('count', 'damage', 'loss', 'expired', 'other'))");
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_quantity_check CHECK (quantity <> 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('products');
    }
};
