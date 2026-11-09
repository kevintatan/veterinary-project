<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * The signed-in user's own data (shared across all their clinics).
 */
class ProfileController extends Controller
{
    public function update(Request $request): MeResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:150'],
            'phone' => ['nullable', 'string', 'regex:/^\+\d{8,15}$/'],
            // Tarjeta profesional (COMVEZCOL): printed on prescriptions and clinical records.
            'professional_license' => ['nullable', 'string', 'max:30'],
        ], [], ['name' => 'nombre', 'phone' => 'celular', 'professional_license' => 'tarjeta profesional']);

        $request->user()->update($data);

        return new MeResource($request->user()->fresh());
    }

    /**
     * Changes the password and signs out every other session (other devices
     * keep no access with the old password).
     */
    public function updatePassword(Request $request): Response
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'different:current_password'],
        ], [], ['current_password' => 'contraseña actual', 'password' => 'nueva contraseña']);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'La contraseña actual no es correcta.']);
        }

        $user->update(['password' => $data['password']]);

        $current = $user->currentAccessToken();
        $user->tokens()->when($current?->id, fn ($q, $id) => $q->whereKeyNot($id))->delete();

        return response()->noContent();
    }
}
