<?php

namespace App\Enums;

/**
 * Allowed values for pet attributes, with Spanish labels for the UI.
 * Kept in one place so migrations' CHECKs, validation and resources agree.
 */
final class PetAttributes
{
    public const SEX = ['male' => 'Macho', 'female' => 'Hembra', 'unknown' => 'No definido'];

    public const SIZE = ['toy' => 'Miniatura', 'small' => 'Pequeño', 'medium' => 'Mediano', 'large' => 'Grande', 'giant' => 'Gigante'];

    public const REPRODUCTIVE_STATUS = ['intact' => 'No esterilizado', 'neutered' => 'Esterilizado', 'unknown' => 'Desconocido'];

    public const TEMPERAMENT = [
        'very_docile' => 'Muy dócil',
        'docile' => 'Dócil',
        'reserved' => 'Reservado',
        'reactive' => 'Reactivo',
        'aggressive' => 'Agresivo',
    ];

    public const ALERT_TYPE = [
        'infectious_disease' => 'Enfermedad infecciosa',
        'chronic_condition' => 'Condición crónica',
        'allergy' => 'Alergia',
        'behavior' => 'Comportamiento',
        'other' => 'Otra',
    ];

    public const ALERT_SEVERITY = ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta'];

    /**
     * @param  array<string, string>  $values
     */
    public static function label(array $values, ?string $key): ?array
    {
        return $key === null ? null : ['value' => $key, 'label' => $values[$key] ?? $key];
    }
}
