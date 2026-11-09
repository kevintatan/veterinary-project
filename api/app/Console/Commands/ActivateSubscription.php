<?php

namespace App\Console\Commands;

use App\Billing\ActivatePlan;
use App\Models\Clinic;
use App\Models\Plan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:activate {clinic : Slug de la clínica} {plan : Código del plan (basic, pro, pro_invoicing)} {--months=1 : Meses pagados} {--reference= : Referencia del pago (transferencia, Nequi…)}')]
#[Description('Activa o renueva el plan de una clínica después de un pago manual (mientras llega Mercado Pago).')]
class ActivateSubscription extends Command
{
    public function handle(ActivatePlan $activate): int
    {
        $clinic = Clinic::where('slug', $this->argument('clinic'))->first();
        $plan = Plan::where('code', $this->argument('plan'))->where('is_active', true)->first();
        $months = (int) $this->option('months');

        if ($clinic === null || $plan === null || $months < 1 || $months > 24) {
            $this->error('Clínica o plan no encontrado, o meses fuera de rango (1 a 24).');

            return self::FAILURE;
        }

        $subscription = $activate($clinic, $plan, $months, 'manual', $this->option('reference'));

        $this->info("{$clinic->name}: plan {$plan->name} activo hasta el {$subscription->current_period_end->timezone('America/Bogota')->format('d/m/Y')}.");

        return self::SUCCESS;
    }
}
