<?php

namespace App\Http\Resources;

use App\Auth\ClinicRole;
use App\Http\Controllers\Api\V1\ClinicController;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Current session: user + clinic in context + role/permissions + subscription.
 * Must be built inside the clinic context (after ResolveClinicFromToken).
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Clinic $clinic */
        $clinic = $request->attributes->get('clinic');
        $role = $this->getRoleNames()->first();
        // The last one even if expired: the app shows "modo consulta" and how to activate a plan.
        $subscription = Subscription::latestForClinic();

        return [
            'user' => [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'professional_license' => $this->professional_license,
            ],
            'clinic' => [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'slug' => $clinic->slug,
                'logo_url' => ClinicController::logoUrl($clinic),
                'timezone' => $clinic->timezone,
            ],
            'role' => $role === null ? null : [
                'name' => $role,
                'label' => ClinicRole::tryFrom($role)?->label(),
            ],
            'permissions' => $this->getAllPermissions()->pluck('name')->sort()->values(),
            'branches' => $this->branches()->where('is_active', true)->orderBy('name')->get(['branches.id', 'branches.name']),
            'subscription' => $subscription === null ? null : [
                'status' => $subscription->status,
                'plan' => ['code' => $subscription->plan->code, 'name' => $subscription->plan->name],
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'trial_days_left' => $subscription->trialDaysLeft(),
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                'allows_writes' => $subscription->allowsWrites(),
            ],
        ];
    }
}
