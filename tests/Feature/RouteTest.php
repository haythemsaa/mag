<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Organization;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RouteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;
    protected Vehicle $vehicle;
    protected Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organization
        $this->organization = Organization::factory()->create();

        // Create user
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create vehicle and driver
        $this->vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->driver = Driver::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->user);
    }

    /** @test */
    public function it_can_list_routes()
    {
        Route::factory()
            ->count(3)
            ->forOrganization($this->organization)
            ->create();

        $response = $this->getJson('/api/routes');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'route_number',
                        'name',
                        'status',
                        'planned_date',
                        'planned_distance_km',
                        'planned_duration_minutes',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_filter_routes_by_status()
    {
        Route::factory()
            ->forOrganization($this->organization)
            ->planned()
            ->count(2)
            ->create();

        Route::factory()
            ->forOrganization($this->organization)
            ->completed()
            ->create();

        $response = $this->getJson('/api/routes?status=planned');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_can_filter_routes_by_date()
    {
        $today = now()->toDateString();

        Route::factory()
            ->forOrganization($this->organization)
            ->create(['planned_date' => $today]);

        Route::factory()
            ->forOrganization($this->organization)
            ->create(['planned_date' => now()->addDays(5)]);

        $response = $this->getJson("/api/routes?date={$today}");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function it_can_create_a_route()
    {
        $data = [
            'name' => 'Test Route',
            'description' => 'Test route description',
            'planned_date' => now()->addDays(1)->toDateString(),
            'vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->driver->id,
            'notes' => 'Important notes',
        ];

        $response = $this->postJson('/api/routes', $data);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Test Route',
                'status' => 'draft',
            ]);

        $this->assertDatabaseHas('routes', [
            'name' => 'Test Route',
            'organization_id' => $this->organization->id,
            'status' => 'draft',
        ]);
    }

    /** @test */
    public function it_validates_route_creation()
    {
        $response = $this->postJson('/api/routes', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'planned_date']);
    }

    /** @test */
    public function it_can_show_a_route()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $response = $this->getJson("/api/routes/{$route->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $route->id,
                'route_number' => $route->route_number,
            ]);
    }

    /** @test */
    public function it_can_show_route_with_stops()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        RouteStop::factory()
            ->forRoute($route)
            ->count(3)
            ->create();

        $response = $this->getJson("/api/routes/{$route->id}");

        $response->assertOk()
            ->assertJsonCount(3, 'data.stops');
    }

    /** @test */
    public function it_can_update_a_route()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $response = $this->putJson("/api/routes/{$route->id}", [
            'name' => 'Updated Name',
            'notes' => 'Updated notes',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('routes', [
            'id' => $route->id,
            'name' => 'Updated Name',
        ]);
    }

    /** @test */
    public function it_can_delete_a_route()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $response = $this->deleteJson("/api/routes/{$route->id}");

        $response->assertOk();

        $this->assertSoftDeleted('routes', ['id' => $route->id]);
    }

    /** @test */
    public function it_can_start_a_route()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->planned()
            ->create([
                'vehicle_id' => $this->vehicle->id,
                'driver_id' => $this->driver->id,
            ]);

        RouteStop::factory()
            ->forRoute($route)
            ->create();

        $response = $this->postJson("/api/routes/{$route->id}/start");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'in_progress']);

        $this->assertDatabaseHas('routes', [
            'id' => $route->id,
            'status' => 'in_progress',
        ]);

        $this->assertNotNull($route->fresh()->started_at);
    }

    /** @test */
    public function it_cannot_start_route_without_vehicle_and_driver()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->planned()
            ->create([
                'vehicle_id' => null,
                'driver_id' => null,
            ]);

        $response = $this->postJson("/api/routes/{$route->id}/start");

        $response->assertStatus(422);
    }

    /** @test */
    public function it_can_complete_a_route()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->inProgress()
            ->create();

        $data = [
            'actual_distance_km' => 125.50,
            'actual_duration_minutes' => 180,
            'fuel_consumption_liters' => 12.5,
            'completion_notes' => 'All deliveries completed successfully',
        ];

        $response = $this->postJson("/api/routes/{$route->id}/complete", $data);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'completed']);

        $route->refresh();

        $this->assertEquals('completed', $route->status);
        $this->assertEquals(125.50, (float) $route->actual_distance_km);
        $this->assertNotNull($route->completed_at);
    }

    /** @test */
    public function it_can_cancel_a_route()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->planned()
            ->create();

        $response = $this->postJson("/api/routes/{$route->id}/cancel", [
            'reason' => 'Vehicle breakdown',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);

        $this->assertDatabaseHas('routes', [
            'id' => $route->id,
            'status' => 'cancelled',
            'completion_notes' => 'Vehicle breakdown',
        ]);
    }

    /** @test */
    public function it_can_assign_vehicle_to_route()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create(['vehicle_id' => null]);

        $response = $this->postJson("/api/routes/{$route->id}/assign-vehicle", [
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('routes', [
            'id' => $route->id,
            'vehicle_id' => $this->vehicle->id,
        ]);
    }

    /** @test */
    public function it_cannot_assign_vehicle_from_different_organization()
    {
        $otherOrganization = Organization::factory()->create();
        $otherVehicle = Vehicle::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $response = $this->postJson("/api/routes/{$route->id}/assign-vehicle", [
            'vehicle_id' => $otherVehicle->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    /** @test */
    public function it_can_assign_driver_to_route()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create(['driver_id' => null]);

        $response = $this->postJson("/api/routes/{$route->id}/assign-driver", [
            'driver_id' => $this->driver->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('routes', [
            'id' => $route->id,
            'driver_id' => $this->driver->id,
        ]);
    }

    /** @test */
    public function it_can_optimize_a_route()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->draft()
            ->create();

        // Create multiple stops with coordinates
        RouteStop::factory()
            ->forRoute($route)
            ->count(5)
            ->create();

        $response = $this->postJson("/api/routes/{$route->id}/optimize", [
            'method' => 'nearest_neighbor',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data',
                'savings' => [
                    'distance_saved_km',
                    'time_saved_minutes',
                    'cost_saved',
                    'improvement_percent',
                ],
            ]);

        $route->refresh();

        $this->assertTrue($route->is_optimized);
        $this->assertEquals('nearest_neighbor', $route->optimization_method);
    }

    /** @test */
    public function it_cannot_optimize_route_with_less_than_two_stops()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        RouteStop::factory()
            ->forRoute($route)
            ->create();

        $response = $this->postJson("/api/routes/{$route->id}/optimize");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Route cannot be optimized']);
    }

    /** @test */
    public function it_can_get_route_statistics()
    {
        Route::factory()
            ->forOrganization($this->organization)
            ->planned()
            ->count(5)
            ->create();

        Route::factory()
            ->forOrganization($this->organization)
            ->completed()
            ->count(10)
            ->create();

        $response = $this->getJson('/api/routes/statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'total_routes',
                'by_status',
                'upcoming_routes',
                'completed_this_month',
            ]);
    }

    /** @test */
    public function it_cannot_view_routes_from_other_organizations()
    {
        $otherOrganization = Organization::factory()->create();
        $otherRoute = Route::factory()
            ->forOrganization($otherOrganization)
            ->create();

        $response = $this->getJson("/api/routes/{$otherRoute->id}");

        $response->assertForbidden();
    }

    /** @test */
    public function it_cannot_update_routes_from_other_organizations()
    {
        $otherOrganization = Organization::factory()->create();
        $otherRoute = Route::factory()
            ->forOrganization($otherOrganization)
            ->create();

        $response = $this->putJson("/api/routes/{$otherRoute->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertForbidden();
    }

    // ============================================================
    // ROUTE STOPS TESTS
    // ============================================================

    /** @test */
    public function it_can_list_stops_for_a_route()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        RouteStop::factory()
            ->forRoute($route)
            ->count(5)
            ->create();

        $response = $this->getJson("/api/routes/{$route->id}/stops");

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function it_can_create_a_stop_for_route()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $data = [
            'type' => 'delivery',
            'location_name' => 'Customer ABC',
            'address' => '123 Main St',
            'latitude' => 36.8065,
            'longitude' => 10.1815,
            'contact_name' => 'John Doe',
            'contact_phone' => '+216 12 345 678',
            'service_duration_minutes' => 30,
            'requires_signature' => true,
            'reference_number' => 'REF-123',
        ];

        $response = $this->postJson("/api/routes/{$route->id}/stops", $data);

        $response->assertCreated()
            ->assertJsonFragment([
                'location_name' => 'Customer ABC',
                'type' => 'delivery',
            ]);

        $this->assertDatabaseHas('route_stops', [
            'route_id' => $route->id,
            'location_name' => 'Customer ABC',
        ]);
    }

    /** @test */
    public function it_validates_stop_creation()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $response = $this->postJson("/api/routes/{$route->id}/stops", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'type',
                'location_name',
                'latitude',
                'longitude',
            ]);
    }

    /** @test */
    public function it_validates_latitude_and_longitude_ranges()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $response = $this->postJson("/api/routes/{$route->id}/stops", [
            'type' => 'delivery',
            'location_name' => 'Test',
            'latitude' => 100, // Invalid
            'longitude' => -200, // Invalid
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    /** @test */
    public function it_can_show_a_stop()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $stop = RouteStop::factory()
            ->forRoute($route)
            ->create();

        $response = $this->getJson("/api/routes/{$route->id}/stops/{$stop->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $stop->id]);
    }

    /** @test */
    public function it_can_update_a_stop()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $stop = RouteStop::factory()
            ->forRoute($route)
            ->create();

        $response = $this->putJson("/api/routes/{$route->id}/stops/{$stop->id}", [
            'location_name' => 'Updated Location',
            'notes' => 'Updated notes',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['location_name' => 'Updated Location']);
    }

    /** @test */
    public function it_can_delete_a_stop()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $stop = RouteStop::factory()
            ->forRoute($route)
            ->create();

        $response = $this->deleteJson("/api/routes/{$route->id}/stops/{$stop->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('route_stops', ['id' => $stop->id]);
    }

    /** @test */
    public function it_can_mark_stop_as_arrived()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->inProgress()
            ->create();

        $stop = RouteStop::factory()
            ->forRoute($route)
            ->pending()
            ->create();

        $response = $this->postJson("/api/routes/{$route->id}/stops/{$stop->id}/arrive");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'arrived']);

        $stop->refresh();

        $this->assertEquals('arrived', $stop->status);
        $this->assertNotNull($stop->actual_arrival);
    }

    /** @test */
    public function it_can_start_service_at_stop()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->inProgress()
            ->create();

        $stop = RouteStop::factory()
            ->forRoute($route)
            ->arrived()
            ->create();

        $response = $this->postJson("/api/routes/{$route->id}/stops/{$stop->id}/start-service");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'in_progress']);
    }

    /** @test */
    public function it_can_complete_a_stop()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->inProgress()
            ->create();

        $stop = RouteStop::factory()
            ->forRoute($route)
            ->inProgress()
            ->create();

        $response = $this->postJson("/api/routes/{$route->id}/stops/{$stop->id}/complete", [
            'completion_notes' => 'Delivered successfully',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'completed']);

        $stop->refresh();

        $this->assertEquals('completed', $stop->status);
        $this->assertNotNull($stop->actual_departure);
    }

    /** @test */
    public function it_can_skip_a_stop()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->inProgress()
            ->create();

        $stop = RouteStop::factory()
            ->forRoute($route)
            ->pending()
            ->create();

        $response = $this->postJson("/api/routes/{$route->id}/stops/{$stop->id}/skip", [
            'reason' => 'Customer not available',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'skipped']);

        $this->assertDatabaseHas('route_stops', [
            'id' => $stop->id,
            'status' => 'skipped',
            'failure_reason' => 'Customer not available',
        ]);
    }

    /** @test */
    public function it_can_fail_a_stop()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->inProgress()
            ->create();

        $stop = RouteStop::factory()
            ->forRoute($route)
            ->inProgress()
            ->create();

        $response = $this->postJson("/api/routes/{$route->id}/stops/{$stop->id}/fail", [
            'reason' => 'Wrong address',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'failed']);
    }

    /** @test */
    public function it_can_upload_signature_to_stop()
    {
        Storage::fake('public');

        $route = Route::factory()
            ->forOrganization($this->organization)
            ->inProgress()
            ->create();

        $stop = RouteStop::factory()
            ->forRoute($route)
            ->inProgress()
            ->create(['requires_signature' => true]);

        $signature = UploadedFile::fake()->image('signature.png', 500, 200);

        $response = $this->postJson("/api/routes/{$route->id}/stops/{$stop->id}/upload-signature", [
            'signature' => $signature,
        ]);

        $response->assertOk();

        $stop->refresh();

        $this->assertNotNull($stop->signature_path);
        Storage::disk('public')->assertExists($stop->signature_path);
    }

    /** @test */
    public function it_can_upload_photo_to_stop()
    {
        Storage::fake('public');

        $route = Route::factory()
            ->forOrganization($this->organization)
            ->inProgress()
            ->create();

        $stop = RouteStop::factory()
            ->forRoute($route)
            ->inProgress()
            ->create(['requires_photo' => true]);

        $photo = UploadedFile::fake()->image('delivery.jpg', 1200, 900);

        $response = $this->postJson("/api/routes/{$route->id}/stops/{$stop->id}/upload-photo", [
            'photo' => $photo,
        ]);

        $response->assertOk();

        $stop->refresh();

        $this->assertNotNull($stop->photo_paths);
        $this->assertIsArray($stop->photo_paths);
        $this->assertCount(1, $stop->photo_paths);
        Storage::disk('public')->assertExists($stop->photo_paths[0]);
    }

    /** @test */
    public function it_validates_signature_upload()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $stop = RouteStop::factory()
            ->forRoute($route)
            ->create();

        $response = $this->postJson("/api/routes/{$route->id}/stops/{$stop->id}/upload-signature", [
            'signature' => 'not-a-file',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['signature']);
    }

    /** @test */
    public function it_validates_photo_upload()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $stop = RouteStop::factory()
            ->forRoute($route)
            ->create();

        $response = $this->postJson("/api/routes/{$route->id}/stops/{$stop->id}/upload-photo", [
            'photo' => 'not-a-file',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    /** @test */
    public function stop_must_belong_to_route()
    {
        $route1 = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $route2 = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $stop = RouteStop::factory()
            ->forRoute($route2)
            ->create();

        $response = $this->getJson("/api/routes/{$route1->id}/stops/{$stop->id}");

        $response->assertNotFound();
    }

    /** @test */
    public function it_auto_generates_route_number()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $this->assertNotNull($route->route_number);
        $this->assertStringStartsWith('RT-', $route->route_number);
        $this->assertStringContainsString((string) now()->year, $route->route_number);
    }

    /** @test */
    public function it_calculates_route_progress_percentage()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create(['stops_count' => 10, 'completed_stops_count' => 3]);

        $this->assertEquals(30.0, $route->getProgressPercentage());
    }

    /** @test */
    public function it_calculates_stop_delay()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $plannedArrival = now()->subMinutes(30);
        $actualArrival = now();

        $stop = RouteStop::factory()
            ->forRoute($route)
            ->create([
                'planned_arrival' => $plannedArrival,
                'actual_arrival' => $actualArrival,
            ]);

        $this->assertTrue($stop->isLate());
        $this->assertEquals(30, $stop->getDelayMinutes());
    }

    /** @test */
    public function it_can_check_if_stop_is_within_time_window()
    {
        $route = Route::factory()
            ->forOrganization($this->organization)
            ->create();

        $currentHour = now()->format('H');
        $startHour = (int) $currentHour - 1;
        $endHour = (int) $currentHour + 1;

        $stop = RouteStop::factory()
            ->forRoute($route)
            ->create([
                'time_window_start' => sprintf('%02d:00', $startHour),
                'time_window_end' => sprintf('%02d:00', $endHour),
            ]);

        $this->assertTrue($stop->isWithinTimeWindow());
    }
}
