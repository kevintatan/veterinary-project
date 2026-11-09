<?php

namespace App\Documents;

use App\Enums\DocumentType;
use App\Enums\PetAttributes;
use App\Models\Clinic;
use App\Models\Documents\DocumentTemplate;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Fills a template (DocumentTypes) with the clinic, owner, pet and vet data
 * and the case fields. The result is frozen in the document (snapshot): what
 * the owner reads on screen, what they sign and what the PDF prints are the
 * same text, even if the pet's weight or the clinic's name change later.
 */
class DocumentRenderer
{
    /**
     * Validation rules for the case fields of a type.
     *
     * @return array<string, mixed>
     */
    public function rules(string $type): array
    {
        $rules = [];

        foreach (DocumentTypes::get($type)['fields'] ?? [] as $f) {
            $required = ($f['required'] ?? false) ? 'required' : 'nullable';
            $rules["data.{$f['key']}"] = match ($f['type']) {
                'text' => [$required, 'string', 'max:300'],
                'textarea' => [$required, 'string', 'max:3000'],
                'select' => [$required, Rule::in(array_keys($f['options'] ?? []))],
                'checkbox' => ['nullable', 'boolean'],
                'money' => [$required, 'integer', 'min:0', 'max:1000000000'],
                'number' => [$required, 'integer', 'min:1', 'max:365'],
            };
        }

        return $rules;
    }

    /** @return array<string, string> attribute names for the error messages */
    public function attributes(string $type): array
    {
        return collect(DocumentTypes::get($type)['fields'] ?? [])->mapWithKeys(fn ($f) => ["data.{$f['key']}" => mb_strtolower($f['label'])])->all();
    }

    /**
     * Everything the document needs, frozen now.
     *
     * @param  array<string, mixed>  $data  validated case fields
     * @return array<string, mixed>
     */
    public function snapshot(string $type, array $data, Clinic $clinic, Pet $pet, User $vet): array
    {
        $template = DocumentTypes::get($type) ?? throw ValidationException::withMessages(['type' => 'Tipo de documento desconocido.']);
        $pet->loadMissing(['species', 'breed', 'owner']);
        /** @var Owner $owner */
        $owner = $pet->owner;

        if (blank($owner?->document_number)) {
            throw ValidationException::withMessages(['owner' => 'El tutor no tiene documento de identidad registrado. Complétalo en su ficha: el documento lo identifica en el consentimiento.']);
        }
        if (blank($vet->professional_license)) {
            throw ValidationException::withMessages(['vet_id' => "{$vet->name} no tiene tarjeta profesional registrada (Configuración → Mi perfil). Es obligatoria en un consentimiento."]);
        }

        $docType = DocumentType::tryFrom((string) ($owner->document_type?->value ?? $owner->document_type))?->label() ?? 'documento';
        $now = Carbon::now($clinic->timezone);

        $values = [
            'fecha' => $now->translatedFormat('j \d\e F \d\e Y'),
            'clinica' => $clinic->name,
            'clinica_nit' => $clinic->tax_id ? "NIT {$clinic->tax_id}" : '',
            'tutor' => $owner->full_name,
            'tutor_doc' => "{$docType} {$owner->document_number}",
            'tutor_cel' => $this->phone($owner->mobile_phone) ?? 'el número registrado en la clínica',
            'mascota' => $pet->name,
            'especie' => mb_strtolower((string) ($pet->species?->name ?? 'especie no registrada')),
            'raza' => $pet->breed?->name ?? $pet->breed_other ?? 'raza no registrada',
            'sexo' => mb_strtolower(PetAttributes::SEX[$pet->sex] ?? 'sexo no definido'),
            'edad' => $pet->ageLabel() ?? 'edad no registrada',
            'peso' => $pet->weight_kg !== null ? str_replace('.', ',', rtrim(rtrim(number_format((float) $pet->weight_kg, 2, '.', ''), '0'), '.')).' kg' : 'peso no registrado',
            'microchip' => $pet->microchip ?? 'sin microchip',
            'veterinario' => $vet->name,
            'veterinario_tp' => $vet->professional_license,
            'contacto' => filled($data['contact'] ?? null) ? trim((string) $data['contact']) : ($this->phone($owner->mobile_phone) ?? 'el número registrado en la clínica'),
        ];

        $fields = collect($template['fields'])->keyBy('key');
        $field = function (string $key) use ($fields, $data): string {
            $f = $fields[$key] ?? null;
            $v = $data[$key] ?? null;

            return match (true) {
                $f === null || $v === null || $v === '' => '',
                $f['type'] === 'select' => $f['options'][$v] ?? (string) $v,
                $f['type'] === 'money' => '$ '.number_format((int) $v, 0, ',', '.'),
                $f['type'] === 'checkbox' => $v ? 'sí' : 'no',
                default => trim((string) $v),
            };
        };

        $fill = fn (string $text) => preg_replace_callback('/\{\{\s*([a-z_.]+)\s*\}\}/', function ($m) use ($values, $field) {
            return str_starts_with($m[1], 'campo.') ? $field(substr($m[1], 6)) : ($values[$m[1]] ?? '');
        }, $text);

        $blocks = [];
        // The clinic's own wording if it has one (D4); otherwise Zooetia's.
        $custom = DocumentTemplate::where('type', $type)->first();

        foreach ($custom?->body ?? $template['body'] as $block) {
            $when = $block['when'] ?? null;
            if ($when !== null && empty($data[$when])) {
                continue;
            }
            $blocks[] = isset($block['list'])
                ? ['type' => 'list', 'items' => array_map($fill, $block['list'])]
                : ['type' => 'p', 'text' => $fill($block['text'])];
        }

        return [
            'title' => $template['name'],
            'date' => $values['fecha'],
            'clinic' => ['name' => $clinic->name, 'legal_name' => $clinic->legal_name, 'tax_id' => $clinic->tax_id, 'address' => $clinic->address, 'phone' => $clinic->phone],
            'owner' => ['name' => $owner->full_name, 'document' => $values['tutor_doc'], 'document_number' => $owner->document_number, 'phone' => $values['tutor_cel']],
            'pet' => ['name' => $pet->name, 'species' => $pet->species?->name, 'breed' => $values['raza'], 'sex' => PetAttributes::SEX[$pet->sex] ?? null, 'age' => $values['edad'], 'weight' => $values['peso'], 'microchip' => $pet->microchip],
            'vet' => ['id' => $vet->id, 'name' => $vet->name, 'license' => $vet->professional_license],
            'signers' => $template['signers'],
            'template' => ['base_version' => $template['version'], 'clinic_version' => $custom?->version],
            'blocks' => $blocks,
        ];
    }

    /** sha256 of exactly what is signed: the title and the frozen text, plus who it is about. */
    public function contentHash(array $snapshot): string
    {
        return hash('sha256', json_encode([
            'title' => $snapshot['title'],
            'date' => $snapshot['date'],
            'owner' => $snapshot['owner'],
            'pet' => $snapshot['pet'],
            'vet' => $snapshot['vet'],
            'blocks' => $snapshot['blocks'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function phone(?string $e164): ?string
    {
        if ($e164 === null) {
            return null;
        }

        return str_starts_with($e164, '+57') && strlen($e164) === 13
            ? substr($e164, 3, 3).' '.substr($e164, 6, 3).' '.substr($e164, 9)
            : $e164;
    }
}
