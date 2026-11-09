<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Clinical\SaveClinicalRecord;
use App\Clinical\ClinicalPdf;
use App\Clinical\RecordAccess;
use App\Clinical\RecordTypes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\ClinicalRecordRequest;
use App\Http\Resources\ClinicalRecordResource;
use App\Models\Attachment;
use App\Models\Clinic;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Pet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClinicalRecordController extends Controller
{
    private const BASE_RELATIONS = ['author', 'voider'];

    /**
     * Pet timeline: every record, newest first, with counts per type.
     */
    public function index(Request $request, Pet $pet): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(RecordTypes::keys())],
            'include_voided' => ['sometimes', 'boolean'],
        ], [], trans('fields'));

        $records = ClinicalRecord::query()
            ->where('pet_id', $pet->id)
            ->with([...self::BASE_RELATIONS, ...RecordTypes::allRelations()])
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when(! $request->boolean('include_voided', true), fn ($q) => $q->notVoided())
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $counts = ClinicalRecord::query()
            ->where('pet_id', $pet->id)
            ->notVoided()
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        return ClinicalRecordResource::collection($records)->additional(['meta' => [
            'counts' => collect(ClinicalRecord::TYPES)->map(fn ($label, $key) => ['label' => $label, 'total' => (int) ($counts[$key] ?? 0)]),
        ]]);
    }

    public function store(ClinicalRecordRequest $request, Pet $pet, SaveClinicalRecord $save): JsonResponse
    {
        $record = $save($this->clinic($request), $request->user(), $pet, $request->validated());

        return $this->detail($record)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(ClinicalRecord $record): ClinicalRecordResource
    {
        return $this->detail($record);
    }

    public function update(ClinicalRecordRequest $request, ClinicalRecord $record, SaveClinicalRecord $save, RecordAccess $access): ClinicalRecordResource
    {
        $clinic = $this->clinic($request);

        abort_unless($access->canEdit($request->user(), $record, $clinic), Response::HTTP_FORBIDDEN, $access->denyEditReason($request->user(), $record, $clinic));

        return $this->detail($save($clinic, $request->user(), $record->pet, $request->validated(), $record));
    }

    /**
     * Records are never deleted (D8): voiding keeps them visible, struck through.
     */
    public function void(Request $request, ClinicalRecord $record, RecordAccess $access): ClinicalRecordResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo']);
        $clinic = $this->clinic($request);

        abort_unless($access->canVoid($request->user(), $record, $clinic), Response::HTTP_FORBIDDEN, $record->isVoided()
            ? 'El registro ya está anulado.'
            : 'Solo el autor (dentro de la ventana de edición) o un administrador puede anular el registro.');

        $record->forceFill([
            'voided_at' => now(),
            'voided_by' => $request->user()->id,
            'void_reason' => $data['reason'],
        ])->save();

        return $this->detail($record);
    }

    // ---- Attachments ------------------------------------------------------------

    /**
     * Adding files is allowed even after the lock (lab results arrive later),
     * but never on voided records.
     */
    public function storeAttachment(Request $request, ClinicalRecord $record): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.Attachment::MAX_KILOBYTES, 'mimes:'.implode(',', Attachment::ALLOWED_MIMES)],
            'category' => ['sometimes', Rule::in(Attachment::CATEGORIES)],
            'visible_to_owner' => ['sometimes', 'boolean'],
        ], [], ['file' => 'archivo', 'category' => 'categoría']);

        abort_if($record->isVoided(), Response::HTTP_FORBIDDEN, 'No se pueden adjuntar archivos a un registro anulado.');

        $file = $data['file'];
        $disk = (string) config('filesystems.attachments_disk');
        $path = $file->storeAs(
            "clinics/{$record->clinic_id}/clinical-records/{$record->id}",
            Str::uuid7().'.'.strtolower($file->getClientOriginalExtension()),
            $disk,
        );

        $attachment = $record->attachments()->create([
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'category' => $data['category'] ?? (str_starts_with((string) $file->getMimeType(), 'image/') ? 'image' : 'document'),
            'visible_to_owner' => $data['visible_to_owner'] ?? false,
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $attachment->only(['id', 'original_name', 'mime_type', 'size_bytes', 'category', 'visible_to_owner'])], Response::HTTP_CREATED);
    }

    public function downloadAttachment(Attachment $attachment): StreamedResponse
    {
        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroyAttachment(Request $request, Attachment $attachment, RecordAccess $access): Response
    {
        $record = $attachment->attachable;

        abort_unless(
            $record instanceof ClinicalRecord && $access->canEdit($request->user(), $record, $this->clinic($request)),
            Response::HTTP_FORBIDDEN,
            'No puedes eliminar este adjunto.',
        );

        $attachment->delete(); // soft delete: the file stays for the audit trail

        return response()->noContent();
    }

    // ---- Helpers ----------------------------------------------------------------

    /**
     * Whole history (optionally a period and some types) as a printable PDF.
     */
    public function historyPdf(Request $request, Pet $pet, ClinicalPdf $pdf): SymfonyResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'types' => ['nullable', 'array'],
            'types.*' => [Rule::in(RecordTypes::keys())],
            'include_voided' => ['sometimes', 'boolean'],
        ], [], trans('fields'));

        $clinic = $this->clinic($request);
        $from = isset($filters['from']) ? Carbon::parse($filters['from'], $clinic->timezone)->startOfDay() : null;
        $to = isset($filters['to']) ? Carbon::parse($filters['to'], $clinic->timezone)->endOfDay() : null;

        $records = ClinicalRecord::query()
            ->where('pet_id', $pet->id)
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from->utc()))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to->utc()))
            ->when($filters['types'] ?? null, fn ($q, $types) => $q->whereIn('type', $types))
            ->when(! $request->boolean('include_voided', true), fn ($q) => $q->notVoided())
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $period = match (true) {
            $from && $to => 'Del '.$from->format('d/m/Y').' al '.$to->format('d/m/Y'),
            (bool) $from => 'Desde el '.$from->format('d/m/Y'),
            (bool) $to => 'Hasta el '.$to->format('d/m/Y'),
            default => null,
        };

        return $pdf->render($clinic, $request->user(), $pet, $records, 'Historia clínica', $period);
    }

    /**
     * One record (a prescription to print, a surgery summary…) as a PDF.
     */
    public function recordPdf(Request $request, ClinicalRecord $record, ClinicalPdf $pdf): SymfonyResponse
    {
        $title = $record->type === 'prescription' ? 'Fórmula médica' : $record->typeLabel();

        return $pdf->render($this->clinic($request), $request->user(), $record->pet, $record->newCollection([$record]), $title);
    }

    private function detail(ClinicalRecord $record): ClinicalRecordResource
    {
        $record->load([...self::BASE_RELATIONS, ...RecordTypes::for($record->type)->relations(), 'attachments']);

        return (new ClinicalRecordResource($record))->withDetail();
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
