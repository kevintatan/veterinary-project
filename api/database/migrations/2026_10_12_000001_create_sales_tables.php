<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5a · V2: sales and payments (docs/inventario.md, docs/database.md §8).
 * Not an electronic invoice: the receipt says so. Prices include IVA; the
 * tax is computed inside each line for reports.
 *
 * - sales: one account per customer visit. Status follows the money:
 *   open (nothing paid) → partially_paid → paid; void with reason. Numbered
 *   per clinic (unique), never deleted.
 * - sale_items: products (move stock), services or free lines, with a
 *   snapshot of description, price and IVA at the time of sale.
 * - payments: cash, card, transfer, Nequi, Daviplata… A wrong payment is
 *   voided with a reason, never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('branch_id', 'branches');
            $table->unsignedInteger('number');
            $table->tenantForeign('owner_id', 'owners', nullable: true); // null = counter sale
            $table->string('status', 15)->default('open');
            $table->decimal('subtotal', 14, 2)->default(0);      // before line discounts
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);     // IVA included in total
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->jsonb('customer_snapshot')->nullable();      // name, document, phone at the time of sale
            $table->string('notes', 500)->nullable();
            $table->foreignUuid('sold_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('sold_at');
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->foreignUuid('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['clinic_id', 'number']);
            $table->index(['clinic_id', 'status', 'sold_at']);
            $table->index(['clinic_id', 'sold_at']);
        });
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_status_check CHECK (status IN ('open', 'partially_paid', 'paid', 'void'))");
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_money_check CHECK (total >= 0 AND paid_total >= 0 AND discount_total >= 0)');

        Schema::create('sale_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('sale_id', 'sales', onDelete: 'cascade');
            $table->string('item_type', 10); // product, service, custom
            $table->tenantForeign('product_id', 'products', nullable: true);
            $table->tenantForeign('service_id', 'services', nullable: true);
            $table->string('description', 200);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);  // amount off this line
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0); // IVA inside total
            $table->decimal('total', 14, 2);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE sale_items ADD CONSTRAINT sale_items_type_check CHECK (item_type IN ('product', 'service', 'custom'))");
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_money_check CHECK (quantity > 0 AND unit_price >= 0 AND discount >= 0 AND total >= 0)');

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->tenantForeign('sale_id', 'sales');
            $table->string('method', 15);
            $table->decimal('amount', 14, 2);
            $table->string('reference', 100)->nullable();
            $table->foreignUuid('received_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('paid_at');
            $table->timestampTz('voided_at')->nullable();
            $table->foreignUuid('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->timestampsTz();

            $table->index(['clinic_id', 'paid_at']);
        });
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('cash', 'card', 'transfer', 'nequi', 'daviplata', 'other'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
