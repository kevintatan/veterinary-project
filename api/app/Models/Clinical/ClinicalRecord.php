<?php

namespace App\Models\Clinical;

use App\Models\Appointment;
use App\Models\Attachment;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Concerns\StoresDatesInUtc;
use App\Models\Pet;
use App\Models\User;
use App\Observers\ClinicalRecordReminderObserver;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Parent of every clinical record (docs/database.md §7). Never deleted:
 * voided (with reason) and locked after the clinic's edit window.
 *
 * @property string $id
 * @property string $clinic_id
 * @property string $pet_id
 * @property string $type
 * @property string $author_id
 * @property Carbon $occurred_at
 * @property Carbon|null $next_control_at
 * @property Carbon|null $locked_at
 * @property Carbon|null $voided_at
 * @property Carbon $created_at
 */
#[ObservedBy(ClinicalRecordReminderObserver::class)]
class ClinicalRecord extends Model
{
    use BelongsToClinic, HasUuids, LogsActivity, StoresDatesInUtc;

    public const TYPES = [
        'consultation' => 'Consulta',
        'vaccination' => 'Vacunación',
        'deworming' => 'Desparasitación',
        'prescription' => 'Fórmula médica',
        'procedure' => 'Cirugía / procedimiento',
        'follow_up' => 'Seguimiento',
        'note' => 'Nota clínica',
    ];

    /** Default visibility in the owner portal (docs/database.md §10b). */
    public const VISIBLE_TO_OWNER_BY_DEFAULT = ['vaccination', 'deworming', 'prescription'];

    protected $fillable = [
        'branch_id', 'pet_id', 'type', 'occurred_at', 'author_id', 'appointment_id', 'parent_record_id',
        'next_control_at', 'next_control_note', 'ai_assisted', 'visible_to_owner',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'next_control_at' => 'date',
            'locked_at' => 'datetime',
            'voided_at' => 'datetime',
            'ai_assisted' => 'boolean',
            'visible_to_owner' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Clinical records are never deleted: void them instead.'));
    }

    // ---- Relations ------------------------------------------------------------

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_record_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_record_id');
    }

    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class, 'id');
    }

    public function vaccination(): HasOne
    {
        return $this->hasOne(Vaccination::class, 'id');
    }

    public function deworming(): HasOne
    {
        return $this->hasOne(Deworming::class, 'id');
    }

    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class, 'id');
    }

    public function procedure(): HasOne
    {
        return $this->hasOne(Procedure::class, 'id');
    }

    public function followUp(): HasOne
    {
        return $this->hasOne(FollowUp::class, 'id');
    }

    public function note(): HasOne
    {
        return $this->hasOne(ClinicalNote::class, 'id');
    }

    public function vitalSigns(): HasOne
    {
        return $this->hasOne(VitalSigns::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(PhysicalExamFinding::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(ConsultationDiagnosis::class)->orderBy('sort');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // ---- State ------------------------------------------------------------------

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * Locked when marked by the job OR once the edit window has passed,
     * so the rule holds even if the job has not run yet.
     */
    /**
     * When the edit window closes (null when already locked by hand or not saved).
     */
    public function locksAt(?Clinic $clinic = null): ?Carbon
    {
        if ($this->locked_at !== null || $this->created_at === null) {
            return null;
        }

        $clinic ??= Clinic::find($this->clinic_id);

        return $this->created_at->copy()->addHours((int) $clinic->setting('edit_window_hours'));
    }

    public function isLocked(?Clinic $clinic = null): bool
    {
        if ($this->locked_at !== null) {
            return true;
        }

        $clinic ??= Clinic::find($this->clinic_id);
        $hours = (int) $clinic->setting('edit_window_hours');

        return $this->created_at !== null && $this->created_at->copy()->addHours($hours)->isPast();
    }

    /**
     * Controls still pending: no newer record of the same kind for the pet
     * (for vaccines, of the SAME vaccine: a later parvovirus shot doesn't
     * answer a rabies booster). Used by reminders, manual and automatic.
     */
    public function scopeUnanswered(Builder $query): Builder
    {
        return $query->whereRaw(<<<'SQL'
            NOT EXISTS (
                SELECT 1 FROM clinical_records newer
                LEFT JOIN vaccinations nv ON nv.id = newer.id
                LEFT JOIN vaccinations ov ON ov.id = clinical_records.id
                WHERE newer.pet_id = clinical_records.pet_id
                  AND newer.type = clinical_records.type
                  AND newer.occurred_at > clinical_records.occurred_at
                  AND newer.voided_at IS NULL
                  AND (clinical_records.type <> 'vaccination'
                       OR COALESCE(nv.vaccine_id::text, lower(nv.vaccine_name)) = COALESCE(ov.vaccine_id::text, lower(ov.vaccine_name)))
            )
            SQL);
    }

    public function scopeNotVoided(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['occurred_at', 'next_control_at', 'next_control_note', 'visible_to_owner', 'voided_at', 'void_reason', 'locked_at'])
            ->logOnlyDirty();
    }
}
