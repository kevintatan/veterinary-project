<?php

namespace App\Http\Resources;

use App\Auth\ClinicRole;
use App\Models\ClinicMembership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member of the clinic in context. Never exposes the user's other clinics.
 *
 * @mixin ClinicMembership
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->user->getRoleNames()->first();

        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'professional_license' => $this->user->professional_license,
            ],
            'role' => $role === null ? null : ['name' => $role, 'label' => ClinicRole::tryFrom($role)?->label()],
            'status' => $this->status,
            'shows_in_agenda' => $this->shows_in_agenda,
            'agenda_color' => $this->agenda_color,
            'branches' => $this->user->branches->map(fn ($branch) => ['id' => $branch->id, 'name' => $branch->name])->values(),
            'joined_at' => $this->joined_at?->toIso8601String(),
        ];
    }
}
