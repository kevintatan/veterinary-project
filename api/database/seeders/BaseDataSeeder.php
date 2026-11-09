<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Data every environment needs (production included). Idempotent.
 */
class BaseDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlansSeeder::class,
            RolesAndPermissionsSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
