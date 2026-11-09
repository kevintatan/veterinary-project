<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Patients\RecordConsents;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\OwnerConsentsRequest;
use App\Http\Requests\Patients\OwnerRequest;
use App\Http\Resources\OwnerResource;
use App\Models\Owner;
use App\Support\SearchText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnerController extends Controller
{
    /**
     * Search by owner name (any word order, typo tolerant), document, phone,
     * or by pet name / microchip. Returns owners with their pets.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']], [], trans('fields'));

        $query = Owner::query()->with(['pets' => fn ($q) => $q->with(['species', 'breed'])->orderBy('name')]);
        $term = trim((string) $request->input('q'));

        if ($term === '') {
            return OwnerResource::collection($query->latest('updated_at')->paginate(20));
        }

        $normalized = SearchText::normalize($term);
        $words = SearchText::words($term);
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        $allWords = function (Builder $q, string $column) use ($words): void {
            foreach ($words as $word) {
                $q->where($column, 'ilike', '%'.SearchText::like($word).'%');
            }
        };

        $query->where(function (Builder $q) use ($allWords, $normalized, $digits, $term) {
            $q->where(fn (Builder $w) => $allWords($w, 'owners.name_search'))
                // Typo tolerance: "carolna restrpo" still finds "carolina restrepo".
                ->orWhereRaw('? <% owners.name_search', [$normalized])
                ->orWhereHas('pets', fn (Builder $p) => $p
                    ->where(fn (Builder $w) => $allWords($w, 'pets.name_search'))
                    ->orWhere('pets.microchip', $term));

            if (strlen($digits) >= 5) {
                $q->orWhere('owners.document_number', 'like', SearchText::like($digits).'%')
                    ->orWhere('owners.mobile_phone', 'like', '%'.SearchText::like($digits).'%');
            }
        });

        $query->orderByRaw('word_similarity(?, owners.name_search) DESC', [$normalized])->orderBy('owners.full_name');

        return OwnerResource::collection($query->paginate(20)->withQueryString());
    }

    public function store(OwnerRequest $request, RecordConsents $recordConsents): JsonResponse
    {
        $owner = DB::transaction(function () use ($request, $recordConsents) {
            $owner = Owner::create([...Arr::except($request->validated(), ['consents']), 'created_by' => $request->user()->id]);

            $recordConsents($owner, $request->validated('consents'), 'in_person', $request->user()->id);

            return $owner;
        });

        return (new OwnerResource($owner->load('pets')))->withConsents()->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Owner $owner): OwnerResource
    {
        return (new OwnerResource($owner->load(['pets' => fn ($q) => $q->with(['species', 'breed', 'alerts' => fn ($a) => $a->where('is_active', true)])->orderBy('name')])))->withConsents();
    }

    public function update(OwnerRequest $request, Owner $owner, RecordConsents $recordConsents): OwnerResource
    {
        DB::transaction(function () use ($request, $owner, $recordConsents) {
            $owner->update($request->validated());

            // Without the contact data, the channel authorization no longer applies.
            $revoke = [];
            if ($owner->mobile_phone === null) {
                $revoke += ['whatsapp' => false, 'sms' => false];
            }
            if ($owner->email === null) {
                $revoke['email'] = false;
            }
            $recordConsents($owner, $revoke, 'in_person', $request->user()->id, ['reason' => 'contact_removed']);
        });

        return (new OwnerResource($owner->refresh()->load('pets')))->withConsents();
    }

    public function destroy(Owner $owner): Response
    {
        if ($owner->pets()->exists()) {
            throw ValidationException::withMessages(['owner' => 'No se puede eliminar un tutor con mascotas registradas.']);
        }

        $owner->delete();

        return response()->noContent();
    }

    public function updateConsents(OwnerConsentsRequest $request, Owner $owner, RecordConsents $recordConsents): JsonResponse
    {
        $changed = $recordConsents($owner, $request->validated(), 'in_person', $request->user()->id);

        return response()->json([
            'data' => (new OwnerResource($owner))->consentsPayload(),
            'changed' => $changed,
        ]);
    }
}
