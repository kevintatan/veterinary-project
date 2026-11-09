<?php

namespace App\Http\Controllers\Api\V1;

use App\Booking\OnlineBooking;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Catalogs\Species;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\Service;
use App\Models\User;
use App\Support\Phone;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public booking page /reservar/{clinic} (docs/reservas.md). No login.
 * Never says whether a phone is a client nor shows anyone's pets.
 */
class PublicBookingController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function show(string $clinicSlug): JsonResponse
    {
        return $this->inClinic($clinicSlug, function (Clinic $clinic) {
            $config = OnlineBooking::config($clinic);
            $branches = Branch::where('is_active', true)->orderBy('name')->get();

            return response()->json(['data' => [
                'clinic' => [
                    'name' => $clinic->name,
                    'slug' => $clinic->slug,
                    'has_logo' => $clinic->logo_path !== null,
                    'phone' => $clinic->phone,
                    'address' => $clinic->address,
                    'timezone' => $clinic->timezone,
                ],
                'message' => $config['message'],
                'max_days_ahead' => $config['max_days_ahead'],
                'choose_professional' => $config['choose_professional'],
                'branches' => $branches->map(fn (Branch $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'address' => $b->address,
                    'professionals' => $config['choose_professional'] ? $this->professionals($b) : [],
                ]),
                'services' => Service::where('is_active', true)->where('is_bookable_online', true)->orderBy('category')->orderBy('name')->get()
                    ->map(fn (Service $s) => ['id' => $s->id, 'name' => $s->name, 'category' => $s->category, 'duration_minutes' => $s->duration_minutes, 'price' => $s->price === null ? null : (int) $s->price]),
                'document_types' => collect(OnlineBooking::DOCUMENT_TYPES)->map(fn (string $t) => ['value' => $t, 'label' => DocumentType::from($t)->label()]),
                'species' => Species::where('is_active', true)->orderBy('sort')->orderBy('name')->get(['id', 'name']),
            ]]);
        });
    }

    public function logo(string $clinicSlug): StreamedResponse
    {
        $clinic = Clinic::where('slug', $clinicSlug)->firstOrFail();
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        abort_if($clinic->logo_path === null || ! $disk->exists($clinic->logo_path), 404);

        return $disk->response($clinic->logo_path, null, ['Cache-Control' => 'public, max-age=86400']);
    }

    public function slots(Request $request, string $clinicSlug, OnlineBooking $booking): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'uuid'],
            'service_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'user_id' => ['nullable', 'uuid'],
        ]);

        return $this->inClinic($clinicSlug, function (Clinic $clinic) use ($data, $booking) {
            $branch = Branch::where('is_active', true)->findOrFail($data['branch_id']);
            $service = Service::where('is_active', true)->where('is_bookable_online', true)->findOrFail($data['service_id']);

            // The public page only needs the times, not who is free.
            return response()->json(['data' => array_map(
                fn ($s) => $s['starts_at'],
                $booking->slots($clinic, $branch, $service, $data['date'], $data['user_id'] ?? null),
            )]);
        });
    }

    public function store(Request $request, string $clinicSlug, OnlineBooking $booking): JsonResponse
    {
        $request->merge([
            'phone' => Phone::normalize((string) $request->input('phone')) ?? $request->input('phone'),
            'document_number' => strtoupper((string) preg_replace('/[\s.\-]/', '', (string) $request->input('document_number'))),
        ]);
        $data = $request->validate([
            'branch_id' => ['required', 'uuid'],
            'service_id' => ['required', 'uuid'],
            'starts_at' => ['required', 'string', 'max:40'],
            'user_id' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'phone' => ['required', 'string', fn ($a, $v, $fail) => Phone::isColombianMobile($v) ?: $fail('Escribe un celular colombiano válido (10 dígitos).')],
            // Identifies the tutor more reliably than the phone (docs/reservas.md).
            'document_type' => ['required', Rule::in(OnlineBooking::DOCUMENT_TYPES)],
            'document_number' => ['required', 'string', 'regex:/^[A-Z0-9]{4,15}$/'],
            'pet_name' => ['required', 'string', 'max:60'],
            'species_id' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:500'],
            'accept_data' => ['accepted'],
            'accept_whatsapp' => ['accepted'],
        ], [
            'accept_data.accepted' => 'Debes autorizar el tratamiento de tus datos para pedir la cita.',
            'accept_whatsapp.accepted' => 'Debes autorizar que la clínica te escriba por WhatsApp sobre esta cita.',
            'document_number.regex' => 'Escribe el número de documento sin puntos ni espacios.',
        ], trans('fields'));

        return $this->inClinic($clinicSlug, function (Clinic $clinic) use ($data, $booking) {
            $species = Species::where('is_active', true)->findOrFail($data['species_id']);
            $appointment = $booking->request($clinic, [...$data, 'species_name' => $species->name]);

            return response()->json(['data' => [
                'id' => $appointment->id,
                'starts_at' => $appointment->starts_at->setTimezone($clinic->timezone)->toIso8601String(),
            ]], 201);
        });
    }

    /** @return list<array{id: string, name: string}> */
    private function professionals(Branch $branch): array
    {
        $ids = ClinicMembership::where('status', ClinicMembership::STATUS_ACTIVE)->where('shows_in_agenda', true)->pluck('user_id');

        return User::whereIn('id', $ids)->whereHas('branches', fn ($q) => $q->where('branches.id', $branch->id))->orderBy('name')
            ->get(['id', 'name'])->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->all();
    }

    /** Runs inside the clinic; 404 if it doesn't exist or doesn't take online bookings. */
    private function inClinic(string $slug, callable $fn): mixed
    {
        $clinic = Clinic::where('slug', $slug)->where('status', Clinic::STATUS_ACTIVE)->first();
        abort_if($clinic === null, 404, 'Clínica no encontrada.');

        return $this->tenant->runAs($clinic->id, function () use ($clinic, $fn) {
            abort_unless(OnlineBooking::isOpen($clinic), 404, 'Esta clínica no está recibiendo reservas en línea.');

            return $fn($clinic);
        });
    }
}
