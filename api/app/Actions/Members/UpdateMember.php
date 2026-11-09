<?php

namespace App\Actions\Members;

use App\Auth\ClinicRole;
use App\Models\ClinicMembership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Role, status, agenda and branches of a member of the clinic in context.
 */
final class UpdateMember
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  array{role?: string, status?: string, shows_in_agenda?: bool, agenda_color?: string|null, branch_ids?: list<string>}  $data
     */
    public function __invoke(User $actor, ClinicMembership $membership, array $data): ClinicMembership
    {
        $user = $membership->user;
        $changesAccess = isset($data['role']) || isset($data['status']);

        if ($changesAccess && $user->is($actor)) {
            throw ValidationException::withMessages(['role' => 'No puedes cambiar tu propio rol ni desactivarte.']);
        }

        $losesAdmin = $user->hasRole(ClinicRole::Admin->value) && (
            (isset($data['role']) && $data['role'] !== ClinicRole::Admin->value)
            || (($data['status'] ?? null) === ClinicMembership::STATUS_DISABLED)
        );

        if ($losesAdmin && $this->activeAdminCount() <= 1) {
            throw ValidationException::withMessages(['role' => 'La clínica debe tener al menos un administrador activo.']);
        }

        return DB::transaction(function () use ($membership, $user, $data) {
            $membership->update(array_intersect_key($data, array_flip(['status', 'shows_in_agenda', 'agenda_color'])));

            if (isset($data['role'])) {
                // Spatie teams: only detaches roles of the clinic in context.
                $user->syncRoles([$data['role']]);
            }

            if (isset($data['branch_ids'])) {
                $clinicId = $this->tenant->requireId();
                $user->branches()->sync(collect($data['branch_ids'])->mapWithKeys(fn (string $id) => [$id => ['clinic_id' => $clinicId]])->all());
            }

            if (($data['status'] ?? null) === ClinicMembership::STATUS_DISABLED) {
                $user->tokens()->where('clinic_id', $this->tenant->requireId())->delete();
            }

            return $membership->refresh();
        });
    }

    private function activeAdminCount(): int
    {
        return ClinicMembership::where('status', ClinicMembership::STATUS_ACTIVE)
            ->with('user')
            ->get()
            ->filter(fn (ClinicMembership $m) => $m->user->hasRole(ClinicRole::Admin->value))
            ->count();
    }
}
