<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenantA = Tenant::updateOrCreate(
            ['slug' => 'cedra-campaign'],
            [
                'name' => 'Cedra Campaign',
                'status' => 'active',
            ]
        );

        $tenantA->settings()->updateOrCreate(
            [],
            [
                'brand_name' => 'Cedra Campaign',
                'primary_color' => '#0d6efd',
                'timezone' => 'Asia/Beirut',
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@cedra.test'],
            [
                'tenant_id' => $tenantA->id,
                'name' => 'Cedra Admin',
                'password' => Hash::make('password'),
            ]
        );

        $tenantB = Tenant::updateOrCreate(
            ['slug' => 'lebanon-future'],
            [
                'name' => 'Lebanon Future Campaign',
                'status' => 'active',
            ]
        );

        $tenantB->settings()->updateOrCreate(
            [],
            [
                'brand_name' => 'Lebanon Future',
                'primary_color' => '#198754',
                'timezone' => 'Asia/Beirut',
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@future.test'],
            [
                'tenant_id' => $tenantB->id,
                'name' => 'Future Admin',
                'password' => Hash::make('password'),
            ]
        );
    }
}