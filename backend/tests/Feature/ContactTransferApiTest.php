<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class ContactTransferApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    private const HEADERS = [
        'reference_code',
        'first_name',
        'last_name',
        'name_ar',
        'phone',
        'email',
        'address',
        'area_code',
        'preferred_language',
        'preferred_channel',
        'status',
        'source',
        'notes',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            TenantSeeder::class,
            RbacSeeder::class,
            GeographySeeder::class,
        ]);
    }

    public function test_unauthenticated_user_cannot_download_contact_files(): void
    {
        $this->getJson(
            '/api/contacts/transfers/template'
        )->assertUnauthorized();

        $this->getJson(
            '/api/contacts/transfers/export'
        )->assertUnauthorized();
    }

    public function test_user_without_contact_transfer_permissions_is_forbidden(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user)
            ->get('/api/contacts/transfers/template')
            ->assertForbidden();

        $this->get('/api/contacts/transfers/export')
            ->assertForbidden();
    }

    public function test_admin_can_download_header_only_contact_template(): void
    {
        $response = $this->actingAs($this->cedraAdmin())
            ->get('/api/contacts/transfers/template');

        $response
            ->assertOk()
            ->assertDownload(
                'electoflow-contacts-template.csv'
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
            [self::HEADERS],
            $this->csvRows($response)
        );
    }

    public function test_export_contains_only_active_tenant_contacts_and_area_codes(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $cedraArea = $this->findArea($cedraTenant);
        $futureArea = $this->findArea($futureTenant);

        $this->createContact(
            $cedraTenant,
            $cedraArea,
            'CEDRA-EXPORT-1',
            [
                'first_name' => 'Maya',
                'last_name' => 'Haddad',
                'name_ar' => 'مايا حداد',
                'phone' => '+96170555010',
                'email' => 'maya@example.test',
                'address' => 'Achrafieh, Beirut',
                'preferred_language' => 'ar',
                'preferred_channel' => 'whatsapp',
                'status' => 'active',
                'source' => 'manual',
                'notes' => 'Fictional export test contact.',
            ]
        );

        $this->createContact(
            $futureTenant,
            $futureArea,
            'FUTURE-EXPORT-1',
            [
                'first_name' => 'Future',
                'last_name' => 'Contact',
            ]
        );

        $response = $this->actingAs($this->cedraAdmin())
            ->get('/api/contacts/transfers/export');

        $response
            ->assertOk()
            ->assertDownload(
                'electoflow-contacts-export.csv'
            );

        $rows = $this->csvRows($response);

        $this->assertCount(2, $rows);
        $this->assertSame(self::HEADERS, $rows[0]);

        $this->assertSame([
            'CEDRA-EXPORT-1',
            'Maya',
            'Haddad',
            'مايا حداد',
            "'+96170555010",
            'maya@example.test',
            'Achrafieh, Beirut',
            $cedraArea->code,
            'ar',
            'whatsapp',
            'active',
            'manual',
            'Fictional export test contact.',
        ], $rows[1]);

        $this->assertStringNotContainsString(
            'FUTURE-EXPORT-1',
            $response->streamedContent()
        );
    }

    public function test_export_protects_spreadsheet_users_from_formula_values(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $area = $this->findArea($tenant);

        $this->createContact(
            $tenant,
            $area,
            'FORMULA-TEST',
            [
                'first_name' => '=2+3',
                'last_name' => '+SUM(1,1)',
                'name_ar' => '@command',
                'address' => '-danger',
            ]
        );

        $response = $this->actingAs($this->cedraAdmin())
            ->get('/api/contacts/transfers/export')
            ->assertOk();

        $rows = $this->csvRows($response);

        $this->assertSame("'=2+3", $rows[1][1]);
        $this->assertSame("'+SUM(1,1)", $rows[1][2]);
        $this->assertSame("'@command", $rows[1][3]);
        $this->assertSame("'-danger", $rows[1][6]);
    }

    public function test_unauthenticated_user_cannot_preview_contact_import(): void
    {
        $file = $this->csvFile(
            'contacts.csv',
            implode(',', self::HEADERS)."\n"
            .'NEW-1,New,Contact,,,,,,en,,active,,'
        );

        $this->withHeader('Accept', 'application/json')
            ->post(
                '/api/contacts/transfers/preview',
                ['file' => $file]
            )
            ->assertUnauthorized();
    }

    public function test_user_without_contact_import_permission_cannot_preview(): void
    {
        $tenant = $this->findTenant('cedra-campaign');

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $file = $this->csvFile(
            'contacts.csv',
            implode(',', self::HEADERS)."\n"
            .'NEW-1,New,Contact,,,,,,en,,active,,'
        );

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(
                '/api/contacts/transfers/preview',
                ['file' => $file]
            )
            ->assertForbidden();
    }

    public function test_preview_classifies_creates_and_updates_without_writing(): void
    {
        $tenant = $this->findTenant('cedra-campaign');
        $area = $this->findArea($tenant);

        $this->createContact(
            $tenant,
            $area,
            'PREVIEW-EXISTING',
            [
                'first_name' => 'Original',
                'last_name' => 'Contact',
            ]
        );

        $headers = implode(',', self::HEADERS);

        $existingRow = implode(',', [
            'PREVIEW-EXISTING',
            'Updated',
            'Contact',
            '',
            "'+96170111111",
            'updated@example.test',
            '',
            $area->code,
            'ar',
            'whatsapp',
            'active',
            'csv',
            'Updated preview row',
        ]);

        $newRow = implode(',', [
            'PREVIEW-NEW',
            'New',
            'Contact',
            '',
            '',
            'new@example.test',
            '',
            $area->code,
            'en',
            'email',
            'active',
            'csv',
            'New preview row',
        ]);

        $file = $this->csvFile(
            'contacts.csv',
            implode("\n", [
                $headers,
                $existingRow,
                $newRow,
            ])
        );

        $this->actingAs($this->cedraAdmin())
            ->withHeader('Accept', 'application/json')
            ->post(
                '/api/contacts/transfers/preview',
                ['file' => $file]
            )
            ->assertOk()
            ->assertJsonPath('data.type', 'contacts')
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.create', 1)
            ->assertJsonPath('data.summary.update', 1)
            ->assertJsonPath('data.summary.invalid', 0)
            ->assertJsonPath('data.rows.0.status', 'update')
            ->assertJsonPath(
                'data.rows.0.data.phone',
                '+96170111111'
            )
            ->assertJsonPath('data.rows.1.status', 'create');

        $this->assertDatabaseHas('contacts', [
            'tenant_id' => $tenant->id,
            'reference_code' => 'PREVIEW-EXISTING',
            'first_name' => 'Original',
        ]);

        $this->assertDatabaseMissing('contacts', [
            'tenant_id' => $tenant->id,
            'reference_code' => 'PREVIEW-NEW',
        ]);
    }

    public function test_preview_rejects_invalid_values_foreign_areas_and_duplicates(): void
    {
        $cedraTenant = $this->findTenant('cedra-campaign');
        $futureTenant = $this->findTenant('lebanon-future');

        $cedraArea = $this->findArea($cedraTenant);
        $futureArea = $this->findArea($futureTenant);

        $futureArea->code = 'FUTURE-ONLY-AREA';
        $futureArea->save();

        $headers = implode(',', self::HEADERS);

        $invalidRow = implode(',', [
            'INVALID-1',
            '',
            'Contact',
            '',
            '',
            'not-an-email',
            '',
            $futureArea->code,
            'fr',
            'pigeon',
            'unknown',
            'csv',
            'Invalid row',
        ]);

        $duplicateRow = implode(',', [
            'INVALID-1',
            'Duplicate',
            'Contact',
            '',
            '',
            'duplicate@example.test',
            '',
            $cedraArea->code,
            'en',
            'email',
            'active',
            'csv',
            'Duplicate reference',
        ]);

        $file = $this->csvFile(
            'contacts.csv',
            implode("\n", [
                $headers,
                $invalidRow,
                $duplicateRow,
            ])
        );

        $this->actingAs($this->cedraAdmin())
            ->withHeader('Accept', 'application/json')
            ->post(
                '/api/contacts/transfers/preview',
                ['file' => $file]
            )
            ->assertOk()
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.create', 0)
            ->assertJsonPath('data.summary.update', 0)
            ->assertJsonPath('data.summary.invalid', 2)
            ->assertJsonPath('data.rows.0.status', 'invalid')
            ->assertJsonPath('data.rows.1.status', 'invalid')
            ->assertJsonStructure([
                'data' => [
                    'rows' => [
                        0 => [
                            'errors' => [
                                'first_name',
                                'email',
                                'area_code',
                                'preferred_language',
                                'preferred_channel',
                                'status',
                            ],
                        ],
                        1 => [
                            'errors' => [
                                '_row',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseMissing('contacts', [
            'tenant_id' => $cedraTenant->id,
            'reference_code' => 'INVALID-1',
        ]);
    }

    public function test_preview_rejects_incorrect_contact_csv_headers(): void
    {
        $file = $this->csvFile(
            'contacts.csv',
            "first_name,reference_code,last_name\nNew,NEW-1,Contact"
        );

        $this->actingAs($this->cedraAdmin())
            ->withHeader('Accept', 'application/json')
            ->post(
                '/api/contacts/transfers/preview',
                ['file' => $file]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_preview_requires_a_contact_csv_file(): void
    {
        $file = $this->csvFile(
            'contacts.txt',
            implode(',', self::HEADERS)."\n"
            .'NEW-1,New,Contact,,,,,,en,,active,,'
        );

        $this->actingAs($this->cedraAdmin())
            ->withHeader('Accept', 'application/json')
            ->post(
                '/api/contacts/transfers/preview',
                ['file' => $file]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
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

    private function findArea(Tenant $tenant): Area
    {
        return Area::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createContact(
        Tenant $tenant,
        Area $area,
        string $referenceCode,
        array $overrides = []
    ): Contact {
        return Contact::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'area_id' => $area->id,
            'reference_code' => $referenceCode,
            'first_name' => 'Test',
            'last_name' => 'Contact',
            'preferred_language' => 'en',
            'status' => 'active',
            ...$overrides,
        ]);
    }

    private function csvFile(
        string $name,
        string $contents
    ): UploadedFile {
        return UploadedFile::fake()
            ->createWithContent(
                $name,
                $contents
            );
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function csvRows(TestResponse $response): array
    {
        $content = $response->streamedContent();

        $this->assertTrue(
            str_starts_with($content, "\xEF\xBB\xBF"),
            'The CSV must begin with a UTF-8 byte-order mark.'
        );

        $content = substr($content, 3);

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
