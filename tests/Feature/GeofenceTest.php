<?php

namespace Tests\Feature;

use App\Models\Geofence;
use App\Models\GeofenceEvent;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeofenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;

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
            'geofences.view',
            'geofences.create',
            'geofences.update',
            'geofences.delete',
        ]);
    }

    public function test_can_list_geofences(): void
    {
        Geofence::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/geofences');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'type',
                    'shape',
                    'is_active',
                    'created_at',
                ],
            ],
        ]);
    }

    public function test_can_create_circle_geofence(): void
    {
        $geofenceData = [
            'name' => 'Main Depot',
            'description' => 'Company headquarters parking area',
            'type' => 'depot',
            'shape' => 'circle',
            'center_latitude' => 48.8566,
            'center_longitude' => 2.3522,
            'radius_meters' => 500,
            'alert_on_entry' => true,
            'alert_on_exit' => true,
            'is_active' => true,
            'address' => '123 Rue de Rivoli',
            'city' => 'Paris',
            'postal_code' => '75001',
            'country' => 'France',
            'color' => '#3B82F6',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/geofences', $geofenceData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'type',
                'shape',
                'center_latitude',
                'center_longitude',
                'radius_meters',
            ],
        ]);

        $this->assertDatabaseHas('geofences', [
            'name' => 'Main Depot',
            'shape' => 'circle',
            'radius_meters' => 500,
        ]);
    }

    public function test_can_create_polygon_geofence(): void
    {
        $geofenceData = [
            'name' => 'Delivery Zone A',
            'type' => 'delivery_zone',
            'shape' => 'polygon',
            'polygon_coordinates' => [
                ['lat' => 48.8570, 'lng' => 2.3520],
                ['lat' => 48.8575, 'lng' => 2.3530],
                ['lat' => 48.8560, 'lng' => 2.3535],
                ['lat' => 48.8555, 'lng' => 2.3525],
            ],
            'alert_on_entry' => false,
            'alert_on_exit' => false,
            'is_active' => true,
            'city' => 'Paris',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/geofences', $geofenceData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'shape',
                'polygon_coordinates',
            ],
        ]);

        $this->assertDatabaseHas('geofences', [
            'name' => 'Delivery Zone A',
            'shape' => 'polygon',
        ]);
    }

    public function test_can_show_geofence(): void
    {
        $geofence = Geofence::factory()->circle()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/geofences/{$geofence->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'type',
                'shape',
                'organization',
            ],
        ]);
    }

    public function test_can_update_geofence(): void
    {
        $geofence = Geofence::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/geofences/{$geofence->id}", [
                'is_active' => false,
                'alert_on_entry' => true,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('geofences', [
            'id' => $geofence->id,
            'is_active' => false,
            'alert_on_entry' => true,
        ]);
    }

    public function test_can_delete_geofence(): void
    {
        $geofence = Geofence::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/geofences/{$geofence->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('geofences', ['id' => $geofence->id]);
    }

    public function test_can_get_geofence_statistics(): void
    {
        $geofence = Geofence::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/geofences/{$geofence->id}/statistics");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_events',
                'entry_events',
                'exit_events',
                'unique_vehicles',
            ],
        ]);
    }

    public function test_geofences_respects_organization_isolation(): void
    {
        $otherOrganization = Organization::factory()->create();

        Geofence::factory()->count(3)->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/geofences');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_cannot_view_geofence_from_different_organization(): void
    {
        $otherOrganization = Organization::factory()->create();

        $geofence = Geofence::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/geofences/{$geofence->id}");

        $response->assertStatus(403);
    }

    public function test_can_filter_geofences_by_type(): void
    {
        Geofence::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'depot',
        ]);

        Geofence::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'parking',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/geofences?type=depot');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('depot', $response->json('data.0.type'));
    }

    public function test_can_filter_geofences_by_shape(): void
    {
        Geofence::factory()->circle()->create([
            'organization_id' => $this->organization->id,
        ]);

        Geofence::factory()->polygon()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/geofences?shape=circle');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('circle', $response->json('data.0.shape'));
    }

    public function test_geofence_contains_point_logic_for_circle(): void
    {
        $geofence = Geofence::factory()->create([
            'organization_id' => $this->organization->id,
            'shape' => 'circle',
            'center_latitude' => 48.8566,
            'center_longitude' => 2.3522,
            'radius_meters' => 1000,
        ]);

        // Point inside the circle
        $this->assertTrue($geofence->containsPoint(48.8570, 2.3525));

        // Point outside the circle
        $this->assertFalse($geofence->containsPoint(48.9000, 2.4000));
    }

    public function test_geofence_contains_point_logic_for_polygon(): void
    {
        $geofence = Geofence::factory()->create([
            'organization_id' => $this->organization->id,
            'shape' => 'polygon',
            'polygon_coordinates' => [
                ['lat' => 48.8570, 'lng' => 2.3520],
                ['lat' => 48.8575, 'lng' => 2.3530],
                ['lat' => 48.8560, 'lng' => 2.3535],
                ['lat' => 48.8555, 'lng' => 2.3525],
            ],
        ]);

        // Point inside the polygon
        $this->assertTrue($geofence->containsPoint(48.8565, 2.3527));

        // Point outside the polygon
        $this->assertFalse($geofence->containsPoint(48.9000, 2.4000));
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/geofences');
        $response->assertStatus(401);
    }
}
