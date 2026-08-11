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
