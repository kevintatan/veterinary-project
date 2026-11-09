<?php

use App\Http\Middleware\EnsureSubscriptionAllowsWrites;
use App\Http\Middleware\EnsureTutorToken;
use App\Http\Middleware\ResolveClinicFromToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The Next.js server (BFF) calls the API on behalf of visitors and forwards their IP
        // in X-Forwarded-For. Only these addresses are trusted to do so (staging/prod: set TRUSTED_PROXIES).
        $middleware->trustProxies(at: array_filter(explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1'))));

        $middleware->alias([
            'clinic' => ResolveClinicFromToken::class,
            'tutor' => EnsureTutorToken::class,
            'writable' => EnsureSubscriptionAllowsWrites::class,
            'abilities' => CheckAbilities::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // Route model binding ({branch}, {member}…) queries tenant models:
        // the clinic must be in context before bindings are resolved.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveClinicFromToken::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Spatie's 403 message is in English.
        $exceptions->render(fn (UnauthorizedException $e) => response()->json(
            ['message' => 'No tienes permiso para esta acción.'],
            403,
        ));
    })->create();
