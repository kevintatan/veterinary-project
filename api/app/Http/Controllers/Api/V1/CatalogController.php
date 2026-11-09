<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogItemResource;
use App\Models\Catalogs\Breed;
use App\Models\Catalogs\ClinicalAlert;
use App\Models\Catalogs\ProcedureType;
use App\Models\Catalogs\Species;
use App\Models\Catalogs\Vaccine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Mixed catalogs: global entries (read-only) + the clinic's own.
 * {catalog} is constrained in routes to the keys of MODELS.
 */
class CatalogController extends Controller
{
    /** @var array<string, class-string<Model>> */
    private const MODELS = [
        'species' => Species::class,
        'breeds' => Breed::class,
        'vaccines' => Vaccine::class,
        'procedure-types' => ProcedureType::class,
        'clinical-alerts' => ClinicalAlert::class,
    ];

    public function index(Request $request, string $catalog): AnonymousResourceCollection
    {
        $request->validate([
            'species_id' => ['nullable', 'uuid'],
            'category' => ['nullable', Rule::in(ProcedureType::CATEGORIES)],
            'q' => ['nullable', 'string', 'max:100'],
        ], [], trans('fields'));

        $query = self::MODELS[$catalog]::query()->active();

        if ($request->filled('species_id')) {
            $speciesId = (string) $request->input('species_id');
            match ($catalog) {
                'breeds' => $query->where('species_id', $speciesId),
                'vaccines', 'clinical-alerts' => $query->forSpecies($speciesId),
                default => null,
            };
        }

        if ($catalog === 'procedure-types' && $request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('q')) {
            $query->where('name', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->input('q')).'%');
        }

        $catalog === 'species' ? $query->orderBy('sort')->orderBy('name') : $query->orderBy('name');

        return CatalogItemResource::collection($query->get());
    }

    public function store(Request $request, string $catalog): CatalogItemResource
    {
        $model = self::MODELS[$catalog]::create($request->validate($this->rules($catalog, creating: true), [], trans('fields')));

        return new CatalogItemResource($model);
    }

    public function update(Request $request, string $catalog, string $catalogItem): CatalogItemResource
    {
        $model = self::MODELS[$catalog]::query()->findOrFail($catalogItem);

        abort_if($model->isGlobal(), 403, 'Los elementos del catálogo general no se pueden modificar. Crea uno propio.');

        $model->update($request->validate($this->rules($catalog, creating: false), [], trans('fields')));

        return new CatalogItemResource($model);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(string $catalog, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $speciesExists = fn ($attribute, $value, $fail) => Species::whereKey($value)->exists() ?: $fail('La especie no existe.');

        $common = [
            'name' => [$required, 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        return $common + match ($catalog) {
            'species' => [],
            'breeds' => $creating ? ['species_id' => ['required', 'uuid', $speciesExists]] : [],
            'vaccines' => [
                'manufacturer' => ['nullable', 'string', 'max:80'],
                'protects_against' => ['nullable', 'string', 'max:255'],
                'species_ids' => ['sometimes', 'array'],
                'species_ids.*' => ['uuid', $speciesExists],
                'default_booster_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            ],
            'procedure-types' => ['category' => [$required, Rule::in(ProcedureType::CATEGORIES)]],
            'clinical-alerts' => [
                'type' => [$required, Rule::in(ClinicalAlert::TYPES)],
                'species_ids' => ['sometimes', 'array'],
                'species_ids.*' => ['uuid', $speciesExists],
            ],
        };
    }
}
