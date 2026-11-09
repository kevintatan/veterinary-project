<?php

namespace App\Support;

/**
 * Normalizes phone numbers to E.164 (+573157482096), the key used for WhatsApp.
 * Colombia is the default country: "315 748 2096" and "573157482096" both become
 * "+573157482096". Numbers with an explicit "+" keep their country code.
 */
final class Phone
{
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $hasPlus = str_starts_with(trim($raw), '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($hasPlus) {
            return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+'.$digits : null;
        }

        // Colombian mobile (3xx) or landline with area code (60x).
        if (strlen($digits) === 10 && ($digits[0] === '3' || str_starts_with($digits, '60'))) {
            return '+57'.$digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '57')) {
            return '+'.$digits;
        }

        return null;
    }

    /** "+573186665544" → "+57 318 666 5544" (same as the web's formatPhone). */
    public static function display(?string $e164): ?string
    {
        if ($e164 === null) {
            return null;
        }

        return preg_match('/^\+57(\d{3})(\d{3})(\d{4})$/', $e164, $m) ? "+57 {$m[1]} {$m[2]} {$m[3]}" : $e164;
    }

    public static function isColombianMobile(?string $e164): bool
    {
        return $e164 !== null && preg_match('/^\+573\d{9}$/', $e164) === 1;
    }
}
