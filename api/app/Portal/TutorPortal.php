<?php

namespace App\Portal;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Pet;
use App\Models\TutorAccount;
use App\Pets\VaccinationStatus;
use App\Support\ClinicTime;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything the owner sees in the portal (Fase 4e), across every clinic that
 * linked them. Platform code that only reads the account's links; the pets,
 * vaccines and appointments are always read INSIDE each clinic (runAs), so
 * the tenant scope still protects every query.
 */
class TutorPortal
{
    public function __construct(private readonly TenantContext $tenant, private readonly VaccinationStatus $vaccines) {}

    /**
     * The account's links: a narrow read of (clinic_id, owner_id) pairs, the
     * same way the webhook routes messages (no tenant bypass needed).
     *
     * @return Collection<int, object{clinic_id: string, owner_id: string}>
     */
    public function links(TutorAccount $account): Collection
    {
        return DB::table('tutor_account_owner')->where('tutor_account_id', $account->id)->orderBy('linked_at')->get(['clinic_id', 'owner_id']);
    }

    /** @return list<array<string, mixed>> */
    public function pets(TutorAccount $account): array
    {
        $result = [];

        foreach ($this->links($account) as $link) {
            $this->tenant->runAs($link->clinic_id, function () use ($link, &$result) {
                $clinic = Clinic::find($link->clinic_id);
                $pets = Pet::with(['species', 'breed'])->where('owner_id', $link->owner_id)->orderBy('name')->get();
                $next = $this->nextAppointments($pets->pluck('id')->all());

                foreach ($pets as $pet) {
                    $vaccines = $this->vaccines->for($pet, $clinic);
                    $result[] = [
                        'id' => $pet->id,
                        'name' => $pet->name,
                        'species' => $pet->species?->name,
                        'species_code' => $pet->species?->code,
                        'breed' => $pet->breed?->name ?? $pet->breed_other,
                        'age' => $pet->ageLabel(),
                        'deceased' => $pet->deceased_at !== null,
                        'photo_url' => $pet->photo_path === null ? null : "portal/pets/{$pet->id}/photo?v=".substr(md5($pet->photo_path), 0, 8),
                        'clinic' => ['id' => $clinic->id, 'name' => $clinic->name],
                        'next_appointment' => ($a = $next[$pet->id] ?? null) ? $this->appointment($a, $clinic) : null,
                        'vaccines_expired' => collect($vaccines)->where('status', 'expired')->count(),
                    ];
                }
            });
        }

        return $result;
    }

    /**
     * Runs $callback inside the pet's clinic, only if this account is linked to
     * the pet's owner. Anything else is a 404 (no hint that the pet exists).
     */
    public function withPet(TutorAccount $account, string $petId, callable $callback): mixed
    {
        $row = DB::table('pets')->where('id', $petId)->whereNull('deleted_at')->first(['clinic_id', 'owner_id']);
        abort_if($row === null || ! $this->links($account)->contains(fn ($l) => $l->clinic_id === $row->clinic_id && $l->owner_id === $row->owner_id), 404);

        return $this->tenant->runAs($row->clinic_id, fn () => $callback(Pet::findOrFail($petId), Clinic::findOrFail($row->clinic_id)));
    }

    /** @return list<array<string, mixed>> */
    public function upcoming(Pet $pet, Clinic $clinic): array
    {
        return Appointment::with(['service', 'branch'])
            ->where('pet_id', $pet->id)
            ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed])
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->limit(10)
            ->get()
            ->map(fn (Appointment $a) => $this->appointment($a, $clinic))
            ->all();
    }

    /** @return array<string, mixed> */
    private function appointment(Appointment $a, Clinic $clinic): array
    {
        return [
            'id' => $a->id,
            'starts_at' => ClinicTime::iso($a->starts_at, $clinic),
            'is_time_unset' => $a->is_time_unset,
            'status' => ['value' => $a->status->value, 'label' => $a->status->label()],
            'service' => $a->service?->name,
            'branch' => $a->branch ? ['name' => $a->branch->name, 'address' => $a->branch->address] : null,
        ];
    }

    /**
     * @param  list<string>  $petIds
     * @return array<string, Appointment>
     */
    private function nextAppointments(array $petIds): array
    {
        return Appointment::with(['service', 'branch'])
            ->whereIn('pet_id', $petIds)
            ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed])
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->get()
            ->unique('pet_id')
            ->keyBy('pet_id')
            ->all();
    }
}
