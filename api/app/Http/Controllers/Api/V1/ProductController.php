<?php

namespace App\Http\Controllers\Api\V1;

use App\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Inventory\Inventory;
use App\Models\Branch;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Products and their stock (docs/inventario.md). Costs only for inventory.adjust. */
class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'uuid'],
            'q' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(array_keys(Product::KINDS))],
            'status' => ['nullable', Rule::in(['low', 'inactive'])],
        ]);
        $branchId = $data['branch_id'] ?? Branch::where('is_active', true)->orderBy('created_at')->value('id');
        $stocks = InventoryStock::where('branch_id', $branchId)->pluck('quantity', 'product_id');
        $totals = InventoryStock::selectRaw('product_id, sum(quantity) as total')->groupBy('product_id')->pluck('total', 'product_id');
        $showCosts = $this->canSeeCosts($request);

        $products = Product::query()
            ->where('is_active', ($data['status'] ?? null) !== 'inactive')
            ->when($data['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'ilike', '%'.addcslashes($term, '%_\\').'%')->orWhere('sku', 'ilike', addcslashes($term, '%_\\').'%')))
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => $this->row($p, (float) ($stocks[$p->id] ?? 0), (float) ($totals[$p->id] ?? 0), $showCosts, $stocks->has($p->id)));

        if (($data['status'] ?? null) === 'low') {
            $products = $products->filter(fn ($p) => in_array($p['stock_status'], ['low', 'out', 'negative'], true))->values();
        }

        return response()->json(['data' => $products->values(), 'meta' => ['branch_id' => $branchId, 'show_costs' => $showCosts]]);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $showCosts = $this->canSeeCosts($request);
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $stocks = InventoryStock::where('product_id', $product->id)->pluck('quantity', 'branch_id');

        return response()->json(['data' => [
            ...$this->row($product, 0, (float) $stocks->sum(), $showCosts),
            'stocks' => $branches->map(fn (Branch $b) => ['branch' => $b->only(['id', 'name']), 'quantity' => (float) ($stocks[$b->id] ?? 0), 'handled' => $stocks->has($b->id)]),
            'movements' => InventoryMovement::with(['branch:id,name', 'user:id,name'])
                ->where('product_id', $product->id)
                ->latest('occurred_at')->orderByDesc('id')->limit(100)->get()
                ->map(fn (InventoryMovement $m) => [
                    'id' => $m->id,
                    'type' => ['value' => $m->type, 'label' => InventoryMovement::TYPES[$m->type]],
                    'reason' => $m->reason === null ? null : ['value' => $m->reason, 'label' => InventoryMovement::REASONS[$m->reason]],
                    'quantity' => (float) $m->quantity,
                    'balance_after' => (float) $m->balance_after,
                    'unit_cost' => $showCosts && $m->unit_cost !== null ? (float) $m->unit_cost : null,
                    'lot_number' => $m->lot_number,
                    'expires_on' => $m->expires_on?->toDateString(),
                    'note' => $m->note,
                    'branch' => $m->branch?->only(['id', 'name']),
                    'user' => $m->user?->only(['id', 'name']),
                    'occurred_at' => $m->occurred_at->toIso8601String(),
                ]),
        ]]);
    }

    public function store(Request $request, Inventory $inventory): JsonResponse
    {
        $data = $this->validated($request);
        $initial = $request->validate([
            'initial_quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'branch_id' => ['required_with:initial_quantity', 'nullable', 'uuid'],
            'lot_number' => ['nullable', 'string', 'max:60'],
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $product = DB::transaction(function () use ($data, $initial, $request, $inventory) {
            $product = Product::create($data);

            if (! empty($initial['initial_quantity']) && $product->tracks_stock) {
                $inventory->initial($product, Branch::findOrFail($initial['branch_id']), (string) $initial['initial_quantity'], $request->user(), [
                    'unit_cost' => $data['cost_price'] ?? null,
                    'lot_number' => $initial['lot_number'] ?? null,
                    'expires_on' => $initial['expires_on'] ?? null,
                ]);
            }

            return $product;
        });

        return $this->show($request, $product)->setStatusCode(201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $this->validated($request, $product);

        // Turning off stock control with stock on hand would hide units: count them out first.
        if (($data['tracks_stock'] ?? true) === false && $product->tracks_stock && InventoryStock::where('product_id', $product->id)->where('quantity', '<>', 0)->exists()) {
            return response()->json(['message' => 'Este producto tiene existencias. Ajústalas a cero antes de dejar de controlarlas.', 'errors' => ['tracks_stock' => ['Tiene existencias: ajústalas a cero primero.']]], 422);
        }

        $product->update($data);

        return $this->show($request, $product);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Product $product = null): array
    {
        $request->merge(['sku' => $request->filled('sku') ? strtoupper(trim((string) $request->input('sku'))) : null]);
        $canCost = $this->canSeeCosts($request);

        $data = $request->validate([
            // Same rule as the database index: names are unique ignoring case.
            'name' => ['required', 'string', 'max:150', fn ($a, $v, $fail) => Product::where('name_key', Product::nameKey((string) $v))
                ->when($product, fn ($q) => $q->whereKeyNot($product->id))->exists() && $fail('Ya existe un producto con ese nombre.')],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('products', 'sku')->where('clinic_id', $request->attributes->get('clinic')->id)->ignore($product?->id)],
            'kind' => ['required', Rule::in(array_keys(Product::KINDS))],
            'unit' => ['required', Rule::in(array_keys(Product::UNITS))],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'tax_rate' => ['required', 'numeric', Rule::in([0, 5, 19])],
            'tracks_stock' => ['required', 'boolean'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'sku.unique' => 'Ya existe un producto con ese código.',
        ], ['sale_price' => 'precio de venta', 'cost_price' => 'costo', 'tax_rate' => 'IVA', 'min_stock' => 'existencia mínima', 'sku' => 'código']);

        $data['min_stock'] ??= 0;
        if (! $canCost) {
            unset($data['cost_price']); // only the admin sets costs
        }

        return $data;
    }

    /** @return array<string, mixed> */
    /** $handled: the branch has ever had this product (else it isn't "agotado" there, just not carried). */
    private function row(Product $p, float $stock, float $total, bool $showCosts, bool $handled = true): array
    {
        $min = (float) $p->min_stock;

        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'kind' => ['value' => $p->kind, 'label' => Product::KINDS[$p->kind]],
            'unit' => ['value' => $p->unit, 'label' => Product::UNITS[$p->unit]],
            'sale_price' => (float) $p->sale_price,
            'cost_price' => $showCosts && $p->cost_price !== null ? (float) $p->cost_price : null,
            'tax_rate' => (float) $p->tax_rate,
            'tracks_stock' => $p->tracks_stock,
            'min_stock' => $min,
            'is_active' => $p->is_active,
            'stock' => $stock,
            'stock_total' => $total,
            'stock_status' => ! $p->tracks_stock ? 'untracked' : (! $handled ? 'none' : ($stock < 0 ? 'negative' : ($stock == 0 ? 'out' : ($min > 0 && $stock <= $min ? 'low' : 'ok')))),
        ];
    }

    private function canSeeCosts(Request $request): bool
    {
        return $request->user()->hasPermissionTo(Permission::InventoryAdjust->value);
    }
}
