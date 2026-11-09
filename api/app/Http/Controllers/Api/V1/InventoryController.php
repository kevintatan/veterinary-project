<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Inventory\Inventory;
use App\Inventory\InventoryAlerts;
use App\Inventory\ProductImport;
use App\Models\Branch;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Stock movements, alerts and import (docs/inventario.md). */
class InventoryController extends Controller
{
    public function __construct(private readonly Inventory $inventory) {}

    public function purchase(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'uuid'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lot_number' => ['nullable', 'string', 'max:60'],
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['quantity' => 'cantidad', 'unit_cost' => 'costo unitario', 'lot_number' => 'lote', 'expires_on' => 'vencimiento']);

        $this->inventory->purchase($product, $this->branch($data['branch_id']), (string) $data['quantity'], $request->user(), $data);

        return response()->json(['ok' => true], 201);
    }

    public function adjust(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'uuid'],
            'reason' => ['required', Rule::in(array_keys(InventoryMovement::REASONS))],
            'quantity' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'note' => ['nullable', 'string', 'max:255', 'required_if:reason,other'],
            'lot_movement_id' => ['nullable', 'uuid'],
        ], ['note.required_if' => 'Describe el motivo del ajuste.'], ['quantity' => 'cantidad', 'reason' => 'motivo', 'note' => 'nota']);

        $this->inventory->adjust($product, $this->branch($data['branch_id']), $data['reason'], (string) $data['quantity'], $request->user(), $data);

        return response()->json(['ok' => true], 201);
    }

    public function transfer(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'uuid'],
            'to_branch_id' => ['required', 'uuid'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['quantity' => 'cantidad', 'to_branch_id' => 'sede de destino']);

        $this->inventory->transfer($product, $this->branch($data['branch_id']), $this->branch($data['to_branch_id']), (string) $data['quantity'], $request->user(), $data['note'] ?? null);

        return response()->json(['ok' => true], 201);
    }

    public function alerts(Request $request, InventoryAlerts $alerts): JsonResponse
    {
        $branchId = $request->validate(['branch_id' => ['nullable', 'uuid']])['branch_id'] ?? Branch::where('is_active', true)->orderBy('created_at')->value('id');

        return response()->json(['data' => $alerts->for($branchId)]);
    }

    /** "Ya revisé este lote": hides its expiry alert. */
    public function closeLot(InventoryMovement $movement): JsonResponse
    {
        $this->inventory->closeLot($movement->id);

        return response()->json(['ok' => true]);
    }

    public function import(Request $request, ProductImport $import): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
            'branch_id' => ['required', 'uuid'],
        ], ['file.mimes' => 'Sube el archivo en formato CSV (en Excel: Archivo → Guardar como → CSV).'], ['file' => 'archivo']);

        return response()->json(['data' => $import($data['file']->getRealPath(), $this->branch($data['branch_id']), $request->user())]);
    }

    /** Template to fill in Excel and save as CSV. */
    public function template(): Response
    {
        $rows = [
            ProductImport::HEADERS,
            ['Vacuna múltiple canina', 'VAC-MULT', 'vacuna', 'dosis', '45000', '22000', '0', '5', '12'],
            ['NexGard 10-25 kg', '', 'medicamento', 'tableta', '68000', '', '19', '10', '30'],
        ];
        // BOM + ";" so Excel in Spanish opens it with accents and columns right.
        $csv = "\xEF\xBB\xBF".collect($rows)->map(fn ($r) => implode(';', $r))->implode("\r\n")."\r\n";

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="plantilla-productos.csv"']);
    }

    private function branch(string $id): Branch
    {
        return Branch::where('is_active', true)->findOrFail($id);
    }
}
