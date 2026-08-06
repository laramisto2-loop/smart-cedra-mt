<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\District;
use App\Models\Governorate;
use App\Models\PollingCenter;
use App\Models\PollingStation;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class GeographySeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()->each(function (Tenant $tenant): void {
            $this->seedTenantGeography($tenant);
        });
    }

    private function seedTenantGeography(Tenant $tenant): void
    {
        $governorate = Governorate::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'code' => 'LB-BA',
            ],
            [
                'name_en' => 'Beirut',
                'name_ar' => 'بيروت',
            ]
        );

        $district = District::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'code' => 'BEY-D',
            ],
            [
                'governorate_id' => $governorate->id,
                'name_en' => 'Beirut District',
                'name_ar' => 'قضاء بيروت',
            ]
        );

        $area = Area::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'code' => 'ACH',
            ],
            [
                'district_id' => $district->id,
                'name_en' => 'Achrafieh',
                'name_ar' => 'الأشرفية',
                'type' => 'neighbourhood',
            ]
        );

        $pollingCenter = PollingCenter::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'code' => 'ACH-PC-01',
            ],
            [
                'area_id' => $area->id,
                'name_en' => 'Achrafieh Public School',
                'name_ar' => 'مدرسة الأشرفية الرسمية',
                'address_en' => 'Achrafieh, Beirut',
                'address_ar' => 'الأشرفية، بيروت',
            ]
        );

        $stations = [
            [
                'station_number' => '1',
                'name_en' => 'Polling Station 1',
                'name_ar' => 'قلم الاقتراع 1',
                'room' => 'Room 101',
                'registered_voters' => 850,
            ],
            [
                'station_number' => '2',
                'name_en' => 'Polling Station 2',
                'name_ar' => 'قلم الاقتراع 2',
                'room' => 'Room 102',
                'registered_voters' => 790,
            ],
        ];

        foreach ($stations as $station) {
            PollingStation::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'polling_center_id' => $pollingCenter->id,
                    'station_number' => $station['station_number'],
                ],
                [
                    'name_en' => $station['name_en'],
                    'name_ar' => $station['name_ar'],
                    'room' => $station['room'],
                    'registered_voters' => $station['registered_voters'],
                ]
            );
        }
    }
}
