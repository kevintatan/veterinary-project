<?php

namespace App\Http\Controllers\Api\V1;

use App\Booking\BookingRequests;
use App\Booking\OnlineBooking;
use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Support\ClinicTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Online booking from the clinic's side: settings and requests (docs/reservas.md). */
class BookingRequestController extends Controller
{
    public function index(Request $request, BookingRequests $requests): JsonResponse
    {
        $clinic = $this->clinic($request);

        $pending = Appointment::with(['service:id,name', 'branch:id,name', 'users:id,name'])
            ->where('status', AppointmentStatus::Requested)
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'data' => $pending->map(fn (Appointment $a) => [
                'id' => $a->id,
                'starts_at' => ClinicTime::iso($a->starts_at, $clinic),
                'ends_at' => ClinicTime::iso($a->ends_at, $clinic),
                'is_past' => $a->starts_at->isPast(),
                'service' => $a->service?->only(['id', 'name']),
                'branch' => $a->branch?->only(['id', 'name']),
                'professional' => $a->users->first()?->only(['id', 'name']),
                'details' => collect($a->booking_details)->only(['name', 'phone', 'document_type', 'document_number', 'pet_name', 'species_name', 'notes', 'professional_chosen', 'requested_at']),
                'suggestions' => $requests->suggestions($a),
            ]),
        ]);
    }

    public function count(): JsonResponse
    {
        return response()->json(['data' => ['pending' => Appointment::where('status', AppointmentStatus::Requested)->where('starts_at', '>', now())->count()]]);
    }

    public function accept(Request $request, Appointment $appointment, BookingRequests $requests): JsonResponse
    {
        $data = $request->validate([
            // Empty = new tutor and pet from the request. A pet always comes with its tutor.
            'owner_id' => ['nullable', 'uuid', 'required_with:pet_id'],
            'pet_id' => ['nullable', 'uuid'],
            // Linked tutor with another phone: replace it with the one they just gave (reminders go there).
            'update_phone' => ['sometimes', 'boolean'],
        ]);

        $requests->accept($appointment, $data, $request->user());

        return response()->json(['data' => ['id' => $appointment->id, 'status' => $appointment->refresh()->status->value]]);
    }

    public function reject(Request $request, Appointment $appointment, BookingRequests $requests): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'motivo']);

        $requests->reject($appointment, $data['reason'], $request->user());

        return response()->json(['data' => ['id' => $appointment->id, 'status' => 'cancelled']]);
    }

    public function settings(Request $request): JsonResponse
    {
        $clinic = $this->clinic($request);

        return response()->json(['data' => [
            ...OnlineBooking::config($clinic),
            'included_in_plan' => Subscription::current()?->plan->hasFeature('online_booking') ?? false,
            'public_path' => '/reservar/'.$clinic->slug,
            'public_url' => OnlineBooking::publicUrl($clinic),
        ]]);
    }

    public function qr(Request $request): Response
    {
        return response(OnlineBooking::qrPng($this->clinic($request)), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=300']);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'min_notice_hours' => ['required', 'integer', Rule::in([0, 1, 2, 4, 12, 24, 48])],
            'max_days_ahead' => ['required', 'integer', 'min:1', 'max:90'],
            'choose_professional' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:300'],
        ], [], ['min_notice_hours' => 'anticipación mínima', 'max_days_ahead' => 'días hacia adelante', 'message' => 'mensaje']);

        $clinic = $this->clinic($request);
        $clinic->update(['settings' => array_merge($clinic->settings ?? [], ['online_booking' => $data])]);

        return $this->settings($request);
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
