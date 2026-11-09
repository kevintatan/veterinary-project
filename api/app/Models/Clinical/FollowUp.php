<?php

namespace App\Models\Clinical;

/**
 * Seguimiento.
 */
class FollowUp extends ClinicalDetail
{
    protected $table = 'follow_ups';

    protected $fillable = ['id', 'kind', 'reason', 'details'];

    protected function casts(): array
    {
        return [];
    }
}
