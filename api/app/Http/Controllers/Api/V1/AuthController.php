<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\IssueClinicToken;
use App\Actions\Clinics\RegisterClinic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SelectClinicRequest;
use App\Http\Resources\MeResource;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const SELECTION_TOKEN_MINUTES = 10;

    public function register(RegisterRequest $request, RegisterClinic $registerClinic, IssueClinicToken $issueToken): JsonResponse
    {
        ['user' => $user, 'clinic' => $clinic] = $registerClinic($request->validated());

        $token = $issueToken($user, $clinic->id, $request->input('device_name', 'web'));

        return response()->json(['data' => [
            'token' => $token,
            'clinic' => ['id' => $clinic->id, 'name' => $clinic->name],
        ]], Response::HTTP_CREATED);
    }

    /**
     * One clinic => clinic token right away.
     * Several => short-lived token that can only choose a clinic.
     */
    public function login(LoginRequest $request, IssueClinicToken $issueToken): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }

        $clinics = $user->activeClinics()->orderBy('name')->get(['clinics.id', 'clinics.name', 'clinics.logo_path']);
        $deviceName = $request->input('device_name', 'web');

        if ($clinics->isEmpty()) {
            return response()->json(['message' => 'Tu usuario no pertenece a ninguna clínica activa.'], Response::HTTP_FORBIDDEN);
        }

        if ($clinics->count() === 1) {
            return response()->json(['data' => [
                'token' => $issueToken($user, $clinics->first()->id, $deviceName),
                'requires_clinic_selection' => false,
            ]]);
        }

        $selectionToken = $user->createToken(
            $deviceName,
            [PersonalAccessToken::ABILITY_SELECT_CLINIC],
            now()->addMinutes(self::SELECTION_TOKEN_MINUTES),
        )->plainTextToken;

        return response()->json(['data' => [
            'token' => $selectionToken,
            'requires_clinic_selection' => true,
            'clinics' => $clinics->map(fn ($clinic) => [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'logo_path' => $clinic->logo_path,
            ]),
        ]]);
    }

    public function selectClinic(SelectClinicRequest $request, IssueClinicToken $issueToken): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $token = $issueToken($user, $request->validated('clinic_id'), $request->input('device_name', 'web'));

        $user->currentAccessToken()->delete();

        return response()->json(['data' => ['token' => $token]]);
    }

    public function switchClinic(SelectClinicRequest $request, IssueClinicToken $issueToken): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $token = $issueToken($user, $request->validated('clinic_id'), $request->input('device_name', 'web'));

        $user->currentAccessToken()->delete();

        return response()->json(['data' => ['token' => $token]]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): MeResource
    {
        return new MeResource($request->user());
    }

    /**
     * The user's own active clinics (to switch without logging out).
     * Platform-level data about the user, not about the clinic in context.
     */
    public function myClinics(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $current = $request->attributes->get('clinic');

        $clinics = $user->activeClinics()->orderBy('name')->get(['clinics.id', 'clinics.name', 'clinics.logo_path']);

        return response()->json(['data' => $clinics->map(fn ($clinic) => [
            'id' => $clinic->id,
            'name' => $clinic->name,
            'logo_path' => $clinic->logo_path,
            'is_current' => $clinic->id === $current->id,
        ])]);
    }
}
