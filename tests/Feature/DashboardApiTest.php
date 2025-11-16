<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Cost;
use App\Models\Driver;
use App\Models\FuelTransaction;
use App\Models\GpsPosition;
use App\Models\Maintenance;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardApiTest extends TestCase
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
            'vehicles.view',
            'costs.view',
            'maintenances.view',
            'drivers.view',
            'fuel-transactions.view',
        ]);
    }

    public function test_dashboard_returns_successful_response_with_authentication(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'fleet',
                'costs',
                'maintenance',
                'drivers',
                'fuel',
                'alerts',
            ],
        ]);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(401);
    }

    public function test_dashboard_fleet_kpis_structure(): void
    {
        // Create test data
        Vehicle::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'fleet' => [
                    'total_vehicles',
                    'active_vehicles',
                    'by_fuel_type',
                    'average_age',
                    'total_mileage',
                ],
            ],
        ]);
    }

    public function test_dashboard_costs_kpis_structure(): void
    {
        // Create test costs
        Cost::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => Vehicle::factory()->create([
                'organization_id' => $this->organization->id,
            ])->id,
            'amount' => 1000,
            'date' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'costs' => [
                    'total_costs',
                    'trend_percentage',
                    'by_category',
                ],
            ],
        ]);
    }

    public function test_dashboard_maintenance_kpis_structure(): void
    {
        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create various maintenance records
        Maintenance::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'pending',
            'scheduled_date' => now()->addDays(5),
        ]);

        Maintenance::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'pending',
            'scheduled_date' => now()->subDays(2), // Overdue
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'maintenance' => [
                    'total',
                    'pending',
                    'overdue',
                    'upcoming',
                    'average_cost',
                ],
            ],
        ]);

        // Assert overdue count is correct
        $this->assertEquals(1, $response->json('data.maintenance.overdue'));
    }

    public function test_dashboard_drivers_kpis_structure(): void
    {
        Driver::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'license_expiry_date' => now()->addDays(45), // Expiring soon
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'drivers' => [
                    'total',
                    'licenses_expiring_soon',
                    'average_eco_score',
                    'total_infractions',
                ],
            ],
        ]);
    }

    public function test_dashboard_fuel_kpis_structure(): void
    {
        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        FuelTransaction::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'date' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'fuel' => [
                    'total_volume',
                    'total_cost',
                    'average_price_per_liter',
                    'anomalies_count',
                    'by_fuel_type',
                ],
            ],
        ]);
    }

    public function test_dashboard_alerts_structure(): void
    {
        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create overdue maintenance
        Maintenance::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'pending',
            'scheduled_date' => now()->subDays(5),
        ]);

        // Create expiring license
        Driver::factory()->create([
            'organization_id' => $this->organization->id,
            'license_expiry_date' => now()->addDays(30),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'alerts' => [
                    'maintenance_overdue',
                    'licenses_expiring',
                    'contracts_expiring',
                    'pending_validations',
                ],
            ],
        ]);
    }

    public function test_live_fleet_endpoint_returns_vehicle_positions(): void
    {
        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
        ]);

        GpsPosition::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'timestamp' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/live-fleet');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'vehicle_id',
                    'registration_number',
                    'status',
                    'latest_position',
                ],
            ],
        ]);
    }

    public function test_dashboard_filters_by_date_range(): void
    {
        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create costs in different date ranges
        Cost::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'amount' => 1000,
            'date' => now()->startOfMonth(),
        ]);

        Cost::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'amount' => 500,
            'date' => now()->subMonth(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard?start_date=' . now()->startOfMonth()->toDateString() . '&end_date=' . now()->endOfMonth()->toDateString());

        $response->assertStatus(200);

        // Should only include current month's costs
        $totalCosts = $response->json('data.costs.total_costs');
        $this->assertEquals(1000, $totalCosts);
    }

    public function test_dashboard_respects_organization_isolation(): void
    {
        $otherOrganization = Organization::factory()->create();

        // Create data for another organization
        Vehicle::factory()->count(3)->create([
            'organization_id' => $otherOrganization->id,
        ]);

        Cost::factory()->count(5)->create([
            'organization_id' => $otherOrganization->id,
            'vehicle_id' => Vehicle::factory()->create([
                'organization_id' => $otherOrganization->id,
            ])->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard');

        $response->assertStatus(200);

        // User should only see their organization's data
        $this->assertEquals(0, $response->json('data.fleet.total_vehicles'));
        $this->assertEquals(0, $response->json('data.costs.total_costs'));
    }
}
