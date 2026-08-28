<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformAdministratorSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            [
                'email' => 'platform@electoflow.test',
            ],
            [
                'tenant_id' => null,
                'name' => 'ElectoFlow Platform Owner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $user->forceFill([
            'is_platform_admin' => true,
        ])->save();

        $user->roles()->detach();
    }
}
