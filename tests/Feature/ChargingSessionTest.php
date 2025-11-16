<?php

namespace Tests\Feature;

use App\Models\ChargingSession;
use App\Models\ChargingStation;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChargingSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Vehicle $vehicle;
    private ChargingStation $chargingStation;

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
            'view_charging_sessions',
            'create_charging_sessions',
            'update_charging_sessions',
            'delete_charging_sessions',
        ]);

        // Create test vehicle
        $this->vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
            'is_electric' => true,
            'battery_capacity_kwh' => 60,
        ]);

        // Create test charging station
        $this->chargingStation = ChargingStation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_can_list_charging_sessions(): void
    {
        ChargingSession::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/charging-sessions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'session_number',
                    'status',
                    'battery_level_start_percent',
                    'energy_delivered_kwh',
                    'total_cost',
                    'created_at',
                ],
            ],
        ]);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_charging_session(): void
    {
        $sessionData = [
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
            'driver_id' => $this->user->id,
            'battery_level_start_percent' => 20,
            'cost_per_kwh' => 0.25,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/charging-sessions', $sessionData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'session_number',
                'status',
                'battery_level_start_percent',
            ],
        ]);

        $this->assertDatabaseHas('charging_sessions', [
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
            'battery_level_start_percent' => 20,
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_can_show_charging_session(): void
    {
        $session = ChargingSession::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/charging-sessions/{$session->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'session_number',
                'status',
                'battery_level_start_percent',
                'energy_delivered_kwh',
            ],
        ]);
        $response->assertJson([
            'data' => [
                'id' => $session->id,
                'session_number' => $session->session_number,
            ],
        ]);
    }

    public function test_can_update_charging_session(): void
    {
        $session = ChargingSession::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
            'status' => 'in_progress',
        ]);

        $updateData = [
            'notes' => 'Session interrupted due to power outage',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/charging-sessions/{$session->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'notes' => 'Session interrupted due to power outage',
            ],
        ]);

        $this->assertDatabaseHas('charging_sessions', [
            'id' => $session->id,
            'notes' => 'Session interrupted due to power outage',
        ]);
    }

    public function test_can_delete_charging_session(): void
    {
        $session = ChargingSession::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/charging-sessions/{$session->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('charging_sessions', [
            'id' => $session->id,
        ]);
    }

    public function test_can_complete_charging_session(): void
    {
        $session = ChargingSession::factory()->inProgress()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
            'battery_level_start_percent' => 20,
            'cost_per_kwh' => 0.25,
        ]);

        $completeData = [
            'battery_level_end_percent' => 80,
            'energy_delivered_kwh' => 45.5,
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/charging-sessions/{$session->id}/complete", $completeData);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'status' => 'completed',
                'battery_level_end_percent' => 80,
                'energy_delivered_kwh' => 45.5,
            ],
        ]);

        $session->refresh();
        $this->assertEquals('completed', $session->status);
        $this->assertEquals(80, $session->battery_level_end_percent);
        $this->assertEquals(45.5, $session->energy_delivered_kwh);
        $this->assertNotNull($session->end_time);
        $this->assertNotNull($session->duration_minutes);
    }

    public function test_can_get_charging_sessions_statistics(): void
    {
        ChargingSession::factory()->completed()->count(5)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/charging-sessions/statistics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_sessions',
                'completed_sessions',
                'in_progress_sessions',
                'total_energy_kwh',
                'total_cost',
                'average_session_duration',
                'average_energy_per_session',
                'average_cost_per_session',
            ],
        ]);
    }

    public function test_can_filter_sessions_by_vehicle(): void
    {
        $vehicle2 = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
            'is_electric' => true,
        ]);

        ChargingSession::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
        ]);
        ChargingSession::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle2->id,
            'charging_station_id' => $this->chargingStation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/charging-sessions?vehicle_id={$this->vehicle->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJson([
            'data' => [
                ['vehicle_id' => $this->vehicle->id],
            ],
        ]);
    }

    public function test_can_filter_sessions_by_status(): void
    {
        ChargingSession::factory()->completed()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
        ]);
        ChargingSession::factory()->inProgress()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/charging-sessions?status=completed');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJson([
            'data' => [
                ['status' => 'completed'],
            ],
        ]);
    }

    public function test_can_filter_sessions_by_date_range(): void
    {
        ChargingSession::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
            'start_time' => now()->subDays(10),
        ]);
        ChargingSession::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
            'start_time' => now()->subDays(2),
        ]);

        $fromDate = now()->subDays(5)->toDateString();
        $toDate = now()->toDateString();

        $response = $this->actingAs($this->user)
            ->getJson("/api/charging-sessions?from_date={$fromDate}&to_date={$toDate}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_cannot_access_other_organization_sessions(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherVehicle = Vehicle::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);
        $otherStation = ChargingStation::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);
        $otherSession = ChargingSession::factory()->create([
            'organization_id' => $otherOrganization->id,
            'vehicle_id' => $otherVehicle->id,
            'charging_station_id' => $otherStation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/charging-sessions/{$otherSession->id}");

        $response->assertStatus(403);
    }

    public function test_session_auto_generates_session_number(): void
    {
        $sessionData = [
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
            'battery_level_start_percent' => 20,
            'cost_per_kwh' => 0.25,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/charging-sessions', $sessionData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['session_number'],
        ]);

        $sessionNumber = $response->json('data.session_number');
        $this->assertNotNull($sessionNumber);
        $this->assertStringStartsWith('CHG-', $sessionNumber);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/charging-sessions', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'vehicle_id',
            'charging_station_id',
            'battery_level_start_percent',
            'cost_per_kwh',
        ]);
    }

    public function test_validates_battery_level_range(): void
    {
        $sessionData = [
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
            'battery_level_start_percent' => 120, // Invalid: > 100
            'cost_per_kwh' => 0.25,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/charging-sessions', $sessionData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['battery_level_start_percent']);
    }

    public function test_complete_validates_required_fields(): void
    {
        $session = ChargingSession::factory()->inProgress()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/charging-sessions/{$session->id}/complete", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'battery_level_end_percent',
            'energy_delivered_kwh',
        ]);
    }

    public function test_calculates_total_cost_correctly(): void
    {
        $session = ChargingSession::factory()->inProgress()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'charging_station_id' => $this->chargingStation->id,
            'battery_level_start_percent' => 20,
            'cost_per_kwh' => 0.30,
        ]);

        $completeData = [
            'battery_level_end_percent' => 80,
            'energy_delivered_kwh' => 50.0,
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/charging-sessions/{$session->id}/complete", $completeData);

        $response->assertStatus(200);

        $session->refresh();
        $expectedCost = 50.0 * 0.30; // 15.00 EUR
        $this->assertEquals($expectedCost, $session->total_cost);
    }
}
