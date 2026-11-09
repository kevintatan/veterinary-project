<?php

namespace App\Booking;

use App\Actions\Agenda\ChangeAppointmentStatus;
use App\Actions\Patients\RecordConsents;
use App\Enums\AppointmentStatus;
use App\Jobs\SendBookingMessage;
use App\Models\Appointment;
use App\Models\AppointmentStatusChange;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The clinic's answer to an online booking request (docs/reservas.md).
 * Accepting links the request to a tutor and a pet (existing or new) and
 * turns it into a normal "programada" appointment: from there on, the usual
 * reminders and WhatsApp confirmation apply.
 */
final class BookingRequests
{
    public function __construct(
        private readonly RecordConsents $recordConsents,
        private readonly ChangeAppointmentStatus $changeStatus,
    ) {}

    /**
     * Possible matches for the person who asked, for the staff to choose:
     * same document first (strongest), then same phone. Shown only to the
     * clinic, never on the public page.
     *
     * @return list<array{id: string, full_name: string, mobile_phone: string|null, document: string|null, match: string, pets: list<array{id: string, name: string, same_name: bool}>}>
     */
    public function suggestions(Appointment $request): array
    {
        $details = $request->booking_details ?? [];
        $petName = Str::lower(trim((string) ($details['pet_name'] ?? '')));
        $phone = $details['phone'] ?? '';
        $document = $details['document_number'] ?? null;

        return Owner::with(['pets' => fn ($q) => $q->whereNull('deceased_at')->orderBy('name')])
            ->where(function ($q) use ($phone, $document, $details) {
                $q->where('mobile_phone', $phone);
                if ($document !== null) {
                    $q->orWhere(fn ($q) => $q->where('document_type', $details['document_type'])->where('document_number', $document));
                }
            })
            ->limit(5)
            ->get()
            ->map(function (Owner $o) use ($phone, $document, $details, $petName) {
                $sameDocument = $document !== null && $o->document_number === $document && $o->document_type === ($details['document_type'] ?? null);
                $samePhone = $o->mobile_phone === $phone;

                return [
                    'id' => $o->id,
                    'full_name' => $o->full_name,
                    'mobile_phone' => $o->mobile_phone,
                    'document' => $o->document_number === null ? null : "{$o->document_type} {$o->document_number}",
                    'match' => $sameDocument && $samePhone ? 'both' : ($sameDocument ? 'document' : 'phone'),
                    'pets' => $o->pets->map(fn (Pet $p) => ['id' => $p->id, 'name' => $p->name, 'same_name' => Str::lower($p->name) === $petName])->values()->all(),
                ];
            })
            ->sortBy(fn ($s) => ['both' => 0, 'document' => 1, 'phone' => 2][$s['match']])
            ->values()
            ->all();
    }

    /**
     * @param  array{owner_id?: string|null, pet_id?: string|null, update_phone?: bool}  $link  empty = create from the request
     */
    public function accept(Appointment $request, array $link, User $actor): Appointment
    {
        $this->ensurePending($request);
        $details = $request->booking_details ?? [];

        return DB::transaction(function () use ($request, $link, $actor, $details) {
            $documentOwner = empty($details['document_number']) ? null : Owner::where('document_type', $details['document_type'])->where('document_number', $details['document_number'])->first();

            if (! empty($link['owner_id'])) {
                $owner = Owner::findOrFail($link['owner_id']);

                if (($link['update_phone'] ?? false) && ! empty($details['phone']) && $owner->mobile_phone !== $details['phone']) {
                    $owner->update(['mobile_phone' => $details['phone']]);
                }

                // Complete the document if the tutor didn't have one (and nobody else has it).
                if ($owner->document_number === null && $documentOwner === null && ! empty($details['document_number'])) {
                    $owner->update(['document_type' => $details['document_type'], 'document_number' => $details['document_number']]);
                }
            } else {
                if ($documentOwner !== null) {
                    throw ValidationException::withMessages(['owner_id' => "Ya existe un tutor con ese documento ({$documentOwner->full_name}). Vincula la solicitud a ese tutor."]);
                }

                $owner = Owner::create([
                    'full_name' => $details['name'],
                    'mobile_phone' => $details['phone'],
                    'document_type' => $details['document_type'] ?? null,
                    'document_number' => $details['document_number'] ?? null,
                    'referral_source' => 'Reserva en línea',
                    'created_by' => $actor->id,
                ]);
            }

            if (! empty($link['pet_id'])) {
                $pet = Pet::where('owner_id', $owner->id)->findOrFail($link['pet_id']);
            } else {
                $pet = Pet::create(['owner_id' => $owner->id, 'name' => $details['pet_name'], 'species_id' => $details['species_id'], 'created_by' => $actor->id]);
            }

            // What the person authorized in the booking form (Ley 1581), with its evidence.
            ($this->recordConsents)($owner, ['data_processing' => true, 'whatsapp' => true], 'online_booking', $actor->id, [
                'appointment_id' => $request->id,
                'requested_at' => $details['requested_at'] ?? null,
                'phone' => $details['phone'] ?? null,
            ]);

            $from = $request->status;
            $request->forceFill(['owner_id' => $owner->id, 'pet_id' => $pet->id, 'status' => AppointmentStatus::Scheduled])->save();

            AppointmentStatusChange::create([
                'appointment_id' => $request->id,
                'from_status' => $from->value,
                'to_status' => AppointmentStatus::Scheduled->value,
                'changed_by' => $actor->id,
                'channel' => 'staff',
                'note' => 'Solicitud en línea aceptada',
            ]);

            SendBookingMessage::dispatch($request->id, SendBookingMessage::ACCEPTED)->afterCommit();

            return $request->refresh();
        });
    }

    public function reject(Appointment $request, string $reason, User $actor): Appointment
    {
        $this->ensurePending($request);

        $rejected = ($this->changeStatus)($request, AppointmentStatus::Cancelled, 'staff', $actor->id, 'Solicitud en línea rechazada', $reason);
        SendBookingMessage::dispatch($rejected->id, SendBookingMessage::REJECTED)->afterCommit();

        return $rejected;
    }

    private function ensurePending(Appointment $request): void
    {
        if ($request->status !== AppointmentStatus::Requested) {
            throw ValidationException::withMessages(['status' => 'Esta solicitud ya fue respondida.']);
        }
    }
}
