<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\TheftAlert;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TheftAlertTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;
    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_list_theft_alerts(): void
    {
        TheftAlert::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        // Create alerts for another organization (should not be visible)
        TheftAlert::factory()->count(3)->create();

        $response = $this->getJson('/api/theft-alerts');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function it_can_create_theft_alert(): void
    {
        $alertData = [
            'vehicle_id' => $this->vehicle->id,
            'type' => 'unauthorized_ignition',
            'severity' => 'critical',
            'description' => 'Unauthorized ignition detected outside work hours',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'ignition_on' => true,
            'outside_work_hours' => true,
        ];

        $response = $this->postJson('/api/theft-alerts', $alertData);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'unauthorized_ignition')
            ->assertJsonPath('data.severity', 'critical')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'alert_number',
                    'type',
                    'status',
                    'severity',
                    'description',
                ],
            ]);

        $this->assertDatabaseHas('theft_alerts', [
            'vehicle_id' => $this->vehicle->id,
            'type' => 'unauthorized_ignition',
            'severity' => 'critical',
            'organization_id' => $this->organization->id,
        ]);
    }

    /** @test */
    public function it_generates_unique_alert_numbers(): void
    {
        $alert1 = TheftAlert::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $alert2 = TheftAlert::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $this->assertNotEquals($alert1->alert_number, $alert2->alert_number);
        $this->assertStringStartsWith('TH-', $alert1->alert_number);
        $this->assertStringStartsWith('TH-', $alert2->alert_number);
    }

    /** @test */
    public function it_can_show_single_theft_alert(): void
    {
        $alert = TheftAlert::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->getJson("/api/theft-alerts/{$alert->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $alert->id)
            ->assertJsonPath('data.alert_number', $alert->alert_number)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'alert_number',
                    'type',
                    'status',
                    'severity',
                    'vehicle',
                ],
            ]);
    }

    /** @test */
    public function it_cannot_view_alert_from_another_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherVehicle = Vehicle::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);
        $alert = TheftAlert::factory()->create([
            'organization_id' => $otherOrganization->id,
            'vehicle_id' => $otherVehicle->id,
        ]);

        $response = $this->getJson("/api/theft-alerts/{$alert->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_update_theft_alert(): void
    {
        $alert = TheftAlert::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $updateData = [
            'investigation_notes' => 'Contacted driver, awaiting response',
            'admin_notes' => 'High priority alert',
        ];

        $response = $this->putJson("/api/theft-alerts/{$alert->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.investigation_notes', 'Contacted driver, awaiting response');

        $this->assertDatabaseHas('theft_alerts', [
            'id' => $alert->id,
            'investigation_notes' => 'Contacted driver, awaiting response',
        ]);
    }

    /** @test */
    public function it_can_delete_theft_alert(): void
    {
        $alert = TheftAlert::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->deleteJson("/api/theft-alerts/{$alert->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('theft_alerts', ['id' => $alert->id]);
    }

    /** @test */
    public function it_can_assign_alert_to_user(): void
    {
        $alert = TheftAlert::factory()->pending()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $assignee = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->postJson("/api/theft-alerts/{$alert->id}/assign", [
            'user_id' => $assignee->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Alert assigned successfully');

        $this->assertDatabaseHas('theft_alerts', [
            'id' => $alert->id,
            'assigned_to_user_id' => $assignee->id,
        ]);

        $alert->refresh();
        $this->assertNotNull($alert->assigned_at);
    }

    /** @test */
    public function it_cannot_assign_alert_to_user_from_different_organization(): void
    {
        $alert = TheftAlert::factory()->pending()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $otherOrganization = Organization::factory()->create();
        $otherUser = User::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $response = $this->postJson("/api/theft-alerts/{$alert->id}/assign", [
            'user_id' => $otherUser->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'User must belong to the same organization');
    }

    /** @test */
    public function it_can_start_investigation(): void
    {
        $alert = TheftAlert::factory()->pending()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->postJson("/api/theft-alerts/{$alert->id}/investigate");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Investigation started')
            ->assertJsonPath('data.status', 'investigating');

        $this->assertDatabaseHas('theft_alerts', [
            'id' => $alert->id,
            'status' => 'investigating',
        ]);

        $alert->refresh();
        $this->assertNotNull($alert->investigation_started_at);
    }

    /** @test */
    public function it_cannot_start_investigation_on_non_pending_alert(): void
    {
        $alert = TheftAlert::factory()->investigating()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->postJson("/api/theft-alerts/{$alert->id}/investigate");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Only pending alerts can be moved to investigation');
    }

    /** @test */
    public function it_can_mark_as_false_alarm(): void
    {
        $alert = TheftAlert::factory()->investigating()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->postJson("/api/theft-alerts/{$alert->id}/false-alarm", [
            'resolution_notes' => 'Driver confirmed authorized use',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Alert marked as false alarm')
            ->assertJsonPath('data.status', 'false_alarm');

        $this->assertDatabaseHas('theft_alerts', [
            'id' => $alert->id,
            'status' => 'false_alarm',
            'resolution_notes' => 'Driver confirmed authorized use',
        ]);

        $alert->refresh();
        $this->assertNotNull($alert->resolved_at);
    }

    /** @test */
    public function it_can_confirm_theft(): void
    {
        $alert = TheftAlert::factory()->investigating()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->postJson("/api/theft-alerts/{$alert->id}/confirm-theft", [
            'resolution_notes' => 'Theft confirmed by driver, police notified',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Theft confirmed')
            ->assertJsonPath('data.status', 'confirmed_theft');

        $this->assertDatabaseHas('theft_alerts', [
            'id' => $alert->id,
            'status' => 'confirmed_theft',
        ]);
    }

    /** @test */
    public function it_can_resolve_alert(): void
    {
        $alert = TheftAlert::factory()->investigating()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->postJson("/api/theft-alerts/{$alert->id}/resolve", [
            'resolution_notes' => 'Vehicle recovered, issue resolved',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Alert resolved')
            ->assertJsonPath('data.status', 'resolved');

        $alert->refresh();
        $this->assertNotNull($alert->resolved_at);
    }

    /** @test */
    public function it_can_notify_police(): void
    {
        $alert = TheftAlert::factory()->investigating()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->postJson("/api/theft-alerts/{$alert->id}/notify-police", [
            'police_reference_number' => 'POL-2025-12345',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Police notification recorded')
            ->assertJsonPath('data.police_notified', true)
            ->assertJsonPath('data.police_reference_number', 'POL-2025-12345');

        $this->assertDatabaseHas('theft_alerts', [
            'id' => $alert->id,
            'police_notified' => true,
            'police_reference_number' => 'POL-2025-12345',
        ]);

        $alert->refresh();
        $this->assertNotNull($alert->police_notified_at);
    }

    /** @test */
    public function it_cannot_notify_police_twice(): void
    {
        $alert = TheftAlert::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'police_notified' => true,
            'police_notified_at' => now(),
        ]);

        $response = $this->postJson("/api/theft-alerts/{$alert->id}/notify-police");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Police already notified for this alert');
    }

    /** @test */
    public function it_can_link_insurance_claim(): void
    {
        $alert = TheftAlert::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->postJson("/api/theft-alerts/{$alert->id}/insurance-claim", [
            'insurance_claim_number' => 'INS-2025-98765',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Insurance claim linked')
            ->assertJsonPath('data.insurance_claim_number', 'INS-2025-98765');

        $this->assertDatabaseHas('theft_alerts', [
            'id' => $alert->id,
            'insurance_claim_number' => 'INS-2025-98765',
        ]);
    }

    /** @test */
    public function it_can_filter_by_status(): void
    {
        TheftAlert::factory()->pending()->count(3)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        TheftAlert::factory()->investigating()->count(2)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->getJson('/api/theft-alerts?status=pending');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function it_can_filter_by_severity(): void
    {
        TheftAlert::factory()->critical()->count(2)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        TheftAlert::factory()->high()->count(3)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->getJson('/api/theft-alerts?severity=critical');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_can_filter_by_type(): void
    {
        TheftAlert::factory()->unauthorizedIgnition()->count(2)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        TheftAlert::factory()->movementOutsideHours()->count(3)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->getJson('/api/theft-alerts?type=unauthorized_ignition');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_can_filter_by_vehicle(): void
    {
        $vehicle2 = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        TheftAlert::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        TheftAlert::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle2->id,
        ]);

        $response = $this->getJson("/api/theft-alerts?vehicle_id={$this->vehicle->id}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function it_can_filter_unresolved_alerts(): void
    {
        TheftAlert::factory()->pending()->count(2)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        TheftAlert::factory()->investigating()->count(3)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        TheftAlert::factory()->falseAlarm()->count(4)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->getJson('/api/theft-alerts?unresolved=1');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data'); // 2 pending + 3 investigating
    }

    /** @test */
    public function it_can_get_statistics(): void
    {
        TheftAlert::factory()->pending()->count(3)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        TheftAlert::factory()->investigating()->count(2)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        TheftAlert::factory()->falseAlarm()->count(10)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        TheftAlert::factory()->confirmedTheft()->count(1)->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->getJson('/api/theft-alerts/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_alerts',
                    'by_status',
                    'by_severity',
                    'by_type',
                    'average_response_time_minutes',
                    'average_resolution_time_hours',
                    'confirmed_theft_rate',
                ],
            ])
            ->assertJsonPath('data.total_alerts', 16)
            ->assertJsonPath('data.by_status.pending', 3)
            ->assertJsonPath('data.by_status.investigating', 2)
            ->assertJsonPath('data.by_status.false_alarm', 10)
            ->assertJsonPath('data.by_status.confirmed_theft', 1);
    }

    /** @test */
    public function it_validates_required_fields_on_create(): void
    {
        $response = $this->postJson('/api/theft-alerts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vehicle_id', 'type', 'severity', 'description']);
    }

    /** @test */
    public function it_validates_alert_type_values(): void
    {
        $response = $this->postJson('/api/theft-alerts', [
            'vehicle_id' => $this->vehicle->id,
            'type' => 'invalid_type',
            'severity' => 'critical',
            'description' => 'Test alert',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    /** @test */
    public function it_validates_severity_values(): void
    {
        $response = $this->postJson('/api/theft-alerts', [
            'vehicle_id' => $this->vehicle->id,
            'type' => 'unauthorized_ignition',
            'severity' => 'invalid_severity',
            'description' => 'Test alert',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['severity']);
    }

    /** @test */
    public function it_validates_coordinates_range(): void
    {
        $response = $this->postJson('/api/theft-alerts', [
            'vehicle_id' => $this->vehicle->id,
            'type' => 'unauthorized_ignition',
            'severity' => 'critical',
            'description' => 'Test alert',
            'latitude' => 95.0, // Invalid latitude
            'longitude' => 200.0, // Invalid longitude
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }
}
