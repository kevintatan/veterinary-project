<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\Invitation;
use App\Models\Owner;
use App\Models\Schedule;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Primeros pasos" for a new clinic: what is still missing to start working.
 * Each step is checked against real data, so it ticks itself off.
 */
class OnboardingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $clinic = $this->clinic($request);

        $steps = [
            ['key' => 'clinic', 'label' => 'Completa los datos de la clínica', 'hint' => 'NIT, dirección y teléfono: salen en los PDF y consentimientos.', 'href' => '/app/configuracion?seccion=clinica', 'done' => filled($clinic->tax_id) && filled($clinic->address) && filled($clinic->phone)],
            ['key' => 'logo', 'label' => 'Sube el logo', 'hint' => 'Para el carné de las mascotas, los PDF y los documentos.', 'href' => '/app/configuracion?seccion=clinica', 'done' => $clinic->logo_path !== null],
            ['key' => 'schedule', 'label' => 'Define el horario de atención', 'hint' => 'La agenda sugiere horas libres según el horario.', 'href' => '/app/configuracion?seccion=horarios', 'done' => Schedule::exists()],
            ['key' => 'services', 'label' => 'Crea tus servicios', 'hint' => 'Consulta, vacunación, peluquería… con su duración y precio.', 'href' => '/app/configuracion?seccion=servicios', 'done' => Service::exists()],
            ['key' => 'team', 'label' => 'Invita a tu equipo', 'hint' => 'Veterinarios y recepción, cada uno con su rol.', 'href' => '/app/configuracion?seccion=equipo', 'done' => ClinicMembership::count() > 1 || Invitation::exists()],
            ['key' => 'owner', 'label' => 'Registra tu primer tutor y su mascota', 'hint' => 'Con su autorización de datos (Ley 1581).', 'href' => '/app/tutores', 'done' => Owner::exists()],
        ];

        $done = collect($steps)->where('done', true)->count();

        return response()->json(['data' => [
            'steps' => $steps,
            'done' => $done,
            'total' => count($steps),
            // Hidden when finished or dismissed by an admin.
            'visible' => $done < count($steps) && empty($clinic->settings['onboarding_dismissed_at'] ?? null),
        ]]);
    }

    public function dismiss(Request $request): JsonResponse
    {
        $clinic = $this->clinic($request);
        $clinic->update(['settings' => array_merge($clinic->settings ?? [], ['onboarding_dismissed_at' => now()->toIso8601String()])]);

        return $this->show($request);
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
