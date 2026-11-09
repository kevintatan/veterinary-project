<?php

namespace App\Clinical;

use App\Clinical\Types\ConsultationType;
use App\Clinical\Types\DewormingType;
use App\Clinical\Types\FollowUpType;
use App\Clinical\Types\NoteType;
use App\Clinical\Types\PrescriptionType;
use App\Clinical\Types\ProcedureType;
use App\Clinical\Types\VaccinationType;
use InvalidArgumentException;

/**
 * Registry of clinical record types. Adding a type (hospitalization, grooming…)
 * = one class here + its detail table.
 */
final class RecordTypes
{
    /** @var array<string, class-string<RecordType>> */
    private const TYPES = [
        'consultation' => ConsultationType::class,
        'vaccination' => VaccinationType::class,
        'deworming' => DewormingType::class,
        'prescription' => PrescriptionType::class,
        'procedure' => ProcedureType::class,
        'follow_up' => FollowUpType::class,
        'note' => NoteType::class,
    ];

    public static function for(string $type): RecordType
    {
        $class = self::TYPES[$type] ?? throw new InvalidArgumentException("Unknown clinical record type [{$type}].");

        return app($class);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * Every type's relations, for loading a mixed timeline in one go.
     *
     * @return list<string>
     */
    public static function allRelations(): array
    {
        return array_values(array_unique(array_merge(...array_map(
            fn (string $class) => app($class)->relations(),
            array_values(self::TYPES),
        ))));
    }
}
