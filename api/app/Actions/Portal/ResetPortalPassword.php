<?php

namespace App\Actions\Portal;

use App\Models\TutorAccount;
use App\Notifications\TutorPasswordCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Forgotten password in the owner portal (Fase 4e): a 6-digit code by email
 * (free; WhatsApp would be a paid "authentication" message), valid 15 min,
 * 5 attempts. Never tells whether an email has an account.
 */
class ResetPortalPassword
{
    public const MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    /** Sends a code if the account exists; silently does nothing otherwise. */
    public function send(string $email): void
    {
        $account = TutorAccount::where('email', mb_strtolower($email))->first();
        if ($account === null) {
            return;
        }

        $code = (string) random_int(100000, 999999);

        DB::transaction(function () use ($account, $code) {
            // Only the latest code is valid.
            DB::table('tutor_login_codes')->where('tutor_account_id', $account->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            DB::table('tutor_login_codes')->insert([
                'id' => (string) Str::uuid7(),
                'tutor_account_id' => $account->id,
                'purpose' => 'password_reset',
                'channel' => 'email',
                'code_hash' => self::hash($code),
                'expires_at' => now()->addMinutes(self::MINUTES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Notification::route('mail', $account->email)->notify(new TutorPasswordCodeNotification($account->name, $code, self::MINUTES));
    }

    /** Checks the code, sets the new password and closes every other session. */
    public function reset(string $email, string $code, string $password): TutorAccount
    {
        $account = TutorAccount::where('email', mb_strtolower($email))->first();
        $invalid = ValidationException::withMessages(['code' => 'El código no es válido o ya venció. Pide uno nuevo.']);

        if ($account === null) {
            throw $invalid;
        }

        // The transaction returns the outcome instead of throwing inside it: a failed attempt
        // must stay counted (an exception would roll the counter back).
        $outcome = DB::transaction(function () use ($account, $code, $password) {
            $row = DB::table('tutor_login_codes')
                ->where('tutor_account_id', $account->id)
                ->where('purpose', 'password_reset')
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($row === null || $row->attempts >= self::MAX_ATTEMPTS) {
                return 'invalid';
            }

            if (! hash_equals($row->code_hash, self::hash($code))) {
                DB::table('tutor_login_codes')->where('id', $row->id)->increment('attempts');

                return 'mismatch';
            }

            DB::table('tutor_login_codes')->where('id', $row->id)->update(['consumed_at' => now(), 'updated_at' => now()]);
            $account->update(['password' => $password]);
            $account->tokens()->delete(); // whoever had access before, no longer does

            return 'ok';
        });

        return match ($outcome) {
            'ok' => $account,
            'mismatch' => throw ValidationException::withMessages(['code' => 'El código no coincide.']),
            default => throw $invalid,
        };
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
