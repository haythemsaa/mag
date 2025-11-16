<?php

namespace Tests\Feature;

use App\Models\AccountingExport;
use App\Models\Cost;
use App\Models\FuelTransaction;
use App\Models\Maintenance;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountingExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;
    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organization
        $this->organization = Organization::factory()->create();

        // Create user
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create vehicle
        $this->vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->user);

        // Fake storage
        Storage::fake('local');
    }

    /** @test */
    public function it_can_list_exports()
    {
        AccountingExport::factory()
            ->count(3)
            ->forOrganization($this->organization)
            ->create();

        $response = $this->getJson('/api/accounting-exports');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'export_number',
                        'type',
                        'format',
                        'status',
                        'start_date',
                        'end_date',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_filter_exports_by_status()
    {
        AccountingExport::factory()
            ->forOrganization($this->organization)
            ->completed()
            ->count(2)
            ->create();

        AccountingExport::factory()
            ->forOrganization($this->organization)
            ->pending()
            ->create();

        $response = $this->getJson('/api/accounting-exports?status=completed');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_can_create_an_export()
    {
        $data = [
            'type' => 'costs',
            'format' => 'csv',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ];

        $response = $this->postJson('/api/accounting-exports', $data);

        $response->assertCreated()
            ->assertJsonFragment([
                'type' => 'costs',
                'format' => 'csv',
            ]);

        $this->assertDatabaseHas('accounting_exports', [
            'organization_id' => $this->organization->id,
            'type' => 'costs',
            'format' => 'csv',
        ]);
    }

    /** @test */
    public function it_validates_export_creation()
    {
        $response = $this->postJson('/api/accounting-exports', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'format', 'start_date', 'end_date']);
    }

    /** @test */
    public function it_validates_end_date_after_start_date()
    {
        $data = [
            'type' => 'costs',
            'format' => 'csv',
            'start_date' => '2025-01-31',
            'end_date' => '2025-01-01',
        ];

        $response = $this->postJson('/api/accounting-exports', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    /** @test */
    public function it_can_show_an_export()
    {
        $export = AccountingExport::factory()
            ->forOrganization($this->organization)
            ->create();

        $response = $this->getJson("/api/accounting-exports/{$export->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $export->id,
                'export_number' => $export->export_number,
            ]);
    }

    /** @test */
    public function it_can_delete_an_export()
    {
        $export = AccountingExport::factory()
            ->forOrganization($this->organization)
            ->create();

        $response = $this->deleteJson("/api/accounting-exports/{$export->id}");

        $response->assertOk();

        $this->assertSoftDeleted('accounting_exports', ['id' => $export->id]);
    }

    /** @test */
    public function it_can_get_default_account_mapping()
    {
        $response = $this->getJson('/api/accounting-exports/default-account-mapping');

        $response->assertOk()
            ->assertJsonStructure([
                'account_mapping' => [
                    'fuel_account',
                    'maintenance_account',
                    'insurance_account',
                ],
            ]);
    }

    /** @test */
    public function it_can_get_export_statistics()
    {
        AccountingExport::factory()
            ->forOrganization($this->organization)
            ->completed()
            ->count(5)
            ->create();

        AccountingExport::factory()
            ->forOrganization($this->organization)
            ->pending()
            ->count(2)
            ->create();

        $response = $this->getJson('/api/accounting-exports/statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'total_exports',
                'by_status',
                'by_type',
                'by_format',
                'total_records_exported',
                'success_rate',
            ]);
    }

    /** @test */
    public function it_can_preview_export_data()
    {
        // Create some costs for preview
        Cost::factory()
            ->count(5)
            ->create([
                'organization_id' => $this->organization->id,
                'vehicle_id' => $this->vehicle->id,
                'cost_date' => '2025-01-15',
            ]);

        $data = [
            'type' => 'costs',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ];

        $response = $this->postJson('/api/accounting-exports/preview', $data);

        $response->assertOk()
            ->assertJsonStructure([
                'preview',
                'total_records',
                'showing',
                'date_range',
            ]);
    }

    /** @test */
    public function it_validates_preview_data()
    {
        $response = $this->postJson('/api/accounting-exports/preview', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'start_date', 'end_date']);
    }

    /** @test */
    public function it_cannot_view_exports_from_other_organizations()
    {
        $otherOrganization = Organization::factory()->create();
        $otherExport = AccountingExport::factory()
            ->forOrganization($otherOrganization)
            ->create();

        $response = $this->getJson("/api/accounting-exports/{$otherExport->id}");

        $response->assertForbidden();
    }

    /** @test */
    public function it_cannot_delete_exports_from_other_organizations()
    {
        $otherOrganization = Organization::factory()->create();
        $otherExport = AccountingExport::factory()
            ->forOrganization($otherOrganization)
            ->create();

        $response = $this->deleteJson("/api/accounting-exports/{$otherExport->id}");

        $response->assertForbidden();
    }

    /** @test */
    public function it_auto_generates_export_number()
    {
        $export = AccountingExport::factory()
            ->forOrganization($this->organization)
            ->create();

        $this->assertNotNull($export->export_number);
        $this->assertStringStartsWith('EXP-', $export->export_number);
        $this->assertStringContainsString((string) now()->year, $export->export_number);
    }

    /** @test */
    public function it_can_mark_export_as_started()
    {
        $export = AccountingExport::factory()
            ->forOrganization($this->organization)
            ->pending()
            ->create();

        $export->markAsStarted();

        $this->assertEquals('processing', $export->status);
        $this->assertNotNull($export->started_at);
    }

    /** @test */
    public function it_can_mark_export_as_completed()
    {
        $export = AccountingExport::factory()
            ->forOrganization($this->organization)
            ->processing()
            ->create();

        $filePath = 'exports/test.csv';
        $recordsExported = 100;
        $fileSizeBytes = 5000;

        $export->markAsCompleted($filePath, $recordsExported, $fileSizeBytes);

        $this->assertEquals('completed', $export->status);
        $this->assertEquals($filePath, $export->file_path);
        $this->assertEquals($recordsExported, $export->records_exported);
        $this->assertEquals($fileSizeBytes, $export->file_size_bytes);
        $this->assertNotNull($export->completed_at);
    }

    /** @test */
    public function it_can_mark_export_as_failed()
    {
        $export = AccountingExport::factory()
            ->forOrganization($this->organization)
            ->processing()
            ->create();

        $errorMessage = 'Database connection timeout';

        $export->markAsFailed($errorMessage);

        $this->assertEquals('failed', $export->status);
        $this->assertEquals($errorMessage, $export->error_message);
        $this->assertNotNull($export->completed_at);
    }

    /** @test */
    public function it_can_track_download()
    {
        $export = AccountingExport::factory()
            ->forOrganization($this->organization)
            ->completed()
            ->create();

        $initialDownloadCount = $export->download_count;

        $export->trackDownload($this->user->email);

        $this->assertEquals($initialDownloadCount + 1, $export->fresh()->download_count);
        $this->assertEquals($this->user->email, $export->fresh()->downloaded_by);
        $this->assertNotNull($export->fresh()->downloaded_at);
    }

    /** @test */
    public function it_can_check_if_export_is_pending()
    {
        $export = AccountingExport::factory()
            ->forOrganization($this->organization)
            ->pending()
            ->create();

        $this->assertTrue($export->isPending());
        $this->assertFalse($export->isProcessing());
        $this->assertFalse($export->isCompleted());
        $this->assertFalse($export->isFailed());
    }

    /** @test */
    public function it_can_check_if_export_is_completed()
    {
        $export = AccountingExport::factory()
            ->forOrganization($this->organization)
            ->completed()
            ->create();

        $this->assertFalse($export->isPending());
        $this->assertFalse($export->isProcessing());
        $this->assertTrue($export->isCompleted());
        $this->assertFalse($export->isFailed());
    }

    /** @test */
    public function it_can_retry_failed_export()
    {
        $export = AccountingExport::factory()
            ->forOrganization($this->organization)
            ->failed()
            ->create();

        $response = $this->postJson("/api/accounting-exports/{$export->id}/retry");

        $response->assertOk();

        // Export should be reset to pending and then processed
        $this->assertNotEquals('failed', $export->fresh()->status);
    }

    /** @test */
    public function it_cannot_retry_non_failed_export()
    {
        $export = AccountingExport::factory()
            ->forOrganization($this->organization)
            ->completed()
            ->create();

        $response = $this->postJson("/api/accounting-exports/{$export->id}/retry");

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Only failed exports can be retried']);
    }

    /** @test */
    public function it_can_get_file_size_formatted()
    {
        $export = AccountingExport::factory()
            ->forOrganization($this->organization)
            ->completed()
            ->create(['file_size_bytes' => 1024]);

        $this->assertEquals('1 KB', $export->getFileSizeFormatted());
    }

    /** @test */
    public function it_can_get_processing_time_formatted()
    {
        $export = AccountingExport::factory()
            ->forOrganization($this->organization)
            ->completed()
            ->create(['processing_time_seconds' => 125]);

        $this->assertEquals('2m 5s', $export->getProcessingTimeFormatted());
    }

    /** @test */
    public function it_can_filter_exports_by_type()
    {
        AccountingExport::factory()
            ->forOrganization($this->organization)
            ->costs()
            ->count(2)
            ->create();

        AccountingExport::factory()
            ->forOrganization($this->organization)
            ->fuel()
            ->create();

        $response = $this->getJson('/api/accounting-exports?type=costs');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_can_filter_exports_by_format()
    {
        AccountingExport::factory()
            ->forOrganization($this->organization)
            ->csv()
            ->count(2)
            ->create();

        AccountingExport::factory()
            ->forOrganization($this->organization)
            ->excel()
            ->create();

        $response = $this->getJson('/api/accounting-exports?format=csv');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_uses_default_account_mapping_when_not_provided()
    {
        $data = [
            'type' => 'costs',
            'format' => 'csv',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ];

        $response = $this->postJson('/api/accounting-exports', $data);

        $response->assertCreated();

        $export = AccountingExport::latest()->first();

        $this->assertNotNull($export->account_mapping);
        $this->assertArrayHasKey('fuel_account', $export->account_mapping);
        $this->assertEquals('6061', $export->account_mapping['fuel_account']);
    }

    /** @test */
    public function it_can_create_export_with_custom_account_mapping()
    {
        $customMapping = [
            'fuel_account' => '7000',
            'maintenance_account' => '7100',
        ];

        $data = [
            'type' => 'costs',
            'format' => 'csv',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'account_mapping' => $customMapping,
        ];

        $response = $this->postJson('/api/accounting-exports', $data);

        $response->assertCreated();

        $export = AccountingExport::latest()->first();

        $this->assertEquals('7000', $export->account_mapping['fuel_account']);
        $this->assertEquals('7100', $export->account_mapping['maintenance_account']);
    }

    /** @test */
    public function it_can_create_export_with_filters()
    {
        $filters = [
            'vehicle_ids' => [$this->vehicle->id],
            'cost_types' => ['fuel', 'maintenance'],
        ];

        $data = [
            'type' => 'custom',
            'format' => 'csv',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'filters' => $filters,
        ];

        $response = $this->postJson('/api/accounting-exports', $data);

        $response->assertCreated();

        $export = AccountingExport::latest()->first();

        $this->assertNotNull($export->filters);
        $this->assertEquals([$this->vehicle->id], $export->filters['vehicle_ids']);
    }

    /** @test */
    public function it_validates_vehicle_ids_in_filters()
    {
        $otherOrganization = Organization::factory()->create();
        $otherVehicle = Vehicle::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $data = [
            'type' => 'custom',
            'format' => 'csv',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'filters' => [
                'vehicle_ids' => [$otherVehicle->id],
            ],
        ];

        $response = $this->postJson('/api/accounting-exports', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['filters.vehicle_ids.0']);
    }
}
