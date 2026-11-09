<?php

namespace App\Clinical;

use App\Auth\Permission;
use App\Models\Clinic;
use App\Models\Clinical\ClinicalRecord;
use App\Models\User;

/**
 * Who may change a clinical record (docs/database.md §7, D8):
 * - voided records: nobody;
 * - within the edit window (default 24 h): the author, or clinical_records.edit_locked;
 * - after the window (locked): only clinical_records.edit_locked (admin), always audited.
 * Voiding: the author within the window, or clinical_records.void.
 */
final class RecordAccess
{
    public function canEdit(User $user, ClinicalRecord $record, Clinic $clinic): bool
    {
        if ($record->isVoided()) {
            return false;
        }

        if ($user->can(Permission::ClinicalRecordsEditLocked->value)) {
            return true;
        }

        return $record->author_id === $user->id && ! $record->isLocked($clinic);
    }

    public function canVoid(User $user, ClinicalRecord $record, Clinic $clinic): bool
    {
        if ($record->isVoided()) {
            return false;
        }

        if ($user->can(Permission::ClinicalRecordsVoid->value)) {
            return true;
        }

        return $record->author_id === $user->id && ! $record->isLocked($clinic);
    }

    public function denyEditReason(User $user, ClinicalRecord $record, Clinic $clinic): string
    {
        return match (true) {
            $record->isVoided() => 'El registro está anulado y no se puede modificar.',
            $record->isLocked($clinic) => 'El registro está cerrado (pasó la ventana de edición). Solo un administrador puede modificarlo.',
            default => 'Solo el autor del registro o un administrador puede modificarlo.',
        };
    }
}
