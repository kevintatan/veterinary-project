<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Branches\BranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class BranchController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BranchResource::collection(Branch::orderByDesc('is_active')->orderBy('name')->get());
    }

    public function store(BranchRequest $request): BranchResource
    {
        $this->ensureWithinPlanLimit($request);

        return new BranchResource(Branch::create($request->validated()));
    }

    public function show(Branch $branch): BranchResource
    {
        return new BranchResource($branch);
    }

    public function update(BranchRequest $request, Branch $branch): BranchResource
    {
        $deactivating = $branch->is_active && $request->has('is_active') && ! $request->boolean('is_active');

        if ($deactivating && Branch::where('is_active', true)->count() === 1) {
            throw ValidationException::withMessages(['is_active' => 'La clínica debe tener al menos una sede activa.']);
        }

        $branch->update($request->validated());

        return new BranchResource($branch);
    }

    private function ensureWithinPlanLimit(Request $request): void
    {
        /** @var Clinic $clinic */
        $clinic = $request->attributes->get('clinic');
        $subscription = Subscription::with('plan')->whereIn('status', Subscription::CURRENT_STATUSES)->latest()->first();
        $limit = $subscription?->plan->limits['branches'] ?? null;

        if ($limit !== null && Branch::where('is_active', true)->count() >= $limit) {
            throw ValidationException::withMessages([
                'name' => "Tu plan permite {$limit} sede(s). Cambia de plan para agregar más sedes en {$clinic->name}.",
            ]);
        }
    }
}
