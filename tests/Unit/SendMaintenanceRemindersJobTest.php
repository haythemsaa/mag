<?php

namespace Tests\Unit;

use App\Jobs\SendMaintenanceReminders;
use App\Models\Maintenance;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\MaintenanceDueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SendMaintenanceRemindersJobTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $fleetManager;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organization
        $this->organization = Organization::factory()->create();

        // Create roles
        $fleetManagerRole = Role::create(['name' => 'fleet-manager']);
        $viewerRole = Role::create(['name' => 'viewer']);

        // Create users with different roles
        $this->fleetManager = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        $this->fleetManager->assignRole($fleetManagerRole);

        $this->viewer = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        $this->viewer->assignRole($viewerRole);
    }

    public function test_sends_notifications_for_upcoming_maintenances(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create maintenance due in 5 days
        $maintenance = Maintenance::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'pending',
            'scheduled_date' => now()->addDays(5),
        ]);

        // Execute the job
        $job = new SendMaintenanceReminders();
        $job->handle();

        // Fleet manager should receive notification
        Notification::assertSentTo(
            $this->fleetManager,
            MaintenanceDueNotification::class,
            function ($notification) use ($maintenance) {
                return $notification->maintenance->id === $maintenance->id;
            }
        );
    }

    public function test_does_not_send_notifications_for_completed_maintenances(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create completed maintenance
        Maintenance::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'completed',
            'scheduled_date' => now()->addDays(5),
            'completed_date' => now(),
        ]);

        // Execute the job
        $job = new SendMaintenanceReminders();
        $job->handle();

        // No notifications should be sent
        Notification::assertNothingSent();
    }

    public function test_does_not_send_notifications_for_maintenances_beyond_7_days(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create maintenance due in 10 days (beyond 7 day window)
        Maintenance::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'pending',
            'scheduled_date' => now()->addDays(10),
        ]);

        // Execute the job
        $job = new SendMaintenanceReminders();
        $job->handle();

        // No notifications should be sent
        Notification::assertNothingSent();
    }

    public function test_only_sends_to_users_with_relevant_roles(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $maintenance = Maintenance::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'pending',
            'scheduled_date' => now()->addDays(5),
        ]);

        // Execute the job
        $job = new SendMaintenanceReminders();
        $job->handle();

        // Fleet manager should receive notification
        Notification::assertSentTo($this->fleetManager, MaintenanceDueNotification::class);

        // Viewer should NOT receive notification
        Notification::assertNotSentTo($this->viewer, MaintenanceDueNotification::class);
    }

    public function test_does_not_send_to_inactive_users(): void
    {
        Notification::fake();

        // Create inactive fleet manager
        $inactiveUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => false,
        ]);
        $inactiveUser->assignRole('fleet-manager');

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        Maintenance::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'pending',
            'scheduled_date' => now()->addDays(5),
        ]);

        // Execute the job
        $job = new SendMaintenanceReminders();
        $job->handle();

        // Inactive user should NOT receive notification
        Notification::assertNotSentTo($inactiveUser, MaintenanceDueNotification::class);
    }

    public function test_filters_by_organization_when_specified(): void
    {
        Notification::fake();

        $otherOrganization = Organization::factory()->create();
        $otherUser = User::factory()->create([
            'organization_id' => $otherOrganization->id,
            'is_active' => true,
        ]);
        $otherUser->assignRole('fleet-manager');

        $vehicle1 = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $vehicle2 = Vehicle::factory()->create(['organization_id' => $otherOrganization->id]);

        Maintenance::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle1->id,
            'status' => 'pending',
            'scheduled_date' => now()->addDays(5),
        ]);

        Maintenance::factory()->create([
            'organization_id' => $otherOrganization->id,
            'vehicle_id' => $vehicle2->id,
            'status' => 'pending',
            'scheduled_date' => now()->addDays(5),
        ]);

        // Execute job for specific organization only
        $job = new SendMaintenanceReminders($this->organization->id);
        $job->handle();

        // Only users from specified organization should receive notifications
        Notification::assertSentTo($this->fleetManager, MaintenanceDueNotification::class);
        Notification::assertNotSentTo($otherUser, MaintenanceDueNotification::class);
    }

    public function test_sends_notifications_for_overdue_maintenances(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create overdue maintenance
        Maintenance::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'pending',
            'scheduled_date' => now()->subDays(2), // Overdue by 2 days
        ]);

        // Execute the job
        $job = new SendMaintenanceReminders();
        $job->handle();

        // Should still send notification for overdue maintenance
        Notification::assertSentTo($this->fleetManager, MaintenanceDueNotification::class);
    }
}
