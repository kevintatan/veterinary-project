<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Commercial plans (info.md §7). Prices are the proposal; adjust here.
 * Idempotent (upsert by code).
 */
class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'basic',
                'name' => 'Básico',
                'price_monthly' => 59000,
                'features' => ['whatsapp_bot' => false, 'whatsapp_inbox' => false, 'online_booking' => false, 'advanced_reports' => false, 'invoicing' => false],
                'limits' => ['branches' => 1, 'users' => null, 'whatsapp_messages' => 0, 'dian_documents' => 0],
                'sort' => 1,
            ],
            [
                'code' => Plan::PRO,
                'name' => 'Pro',
                'price_monthly' => 99000,
                'features' => ['whatsapp_bot' => true, 'whatsapp_inbox' => false, 'online_booking' => true, 'advanced_reports' => true, 'invoicing' => false],
                'limits' => ['branches' => null, 'users' => null, 'whatsapp_messages' => 500, 'dian_documents' => 0],
                'sort' => 2,
            ],
            [
                'code' => 'pro_invoicing',
                'name' => 'Pro + Facturación',
                'price_monthly' => 149000,
                // whatsapp_inbox (chat with the team) is provisional here: it belongs to the higher plan still to be defined (docs/planes.md).
                'features' => ['whatsapp_bot' => true, 'whatsapp_inbox' => true, 'online_booking' => true, 'advanced_reports' => true, 'invoicing' => true],
                'limits' => ['branches' => null, 'users' => null, 'whatsapp_messages' => 500, 'dian_documents' => 300],
                'sort' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
