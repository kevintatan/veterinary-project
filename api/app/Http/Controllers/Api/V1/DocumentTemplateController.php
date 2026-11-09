<?php

namespace App\Http\Controllers\Api\V1;

use App\Documents\DocumentTypes;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Documents\DocumentTemplate;
use App\Support\ClinicTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The clinic adjusts the wording of its consents (Documentos y firmas, D4).
 * Only the text: case fields and signers stay Zooetia's. Applies to new
 * documents; existing ones keep the text they were created with.
 */
class DocumentTemplateController extends Controller
{
    /** Without these the document no longer identifies who authorizes and for which pet. */
    private const REQUIRED = ['tutor' => 'el nombre del tutor', 'tutor_doc' => 'el documento del tutor', 'mascota' => 'el nombre de la mascota'];

    public function index(Request $request): JsonResponse
    {
        $clinic = $this->clinic($request);
        $custom = DocumentTemplate::with('editor:id,name')->get()->keyBy('type');

        return response()->json(['data' => [
            'placeholders' => collect(DocumentTypes::PLACEHOLDERS)->map(fn ($p, $key) => ['key' => $key, 'label' => $p[0], 'example' => $p[1]])->values(),
            'types' => collect(DocumentTypes::all())->map(function ($t, $code) use ($custom, $clinic) {
                $c = $custom[$code] ?? null;

                return [
                    'code' => $code,
                    'name' => $t['name'],
                    'short' => $t['short'],
                    'fields' => $t['fields'],
                    // Who signs (for the signature lines of the editor's page); the witness only when asked for.
                    'signers' => collect(['owner', 'vet', 'witness'])->filter(fn ($r) => isset($t['signers'][$r]))->map(fn ($r) => ['role' => $r, ...$t['signers'][$r]])->values(),
                    'base_body' => $t['body'],
                    'base_version' => $t['version'],
                    'body' => $c?->body ?? $t['body'],
                    'customized' => $c !== null,
                    'version' => $c?->version,
                    // Zooetia updated its text after the clinic edited its copy: worth a look.
                    'base_changed' => $c !== null && $c->base_version < $t['version'],
                    'updated_at' => ClinicTime::iso($c?->updated_at, $clinic),
                    'updated_by' => $c?->editor?->name,
                ];
            })->values(),
        ]]);
    }

    public function update(Request $request, string $documentType): JsonResponse
    {
        $type = DocumentTypes::get($documentType) ?? abort(404);
        $fieldKeys = collect($type['fields'])->pluck('key')->all();

        $data = $request->validate([
            'body' => ['required', 'array', 'min:1', 'max:60'],
            'body.*.text' => ['required_without:body.*.list', 'nullable', 'string', 'min:1', 'max:3000'],
            'body.*.list' => ['required_without:body.*.text', 'nullable', 'array', 'min:1', 'max:30'],
            'body.*.list.*' => ['required', 'string', 'min:1', 'max:1000'],
            'body.*.when' => ['nullable', Rule::in($fieldKeys)],
        ], [
            'body.*.text.required_without' => 'Hay un párrafo vacío: escríbelo o elimínalo.',
            'body.*.list.*.required' => 'Hay una viñeta vacía: escríbela o elimínala.',
        ], ['body' => 'texto']);

        $body = collect($data['body'])->map(fn ($b) => array_filter([
            'text' => isset($b['text']) && $b['text'] !== null ? trim($b['text']) : null,
            'list' => isset($b['list']) && $b['list'] !== null ? array_values(array_map('trim', $b['list'])) : null,
            'when' => $b['when'] ?? null,
        ], fn ($v) => $v !== null))->values()->all();

        $errors = [];
        foreach ($body as $i => $block) {
            $unknown = DocumentTypes::unknownPlaceholders($documentType, $block['text'] ?? implode("\n", $block['list'] ?? []));
            if ($unknown !== []) {
                $errors["body.{$i}"] = 'No existe el dato '.implode(', ', array_map(fn ($u) => '{{'.$u.'}}', $unknown)).'. Usa los de la lista de datos que se llenan solos.';
            }
        }

        $all = collect($body)->map(fn ($b) => $b['text'] ?? implode("\n", $b['list'] ?? []))->implode("\n");
        foreach (self::REQUIRED as $key => $label) {
            if (! preg_match('/\{\{\s*'.preg_quote($key, '/').'\s*\}\}/', $all)) {
                $errors['body'] = "El texto debe incluir {$label} ({{{$key}}}): identifica a quien autoriza.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $current = DocumentTemplate::where('type', $documentType)->first();
        DocumentTemplate::updateOrCreate(['type' => $documentType], [
            'body' => $body,
            'version' => ($current?->version ?? 0) + 1,
            'base_version' => $type['version'],
            'updated_by' => $request->user()->id,
        ]);

        activity('documents')->causedBy($request->user())->event('template_updated')->withProperties(['type' => $documentType])->log('Ajustó el texto de un documento');

        return $this->index($request);
    }

    /** Back to Zooetia's text. */
    public function destroy(Request $request, string $documentType): JsonResponse
    {
        abort_if(DocumentTypes::get($documentType) === null, 404);
        DocumentTemplate::where('type', $documentType)->delete();

        activity('documents')->causedBy($request->user())->event('template_restored')->withProperties(['type' => $documentType])->log('Restauró el texto de Zooetia de un documento');

        return $this->index($request);
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
