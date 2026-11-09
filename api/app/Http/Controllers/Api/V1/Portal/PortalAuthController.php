<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Actions\Portal\AcceptPortalInvitation;
use App\Actions\Portal\ResetPortalPassword;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\TutorAccount;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Owner portal access (Fase 4e): activate once from the clinic's invitation,
 * then a long session. No codes per login (docs/fase-4.md "Acceso del tutor").
 */
class PortalAuthController extends Controller
{
    /** What the activation page shows before creating the account. */
    public function invitation(string $portalToken, AcceptPortalInvitation $accept, TenantContext $tenant): JsonResponse
    {
        ['invitation' => $invitation, 'owner' => $owner] = $accept->find($portalToken);

        return $tenant->runAs($invitation->clinic_id, fn () => response()->json(['data' => [
            'clinic' => ['name' => Clinic::find($invitation->clinic_id)->name],
            'owner' => ['first_name' => (string) Str::of($owner->full_name)->trim()->explode(' ')->first(), 'full_name' => $owner->full_name, 'email' => $owner->email],
            'phone_hint' => '••• '.substr((string) $owner->mobile_phone, -4),
            'pets' => $owner->pets()->whereNull('deceased_at')->orderBy('name')->pluck('name'),
            'account_exists' => DB::table('tutor_accounts')->where('phone', $owner->mobile_phone)->exists(),
        ]]));
    }

    public function accept(Request $request, string $portalToken, AcceptPortalInvitation $accept): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:tutor_accounts,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], ['email.unique' => 'Ese correo ya tiene una cuenta. Inicia sesión para agregar esta veterinaria.'], trans('fields'));

        $account = $accept->withNewAccount($portalToken, $data);

        return response()->json(['data' => $this->session($account, $data['device_name'] ?? 'portal')], 201);
    }

    /** An existing account (logged in) adds another clinic's invitation. */
    public function link(Request $request, string $portalToken, AcceptPortalInvitation $accept): JsonResponse
    {
        $accept->withExistingAccount($portalToken, $request->user());

        return response()->json(['data' => ['ok' => true]]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [], trans('fields'));

        $account = TutorAccount::where('email', mb_strtolower($data['email']))->first();

        if ($account === null || ! Hash::check($data['password'], $account->password)) {
            throw ValidationException::withMessages(['email' => 'Correo o contraseña incorrectos.']);
        }

        return response()->json(['data' => $this->session($account, $data['device_name'] ?? 'portal')]);
    }

    /** Forgotten password: always the same answer (never reveals whether the email has an account). */
    public function forgotPassword(Request $request, ResetPortalPassword $reset): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']], [], trans('fields'));
        $reset->send($data['email']);

        return response()->json(['data' => ['message' => 'Si ese correo tiene cuenta, te enviamos un código. Revisa también la carpeta de spam.']]);
    }

    public function resetPassword(Request $request, ResetPortalPassword $reset): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [], trans('fields'));

        $account = $reset->reset($data['email'], $data['code'], $data['password']);

        return response()->json(['data' => $this->session($account, $data['device_name'] ?? 'portal')]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var TutorAccount $account */
        $account = $request->user();

        return response()->json(['data' => ['id' => $account->id, 'name' => $account->name, 'email' => $account->email, 'phone' => $account->phone]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    /** @return array{token: string, expires_at: string} */
    private function session(TutorAccount $account, string $device): array
    {
        $account->update(['last_login_at' => now()]);

        return ['token' => $account->issueToken($device), 'expires_at' => now()->addDays(TutorAccount::TOKEN_DAYS)->toIso8601String()];
    }
}
