<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Infraction;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InfractionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Vehicle $vehicle;
    private Driver $driver;

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
            'infractions.view',
            'infractions.create',
            'infractions.update',
            'infractions.delete',
        ]);

        // Create test vehicle and driver
        $this->vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->driver = Driver::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_can_list_infractions(): void
    {
        Infraction::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/infractions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'infraction_number',
                    'type',
                    'status',
                    'amount',
                    'created_at',
                ],
            ],
        ]);
    }

    public function test_can_create_infraction(): void
    {
        $infractionData = [
            'vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->driver->id,
            'type' => 'speeding',
            'infraction_date' => now()->toDateString(),
            'location' => 'Paris, Avenue des Champs-Élysées',
            'amount' => 135.00,
            'reduced_amount' => 90.00,
            'points_deducted' => 1,
            'due_date' => now()->addDays(45)->toDateString(),
            'reduced_due_date' => now()->addDays(15)->toDateString(),
            'status' => 'received',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/infractions', $infractionData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'infraction_number',
                'type',
                'status',
                'amount',
            ],
        ]);

        $this->assertDatabaseHas('infractions', [
            'vehicle_id' => $this->vehicle->id,
            'type' => 'speeding',
            'amount' => 135.00,
        ]);

        // Check auto-generated infraction number
        $infraction = Infraction::first();
        $this->assertStringStartsWith('INF-', $infraction->infraction_number);
    }

    public function test_can_show_infraction(): void
    {
        $infraction = Infraction::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/infractions/{$infraction->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'infraction_number',
                'type',
                'status',
                'amount',
                'vehicle',
            ],
        ]);
    }

    public function test_can_update_infraction(): void
    {
        $infraction = Infraction::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'received',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/infractions/{$infraction->id}", [
                'status' => 'pending',
                'notes' => 'Updated notes',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('infractions', [
            'id' => $infraction->id,
            'status' => 'pending',
        ]);
    }

    public function test_can_delete_infraction(): void
    {
        $infraction = Infraction::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/infractions/{$infraction->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('infractions', ['id' => $infraction->id]);
    }

    public function test_can_mark_infraction_as_paid(): void
    {
        $infraction = Infraction::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/infractions/{$infraction->id}/pay", [
                'payment_method' => 'bank_transfer',
                'payment_reference' => 'REF123456',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('infractions', [
            'id' => $infraction->id,
            'status' => 'paid',
            'payment_method' => 'bank_transfer',
        ]);
    }

    public function test_can_contest_infraction(): void
    {
        $infraction = Infraction::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'received',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/infractions/{$infraction->id}/contest", [
                'contest_notes' => 'I was not driving at that time',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('infractions', [
            'id' => $infraction->id,
            'status' => 'contested',
        ]);
    }

    public function test_can_get_infractions_statistics(): void
    {
        Infraction::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'type' => 'speeding',
            'amount' => 135.00,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/infractions/statistics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_infractions',
                'total_amount',
                'unpaid_count',
                'by_type',
            ],
        ]);
    }

    public function test_infractions_respects_organization_isolation(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherVehicle = Vehicle::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        Infraction::factory()->count(3)->create([
            'organization_id' => $otherOrganization->id,
            'vehicle_id' => $otherVehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/infractions');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_cannot_view_infraction_from_different_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherVehicle = Vehicle::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $infraction = Infraction::factory()->create([
            'organization_id' => $otherOrganization->id,
            'vehicle_id' => $otherVehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/infractions/{$infraction->id}");

        $response->assertStatus(403);
    }

    public function test_can_filter_infractions_by_type(): void
    {
        Infraction::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'type' => 'speeding',
        ]);

        Infraction::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'type' => 'parking',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/infractions?type=speeding');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('speeding', $response->json('data.0.type'));
    }

    public function test_can_filter_infractions_by_status(): void
    {
        Infraction::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'paid',
        ]);

        Infraction::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/infractions?status=pending');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('pending', $response->json('data.0.status'));
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/infractions');
        $response->assertStatus(401);
    }
}
