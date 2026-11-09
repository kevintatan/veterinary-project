<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Portal\InviteOwnerToPortal;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Owner;
use App\Models\TutorAccount;
use App\Models\TutorAccountOwner;
use App\Models\TutorInvitation;
use App\Notifications\TutorPortalInvitationNotification;
use App\Support\ClinicTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Staff side of the owner portal (Fase 4e): invite an owner and see whether
 * they activated it. Sending is free: by email, or opening the clinic's own
 * WhatsApp with the text ready (no Meta template).
 */
class PortalInvitationController extends Controller
{
    public function show(Request $request, Owner $owner): JsonResponse
    {
        return response()->json(['data' => $this->status($owner, $this->clinic($request))]);
    }

    public function store(Request $request, Owner $owner, InviteOwnerToPortal $invite): JsonResponse
    {
        $data = $request->validate(['channel' => ['required', Rule::in(['whatsapp', 'email', 'link'])]]);
        $clinic = $this->clinic($request);

        if ($data['channel'] === 'email' && blank($owner->email)) {
            throw ValidationException::withMessages(['channel' => 'El tutor no tiene correo registrado.']);
        }

        ['url' => $url] = $invite($owner, $request->user());
        $first = Str::of($owner->full_name)->trim()->explode(' ')->first();
        $text = "Hola {$first}, te escribimos de *{$clinic->name}*. Activa tu acceso a nuestro portal para ver el carné con QR de tu mascota, sus vacunas y tus próximas citas: {$url} (el enlace vence en 7 días).";

        if ($data['channel'] === 'email') {
            Notification::route('mail', $owner->email)->notify(new TutorPortalInvitationNotification($clinic, $owner->full_name, $url));
        }

        return response()->json(['data' => [
            'url' => $url,
            'text' => $text,
            'whatsapp_url' => $data['channel'] === 'whatsapp' ? 'https://wa.me/'.ltrim((string) $owner->mobile_phone, '+').'?text='.rawurlencode($text) : null,
            'status' => $this->status($owner, $clinic),
        ]], 201);
    }

    /** @return array<string, mixed> */
    private function status(Owner $owner, Clinic $clinic): array
    {
        $link = TutorAccountOwner::where('owner_id', $owner->id)->first();
        $account = $link ? TutorAccount::find($link->tutor_account_id) : null;
        $pending = TutorInvitation::where('owner_id', $owner->id)->whereNull('accepted_at')->where('expires_at', '>', now())->latest()->first();

        return [
            'active' => $account !== null,
            'linked_at' => ClinicTime::iso($link?->linked_at, $clinic),
            'last_login_at' => ClinicTime::iso($account?->last_login_at, $clinic),
            'pending_invitation' => $pending ? ['created_at' => ClinicTime::iso($pending->created_at, $clinic), 'expires_at' => ClinicTime::iso($pending->expires_at, $clinic)] : null,
            'can_invite' => filled($owner->mobile_phone),
            'has_email' => filled($owner->email),
        ];
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
