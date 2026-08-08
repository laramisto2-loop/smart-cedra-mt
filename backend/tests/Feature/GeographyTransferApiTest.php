<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\District;
use App\Models\Governorate;
use App\Models\PollingCenter;
use App\Models\PollingStation;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class GeographyTransferApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            TenantSeeder::class,
            RbacSeeder::class,
        ]);
    }

    public function test_unauthenticated_user_cannot_download_geography_files(): void
    {
        $this->getJson(
            '/api/geography/transfers/governorates/template'
        )->assertUnauthorized();

        $this->getJson(
            '/api/geography/transfers/governorates/export'
        )->assertUnauthorized();
    }

    public function test_user_without_geography_permission_cannot_download_files(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user)
            ->get(
                '/api/geography/transfers/governorates/template'
            )
            ->assertForbidden();

        $this->get(
            '/api/geography/transfers/governorates/export'
        )->assertForbidden();
    }

    public function test_admin_can_download_header_only_templates_for_all_types(): void
    {
        $admin = $this->cedraAdmin();

        $expectedHeaders = [
            'governorates' => [
                'code',
                'name_en',
                'name_ar',
            ],
            'districts' => [
                'governorate_code',
                'code',
                'name_en',
                'name_ar',
            ],
            'areas' => [
                'district_code',
                'code',
                'name_en',
                'name_ar',
                'type',
                'latitude',
                'longitude',
            ],
            'polling-centers' => [
                'area_code',
                'code',
                'name_en',
                'name_ar',
                'address_en',
                'address_ar',
                'latitude',
                'longitude',
            ],
            'polling-stations' => [
                'polling_center_code',
                'station_number',
                'name_en',
                'name_ar',
                'room',
                'registered_voters',
            ],
        ];

        foreach ($expectedHeaders as $type => $headers) {
            $response = $this->actingAs($admin)->get(
                "/api/geography/transfers/{$type}/template"
            );

            $response
                ->assertOk()
                ->assertDownload(
                    "electoflow-{$type}-template.csv"
                )
                ->assertHeader(
                    'content-type',
                    'text/csv; charset=UTF-8'
                )
                ->assertHeader(
                    'x-content-type-options',
                    'nosniff'
                );

            $this->assertSame(
                [$headers],
                $this->csvRows($response)
            );
        }
    }

    public function test_exports_include_parent_codes_and_only_active_tenant_data(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $this->createHierarchy($cedraTenant, 'CED');
        $this->createHierarchy($futureTenant, 'FUT');

        $admin = $this->cedraAdmin();

        $expectedRows = [
            'governorates' => [
                ['code', 'name_en', 'name_ar'],
                [
                    'CED-GOV',
                    'CED Governorate',
                    'CED Governorate Arabic',
                ],
            ],
            'districts' => [
                [
                    'governorate_code',
                    'code',
                    'name_en',
                    'name_ar',
                ],
                [
                    'CED-GOV',
                    'CED-DIST',
                    'CED District',
                    'CED District Arabic',
                ],
            ],
            'areas' => [
                [
                    'district_code',
                    'code',
                    'name_en',
                    'name_ar',
                    'type',
                    'latitude',
                    'longitude',
                ],
                [
                    'CED-DIST',
                    'CED-AREA',
                    'CED Area',
                    'CED Area Arabic',
                    'city',
                    '',
                    '',
                ],
            ],
            'polling-centers' => [
                [
                    'area_code',
                    'code',
                    'name_en',
                    'name_ar',
                    'address_en',
                    'address_ar',
                    'latitude',
                    'longitude',
                ],
                [
                    'CED-AREA',
                    'CED-CENTER',
                    'CED Center',
                    'CED Center Arabic',
                    '',
                    '',
                    '',
                    '',
                ],
            ],
            'polling-stations' => [
                [
                    'polling_center_code',
                    'station_number',
                    'name_en',
                    'name_ar',
                    'room',
                    'registered_voters',
                ],
                [
                    'CED-CENTER',
                    '7',
                    '',
                    '',
                    'Room 7',
                    '700',
                ],
            ],
        ];

        foreach ($expectedRows as $type => $rows) {
            $response = $this->actingAs($admin)->get(
                "/api/geography/transfers/{$type}/export"
            );

            $response
                ->assertOk()
                ->assertDownload(
                    "electoflow-{$type}-export.csv"
                );

            $this->assertSame(
                $rows,
                $this->csvRows($response)
            );

            $this->assertStringNotContainsString(
                'FUT-',
                $response->streamedContent()
            );
        }
    }

    public function test_export_protects_spreadsheet_users_from_formula_values(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        Governorate::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'code' => 'SAFE',
            'name_en' => '=2+3',
            'name_ar' => '@SUM(1+1)',
        ]);

        $response = $this->actingAs($this->cedraAdmin())
            ->get(
                '/api/geography/transfers/governorates/export'
            )
            ->assertOk();

        $rows = $this->csvRows($response);

        $this->assertSame(
            [
                'SAFE',
                "'=2+3",
                "'@SUM(1+1)",
            ],
            $rows[1]
        );
    }

    public function test_unsupported_geography_transfer_type_returns_not_found(): void
    {
        $this->actingAs($this->cedraAdmin())
            ->get(
                '/api/geography/transfers/unknown/template'
            )
            ->assertNotFound();

        $this->get(
            '/api/geography/transfers/unknown/export'
        )->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_preview_geography_import(): void
    {
        $file = $this->csvFile(
            'governorates.csv',
            "code,name_en,name_ar\nNEW,New,جديد"
        );

        $this->withHeader('Accept', 'application/json')
            ->post(
                '/api/geography/transfers/governorates/preview',
                ['file' => $file]
            )
            ->assertUnauthorized();
    }

    public function test_user_without_geography_permission_cannot_preview_import(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $file = $this->csvFile(
            'governorates.csv',
            "code,name_en,name_ar\nNEW,New,جديد"
        );

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(
                '/api/geography/transfers/governorates/preview',
                ['file' => $file]
            )
            ->assertForbidden();
    }

    public function test_preview_classifies_creates_and_updates_without_writing(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $this->createHierarchy($tenant, 'CED');

        $file = $this->csvFile(
            'governorates.csv',
            implode("\n", [
                'code,name_en,name_ar',
                'CED-GOV,Updated Governorate,Updated Arabic',
                'NEW-GOV,New Governorate,New Arabic',
            ])
        );

        $this->actingAs($this->cedraAdmin())
            ->withHeader('Accept', 'application/json')
            ->post(
                '/api/geography/transfers/governorates/preview',
                ['file' => $file]
            )
            ->assertOk()
            ->assertJsonPath('data.type', 'governorates')
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.create', 1)
            ->assertJsonPath('data.summary.update', 1)
            ->assertJsonPath('data.summary.invalid', 0)
            ->assertJsonPath('data.rows.0.status', 'update')
            ->assertJsonPath('data.rows.1.status', 'create');

        $this->assertDatabaseHas('governorates', [
            'tenant_id' => $tenant->id,
            'code' => 'CED-GOV',
            'name_en' => 'CED Governorate',
        ]);

        $this->assertDatabaseMissing('governorates', [
            'tenant_id' => $tenant->id,
            'code' => 'NEW-GOV',
        ]);
    }

    public function test_preview_rejects_invalid_parents_values_and_duplicates(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $this->createHierarchy($cedraTenant, 'CED');
        $this->createHierarchy($futureTenant, 'FUT');

        $file = $this->csvFile(
            'areas.csv',
            implode("\n", [
                'district_code,code,name_en,name_ar,type,latitude,longitude',
                'CED-DIST,CED-NEW-AREA,New Area,New Arabic,city,33.8,35.5',
                'FUT-DIST,CED-FOREIGN-AREA,Foreign Area,Foreign Arabic,invalid,91,181',
                'CED-DIST,CED-NEW-AREA,Duplicate Area,Duplicate Arabic,town,,',
            ])
        );

        $this->actingAs($this->cedraAdmin())
            ->withHeader('Accept', 'application/json')
            ->post(
                '/api/geography/transfers/areas/preview',
                ['file' => $file]
            )
            ->assertOk()
            ->assertJsonPath('data.summary.total', 3)
            ->assertJsonPath('data.summary.create', 1)
            ->assertJsonPath('data.summary.update', 0)
            ->assertJsonPath('data.summary.invalid', 2)
            ->assertJsonPath(
                'data.rows.1.status',
                'invalid'
            )
            ->assertJsonPath(
                'data.rows.2.status',
                'invalid'
            )
            ->assertJsonStructure([
                'data' => [
                    'rows' => [
                        1 => [
                            'errors' => [
                                'district_code',
                                'type',
                                'latitude',
                                'longitude',
                            ],
                        ],
                        2 => [
                            'errors' => [
                                '_row',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseMissing('areas', [
            'tenant_id' => $cedraTenant->id,
            'code' => 'CED-NEW-AREA',
        ]);

        $this->assertDatabaseMissing('areas', [
            'tenant_id' => $cedraTenant->id,
            'code' => 'CED-FOREIGN-AREA',
        ]);
    }

    public function test_preview_rejects_incorrect_csv_headers(): void
    {
        $file = $this->csvFile(
            'governorates.csv',
            "name_en,code,name_ar\nNew,NEW,جديد"
        );

        $this->actingAs($this->cedraAdmin())
            ->withHeader('Accept', 'application/json')
            ->post(
                '/api/geography/transfers/governorates/preview',
                ['file' => $file]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_preview_requires_a_csv_file(): void
    {
        $file = $this->csvFile(
            'governorates.txt',
            "code,name_en,name_ar\nNEW,New,جديد"
        );

        $this->actingAs($this->cedraAdmin())
            ->withHeader('Accept', 'application/json')
            ->post(
                '/api/geography/transfers/governorates/preview',
                ['file' => $file]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    private function csvFile(
        string $name,
        string $contents
    ): UploadedFile {
        return UploadedFile::fake()->createWithContent(
            $name,
            $contents
        );
    }

    private function cedraAdmin(): User
    {
        return User::query()
            ->where('email', 'admin@cedra.test')
            ->firstOrFail();
    }

    private function findTenant(string $slug): Tenant
    {
        return Tenant::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function createHierarchy(
        Tenant $tenant,
        string $prefix
    ): array {
        $governorate = Governorate::withoutGlobalScopes()
            ->create([
                'tenant_id' => $tenant->id,
                'code' => "{$prefix}-GOV",
                'name_en' => "{$prefix} Governorate",
                'name_ar' => "{$prefix} Governorate Arabic",
            ]);

        $district = District::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'governorate_id' => $governorate->id,
            'code' => "{$prefix}-DIST",
            'name_en' => "{$prefix} District",
            'name_ar' => "{$prefix} District Arabic",
        ]);

        $area = Area::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'district_id' => $district->id,
            'code' => "{$prefix}-AREA",
            'name_en' => "{$prefix} Area",
            'name_ar' => "{$prefix} Area Arabic",
            'type' => 'city',
        ]);

        $pollingCenter = PollingCenter::withoutGlobalScopes()
            ->create([
                'tenant_id' => $tenant->id,
                'area_id' => $area->id,
                'code' => "{$prefix}-CENTER",
                'name_en' => "{$prefix} Center",
                'name_ar' => "{$prefix} Center Arabic",
            ]);

        $pollingStation = PollingStation::withoutGlobalScopes()
            ->create([
                'tenant_id' => $tenant->id,
                'polling_center_id' => $pollingCenter->id,
                'station_number' => '7',
                'room' => 'Room 7',
                'registered_voters' => 700,
            ]);

        return compact(
            'governorate',
            'district',
            'area',
            'pollingCenter',
            'pollingStation'
        );
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function csvRows(TestResponse $response): array
    {
        $content = $response->streamedContent();

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException(
                'The test CSV stream could not be opened.'
            );
        }

        fwrite($stream, $content);
        rewind($stream);

        $rows = [];

        while (
            ($row = fgetcsv(
                $stream,
                null,
                ',',
                '"',
                ''
            )) !== false
        ) {
            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }
}
