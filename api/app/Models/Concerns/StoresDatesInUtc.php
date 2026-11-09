<?php

namespace App\Models\Concerns;

use DateTimeInterface;

/**
 * Eloquent formats a Carbon in *its own* timezone without converting it:
 * assigning 08:00 America/Bogota would be stored as 08:00 UTC (5 hours off).
 * Every model converts to UTC before formatting (info.md: dates stored in UTC).
 */
trait StoresDatesInUtc
{
    public function fromDateTime($value)
    {
        if ($value instanceof DateTimeInterface) {
            $value = $this->asDateTime($value)->utc();
        }

        return parent::fromDateTime($value);
    }
}
