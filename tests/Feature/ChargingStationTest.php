<?php

namespace Tests\Feature;

use App\Models\ChargingStation;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChargingStationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organization
        $this->organization = Organization::factory()->create();

        // Create user with permissions
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'fleet-manager']);
        $this->user->assignRole($role);
        $this->user->givePermissionTo([
            'view_charging_stations',
            'create_charging_stations',
            'update_charging_stations',
            'delete_charging_stations',
        ]);
    }

    public function test_can_list_charging_stations(): void
    {
        ChargingStation::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/charging-stations');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'type',
                    'connector_type',
                    'max_power_kw',
                    'status',
                    'is_active',
                    'created_at',
                ],
            ],
        ]);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_charging_station(): void
    {
        $stationData = [
            'name' => 'Test Charging Station',
            'type' => 'depot',
            'connector_type' => 'Type 2',
            'max_power_kw' => 22,
            'status' => 'available',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'address' => '123 Rue de Paris',
            'city' => 'Paris',
            'postal_code' => '75001',
            'country' => 'France',
            'cost_per_kwh' => 0.25,
            'currency' => 'EUR',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/charging-stations', $stationData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'type',
                'connector_type',
                'max_power_kw',
                'status',
            ],
        ]);

        $this->assertDatabaseHas('charging_stations', [
            'name' => 'Test Charging Station',
            'type' => 'depot',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_can_show_charging_station(): void
    {
        $station = ChargingStation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/charging-stations/{$station->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'type',
                'connector_type',
                'max_power_kw',
                'status',
            ],
        ]);
        $response->assertJson([
            'data' => [
                'id' => $station->id,
                'name' => $station->name,
            ],
        ]);
    }

    public function test_can_update_charging_station(): void
    {
        $station = ChargingStation::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'available',
        ]);

        $updateData = [
            'status' => 'maintenance',
            'notes' => 'Scheduled maintenance',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/charging-stations/{$station->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'status' => 'maintenance',
                'notes' => 'Scheduled maintenance',
            ],
        ]);

        $this->assertDatabaseHas('charging_stations', [
            'id' => $station->id,
            'status' => 'maintenance',
        ]);
    }

    public function test_can_delete_charging_station(): void
    {
        $station = ChargingStation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/charging-stations/{$station->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('charging_stations', [
            'id' => $station->id,
        ]);
    }

    public function test_can_get_charging_station_statistics(): void
    {
        $station = ChargingStation::factory()->create([
            'organization_id' => $this->organization->id,
            'total_sessions' => 100,
            'total_energy_delivered_kwh' => 5000,
            'total_revenue' => 1250,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/charging-stations/{$station->id}/statistics");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_sessions',
                'completed_sessions',
                'total_energy_kwh',
                'total_revenue',
                'average_session_duration',
                'average_energy_per_session',
            ],
        ]);
    }

    public function test_can_filter_charging_stations_by_type(): void
    {
        ChargingStation::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'depot',
        ]);
        ChargingStation::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'public',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/charging-stations?type=depot');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJson([
            'data' => [
                ['type' => 'depot'],
            ],
        ]);
    }

    public function test_can_filter_charging_stations_by_status(): void
    {
        ChargingStation::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'available',
        ]);
        ChargingStation::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'maintenance',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/charging-stations?status=available');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJson([
            'data' => [
                ['status' => 'available'],
            ],
        ]);
    }

    public function test_can_search_charging_stations(): void
    {
        ChargingStation::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Paris Depot Station',
        ]);
        ChargingStation::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Lyon Office Station',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/charging-stations?search=Paris');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_cannot_access_other_organization_charging_stations(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherStation = ChargingStation::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/charging-stations/{$otherStation->id}");

        $response->assertStatus(403);
    }

    public function test_charging_station_auto_generates_station_code(): void
    {
        $stationData = [
            'name' => 'Test Station',
            'type' => 'depot',
            'connector_type' => 'Type 2',
            'max_power_kw' => 22,
            'cost_per_kwh' => 0.25,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/charging-stations', $stationData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['station_code'],
        ]);

        $stationCode = $response->json('data.station_code');
        $this->assertNotNull($stationCode);
        $this->assertStringStartsWith('CS-', $stationCode);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/charging-stations', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'type', 'connector_type', 'max_power_kw']);
    }

    public function test_validates_type_enum(): void
    {
        $stationData = [
            'name' => 'Test Station',
            'type' => 'invalid_type',
            'connector_type' => 'Type 2',
            'max_power_kw' => 22,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/charging-stations', $stationData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_validates_connector_type_enum(): void
    {
        $stationData = [
            'name' => 'Test Station',
            'type' => 'depot',
            'connector_type' => 'invalid_connector',
            'max_power_kw' => 22,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/charging-stations', $stationData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['connector_type']);
    }

    public function test_validates_latitude_range(): void
    {
        $stationData = [
            'name' => 'Test Station',
            'type' => 'depot',
            'connector_type' => 'Type 2',
            'max_power_kw' => 22,
            'latitude' => 95, // Invalid: > 90
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/charging-stations', $stationData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['latitude']);
    }
}
