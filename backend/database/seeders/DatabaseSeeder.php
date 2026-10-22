<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogoSeeder::class);

        // Las cuentas y datos de ejemplo nunca se cargan en producción.
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
