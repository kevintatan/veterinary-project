<?php

namespace App\Http\Resources;

use App\Auth\ClinicRole;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never includes the token (it only travels by email).
 *
 * @mixin Invitation
 */
class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => ['name' => $this->role, 'label' => ClinicRole::tryFrom($this->role)?->label()],
            'branch_ids' => $this->branch_ids,
            'invited_by' => $this->inviter?->name,
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
