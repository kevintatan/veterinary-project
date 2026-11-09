<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Sales\SalesReports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Money reports (docs/inventario.md, V4). The daily close is for whoever
 * charges; the sales report and the export are for the admin, from Pro.
 */
class ReportController extends Controller
{
    public function __construct(private readonly SalesReports $reports) {}

    public function dailyClose(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'branch_id' => ['nullable', 'uuid']]);
        $clinic = $this->clinic($request);

        return response()->json(['data' => $this->reports->dailyClose($clinic, $data['date'] ?? now($clinic->timezone)->toDateString(), $data['branch_id'] ?? null)]);
    }

    public function sales(Request $request): JsonResponse
    {
        $this->ensurePlan();
        $data = $this->period($request);

        return response()->json(['data' => $this->reports->period($this->clinic($request), $data['from'], $data['to'], $data['branch_id'] ?? null)]);
    }

    public function export(Request $request): Response
    {
        $this->ensurePlan();
        $data = $this->period($request) + $request->validate(['kind' => ['required', Rule::in(['items', 'payments'])]]);
        $csv = $this->reports->csv($this->clinic($request), $data['kind'], $data['from'], $data['to'], $data['branch_id'] ?? null);
        $name = ($data['kind'] === 'items' ? 'ventas' : 'pagos')."-{$data['from']}-a-{$data['to']}.csv";

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$name}\"", 'Cache-Control' => 'private, no-store']);
    }

    /** @return array{from: string, to: string, branch_id?: string|null} */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'uuid'],
        ], [], ['from' => 'desde', 'to' => 'hasta']);

        if (now()->parse($data['from'])->diffInDays(now()->parse($data['to'])) > 366) {
            abort(response()->json(['message' => 'El periodo puede ser de máximo un año.', 'errors' => ['to' => ['El periodo puede ser de máximo un año.']]], 422));
        }

        return $data;
    }

    private function ensurePlan(): void
    {
        abort_unless(Subscription::allows('advanced_reports'), 402, 'Los reportes de ventas y la exportación están disponibles desde el plan Pro.');
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
