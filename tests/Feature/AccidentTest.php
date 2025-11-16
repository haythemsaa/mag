<?php

namespace Tests\Feature;

use App\Models\Accident;
use App\Models\Driver;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccidentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Vehicle $vehicle;
    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'fleet-manager']);
        $this->user->assignRole($role);
        $this->user->givePermissionTo([
            'accidents.view',
            'accidents.create',
            'accidents.update',
            'accidents.delete',
        ]);

        $this->vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->driver = Driver::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_can_list_accidents(): void
    {
        Accident::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/accidents');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'accident_number',
                    'severity',
                    'status',
                    'created_at',
                ],
            ],
        ]);
    }

    public function test_can_create_accident(): void
    {
        $accidentData = [
            'vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->driver->id,
            'accident_date' => now()->toDateString(),
            'location' => 'Paris, Rue de Rivoli',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'severity' => 'moderate',
            'responsibility' => 'driver',
            'description' => 'Collision with another vehicle at intersection',
            'police_report' => true,
            'police_report_number' => 'PV-12345-ABCD',
            'injuries' => false,
            'injured_count' => 0,
            'estimated_cost' => 2500.00,
            'status' => 'declared',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/accidents', $accidentData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'accident_number',
                'severity',
                'status',
            ],
        ]);

        $this->assertDatabaseHas('accidents', [
            'vehicle_id' => $this->vehicle->id,
            'severity' => 'moderate',
        ]);

        $accident = Accident::first();
        $this->assertStringStartsWith('ACC-', $accident->accident_number);
    }

    public function test_can_show_accident(): void
    {
        $accident = Accident::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/accidents/{$accident->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'accident_number',
                'severity',
                'status',
                'vehicle',
            ],
        ]);
    }

    public function test_can_update_accident(): void
    {
        $accident = Accident::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'declared',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/accidents/{$accident->id}", [
                'status' => 'in_progress',
                'notes' => 'Updated notes',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('accidents', [
            'id' => $accident->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_can_delete_accident(): void
    {
        $accident = Accident::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/accidents/{$accident->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('accidents', ['id' => $accident->id]);
    }

    public function test_can_mark_accident_as_expertised(): void
    {
        $accident = Accident::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'declared',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/accidents/{$accident->id}/expertised", [
                'estimated_cost' => 3500.00,
                'notes' => 'Expertise completed',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('accidents', [
            'id' => $accident->id,
            'status' => 'expertised',
        ]);
    }

    public function test_can_mark_accident_as_repaired(): void
    {
        $accident = Accident::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'expertised',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/accidents/{$accident->id}/repaired", [
                'final_cost' => 3800.00,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('accidents', [
            'id' => $accident->id,
            'status' => 'repaired',
            'final_cost' => 3800.00,
        ]);
    }

    public function test_can_close_accident(): void
    {
        $accident = Accident::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'repaired',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/accidents/{$accident->id}/close");

        $response->assertStatus(200);
        $this->assertDatabaseHas('accidents', [
            'id' => $accident->id,
            'status' => 'closed',
        ]);
        $this->assertNotNull(Accident::find($accident->id)->closed_date);
    }

    public function test_can_file_insurance_claim(): void
    {
        $accident = Accident::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/accidents/{$accident->id}/insurance-claim", [
                'insurance_claim_number' => 'CLAIM-2025-001',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('accidents', [
            'id' => $accident->id,
            'insurance_claim_number' => 'CLAIM-2025-001',
            'insurance_status' => 'pending',
        ]);
    }

    public function test_can_get_accidents_statistics(): void
    {
        Accident::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'severity' => 'moderate',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/accidents/statistics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_accidents',
                'by_severity',
                'by_status',
            ],
        ]);
    }

    public function test_accidents_respects_organization_isolation(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherVehicle = Vehicle::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        Accident::factory()->count(3)->create([
            'organization_id' => $otherOrganization->id,
            'vehicle_id' => $otherVehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/accidents');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_cannot_view_accident_from_different_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherVehicle = Vehicle::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $accident = Accident::factory()->create([
            'organization_id' => $otherOrganization->id,
            'vehicle_id' => $otherVehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/accidents/{$accident->id}");

        $response->assertStatus(403);
    }

    public function test_can_filter_accidents_by_severity(): void
    {
        Accident::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'severity' => 'severe',
        ]);

        Accident::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'severity' => 'minor',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/accidents?severity=severe');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('severe', $response->json('data.0.severity'));
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/accidents');
        $response->assertStatus(401);
    }
}
