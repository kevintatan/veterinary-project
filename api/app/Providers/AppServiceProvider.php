<?php

namespace App\Providers;

use App\Messaging\WhatsApp\FakeWhatsAppProvider;
use App\Messaging\WhatsApp\MetaWhatsAppProvider;
use App\Messaging\WhatsApp\WhatsAppProvider;
use App\Models\Appointment;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Owner;
use App\Models\Pet;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // WhatsApp provider by driver (config/whatsapp.php): fake until the WABA is connected.
        $this->app->bind(WhatsAppProvider::class, fn () => match (config('whatsapp.driver')) {
            'meta' => new MetaWhatsAppProvider,
            default => new FakeWhatsAppProvider,
        });
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();

        // Stable names in *_type columns, so renaming a PHP class never breaks stored data.
        Relation::morphMap([
            'clinical_record' => ClinicalRecord::class,
            'pet' => Pet::class,
            'owner' => Owner::class,
            'appointment' => Appointment::class,
        ]);
    }

    private function configureRateLimiting(): void
    {
        // Brute force protection: per email+IP and per IP.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        // Portal password codes: a few per email and per IP (each one is an email sent).
        RateLimiter::for('password-code', fn (Request $request) => [
            Limit::perHour(5)->by('code|'.Str::lower((string) $request->input('email'))),
            Limit::perHour(20)->by('code-ip|'.$request->ip()),
        ]);

        // Trying codes: the code itself locks after 5 wrong attempts; this caps guessing across emails.
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(15)->by('reset-ip|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        // Public invitation endpoints: slow down token guessing.
        RateLimiter::for('invitations', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Public pet card (QR): generous for real use (a vet scanning), tight for token guessing.
        // Public booking page: browsing times vs sending requests (docs/reservas.md).
        RateLimiter::for('booking-browse', fn (Request $request) => Limit::perMinute(60)->by('booking|'.$request->ip()));
        RateLimiter::for('booking-request', fn (Request $request) => [
            Limit::perMinute(8)->by('booking-req|'.$request->ip()),
            Limit::perDay(40)->by('booking-req-day|'.$request->ip()),
        ]);
        RateLimiter::for('public-card', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
