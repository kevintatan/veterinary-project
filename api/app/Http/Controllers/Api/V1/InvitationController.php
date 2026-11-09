<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\IssueClinicToken;
use App\Actions\Members\AcceptInvitation;
use App\Auth\ClinicRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\AcceptInvitationRequest;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Public side of invitations (the invitee is not in the clinic yet).
 */
class InvitationController extends Controller
{
    public function show(string $token, AcceptInvitation $accept): JsonResponse
    {
        $invitation = $accept->findPending($token);
        $clinic = Clinic::findOrFail($invitation->clinic_id);

        return response()->json(['data' => [
            'clinic' => ['name' => $clinic->name, 'logo_path' => $clinic->logo_path],
            'email' => $invitation->email,
            'role' => ClinicRole::from($invitation->role)->label(),
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'account_exists' => User::where('email', $invitation->email)->exists(),
        ]]);
    }

    /**
     * New person: creates the account and joins the clinic.
     */
    public function acceptWithNewAccount(AcceptInvitationRequest $request, string $token, AcceptInvitation $accept, IssueClinicToken $issueToken): JsonResponse
    {
        $data = $request->validated();

        $invitation = $accept->findPending($token);

        if (User::where('email', $invitation->email)->exists()) {
            return response()->json(['message' => 'Ya tienes una cuenta con este correo. Inicia sesión para aceptar la invitación.'], Response::HTTP_CONFLICT);
        }

        ['user' => $user, 'clinic' => $clinic] = DB::transaction(fn () => $accept(
            $token,
            User::create(['name' => $data['name'], 'email' => $invitation->email, 'password' => $data['password']]),
        ));

        return response()->json(['data' => ['token' => $issueToken($user, $clinic->id)]], Response::HTTP_CREATED);
    }

    /**
     * Existing user (any valid token of theirs): joins another clinic.
     */
    public function acceptAsExistingUser(Request $request, string $token, AcceptInvitation $accept, IssueClinicToken $issueToken): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Inicia sesión con tu cuenta de Zooetia.'); // never a portal (tutor) token
        ['clinic' => $clinic] = $accept($token, $user);

        return response()->json(['data' => ['token' => $issueToken($user, $clinic->id)]]);
    }
}
