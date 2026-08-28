<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantSeeder::class,
            PlatformAdministratorSeeder::class,
            RbacSeeder::class,
            GeographySeeder::class,
        ]);
    }
}
