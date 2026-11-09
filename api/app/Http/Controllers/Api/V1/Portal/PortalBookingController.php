<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Booking\OnlineBooking;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Owner;
use App\Models\Pet;
use App\Portal\TutorPortal;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Pedir una cita" from the owner portal (docs/reservas.md, B3): the clinics
 * of this account that take online requests, with the tutor's data and pets
 * to prefill the same booking form. The request still waits for the clinic.
 */
class PortalBookingController extends Controller
{
    public function __construct(private readonly TutorPortal $portal, private readonly TenantContext $tenant) {}

    public function index(Request $request): JsonResponse
    {
        $clinics = [];

        foreach ($this->portal->links($request->user()) as $link) {
            $this->tenant->runAs($link->clinic_id, function () use ($link, &$clinics) {
                $clinic = Clinic::find($link->clinic_id);
                $owner = Owner::find($link->owner_id);

                if ($clinic === null || $owner === null || ! OnlineBooking::isOpen($clinic)) {
                    return;
                }

                $clinics[] = [
                    'name' => $clinic->name,
                    'slug' => $clinic->slug,
                    'prefill' => [
                        'name' => $owner->full_name,
                        'phone' => $owner->mobile_phone,
                        'document_type' => in_array($owner->document_type, OnlineBooking::DOCUMENT_TYPES, true) ? $owner->document_type : null,
                        'document_number' => in_array($owner->document_type, OnlineBooking::DOCUMENT_TYPES, true) ? $owner->document_number : null,
                    ],
                    'pets' => Pet::where('owner_id', $owner->id)->whereNull('deceased_at')->orderBy('name')->get(['id', 'name', 'species_id'])
                        ->map(fn (Pet $p) => ['id' => $p->id, 'name' => $p->name, 'species_id' => $p->species_id])->all(),
                ];
            });
        }

        return response()->json(['data' => $clinics]);
    }
}
