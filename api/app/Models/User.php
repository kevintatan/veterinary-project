<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Platform-level identity: one person, possibly several clinics.
 * Roles are per clinic (Spatie teams keyed by clinic_id).
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property bool $is_platform_admin
 */
#[Fillable(['name', 'email', 'password', 'phone', 'document_type', 'document_number', 'professional_license'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    use StoresDatesInUtc;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * Memberships across ALL clinics. Only for platform-level flows
     * (login, clinic selection); never expose them to a clinic.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ClinicMembership::class)->withoutGlobalScopes();
    }

    /**
     * Clinics where the user is an active member.
     */
    public function activeClinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'clinic_user')
            ->wherePivot('status', ClinicMembership::STATUS_ACTIVE)
            ->where('clinics.status', Clinic::STATUS_ACTIVE);
    }

    /**
     * Branches of the clinic in context (branch_user is filtered by the
     * Branch model's clinic scope).
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withTimestamps();
    }
}
