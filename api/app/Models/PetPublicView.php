<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PetPublicView extends Model
{
    use BelongsToClinic, HasUuids, StoresDatesInUtc;

    public $timestamps = false;

    protected $fillable = ['pet_id', 'in_lost_mode', 'ip_hash', 'user_agent', 'viewed_at'];

    protected function casts(): array
    {
        return ['in_lost_mode' => 'boolean', 'viewed_at' => 'datetime'];
    }
}
