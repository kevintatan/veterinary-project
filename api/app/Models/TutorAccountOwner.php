<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Portal account ↔ owner record of one clinic. */
class TutorAccountOwner extends Model
{
    use BelongsToClinic, HasUuids, StoresDatesInUtc;

    protected $table = 'tutor_account_owner';

    protected $fillable = ['clinic_id', 'tutor_account_id', 'owner_id', 'link_method', 'linked_at'];

    protected function casts(): array
    {
        return ['linked_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(TutorAccount::class, 'tutor_account_id');
    }
}
