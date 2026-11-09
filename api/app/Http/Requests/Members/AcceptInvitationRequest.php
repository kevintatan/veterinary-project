<?php

namespace App\Http\Requests\Members;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }
}
