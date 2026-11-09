<?php

namespace App\Http\Requests\Patients;

use Illuminate\Validation\Validator;

/**
 * A channel can only be authorized if the owner has the data for it.
 */
final class ChannelConsentRules
{
    /**
     * @param  array<string, mixed>  $consents
     */
    public static function check(Validator $validator, array $consents, ?string $mobilePhone, ?string $email): void
    {
        $wants = fn (string $key) => filter_var($consents[$key] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (($wants('whatsapp') || $wants('sms')) && blank($mobilePhone)) {
            $validator->errors()->add('consents', 'Para autorizar WhatsApp o SMS el tutor debe tener celular.');
        }

        if ($wants('email') && blank($email)) {
            $validator->errors()->add('consents', 'Para autorizar correo electrónico el tutor debe tener correo.');
        }
    }
}
