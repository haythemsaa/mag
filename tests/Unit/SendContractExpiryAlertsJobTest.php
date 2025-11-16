<?php

namespace Tests\Unit;

use App\Jobs\SendContractExpiryAlerts;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\ContractExpiringNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SendContractExpiryAlertsJobTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $organizationAdmin;
    private User $accountant;
    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organization
        $this->organization = Organization::factory()->create();

        // Create roles
        $adminRole = Role::create(['name' => 'organization-admin']);
        $accountantRole = Role::create(['name' => 'accountant']);
        $driverRole = Role::create(['name' => 'driver']);

        // Create users with different roles
        $this->organizationAdmin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        $this->organizationAdmin->assignRole($adminRole);

        $this->accountant = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        $this->accountant->assignRole($accountantRole);

        $this->driver = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        $this->driver->assignRole($driverRole);
    }

    public function test_sends_notifications_for_contracts_expiring_in_30_days(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create contract expiring in 25 days
        $contract = Contract::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'active',
            'end_date' => now()->addDays(25),
        ]);

        // Execute the job
        $job = new SendContractExpiryAlerts();
        $job->handle();

        // Admin and accountant should receive notifications
        Notification::assertSentTo($this->organizationAdmin, ContractExpiringNotification::class);
        Notification::assertSentTo($this->accountant, ContractExpiringNotification::class);
    }

    public function test_does_not_send_notifications_for_inactive_contracts(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create inactive contract
        Contract::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'expired',
            'end_date' => now()->addDays(25),
        ]);

        // Execute the job
        $job = new SendContractExpiryAlerts();
        $job->handle();

        // No notifications should be sent
        Notification::assertNothingSent();
    }

    public function test_does_not_send_notifications_for_contracts_beyond_30_days(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create contract expiring in 35 days (beyond 30 day window)
        Contract::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'active',
            'end_date' => now()->addDays(35),
        ]);

        // Execute the job
        $job = new SendContractExpiryAlerts();
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

        Contract::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'active',
            'end_date' => now()->addDays(20),
        ]);

        // Execute the job
        $job = new SendContractExpiryAlerts();
        $job->handle();

        // Admin and accountant should receive notifications
        Notification::assertSentTo($this->organizationAdmin, ContractExpiringNotification::class);
        Notification::assertSentTo($this->accountant, ContractExpiringNotification::class);

        // Driver should NOT receive notification
        Notification::assertNotSentTo($this->driver, ContractExpiringNotification::class);
    }

    public function test_sends_multiple_notifications_at_different_intervals(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create contracts at different expiry intervals
        $contract30Days = Contract::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'active',
            'end_date' => now()->addDays(30),
        ]);

        $contract14Days = Contract::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'active',
            'end_date' => now()->addDays(14),
        ]);

        $contract7Days = Contract::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'active',
            'end_date' => now()->addDays(7),
        ]);

        // Execute the job
        $job = new SendContractExpiryAlerts();
        $job->handle();

        // Should send 3 notifications to each user (one for each contract)
        Notification::assertSentTo(
            $this->organizationAdmin,
            ContractExpiringNotification::class,
            3
        );
    }

    public function test_filters_by_organization_when_specified(): void
    {
        Notification::fake();

        $otherOrganization = Organization::factory()->create();
        $otherUser = User::factory()->create([
            'organization_id' => $otherOrganization->id,
            'is_active' => true,
        ]);
        $otherUser->assignRole('organization-admin');

        $vehicle1 = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $vehicle2 = Vehicle::factory()->create(['organization_id' => $otherOrganization->id]);

        Contract::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle1->id,
            'status' => 'active',
            'end_date' => now()->addDays(20),
        ]);

        Contract::factory()->create([
            'organization_id' => $otherOrganization->id,
            'vehicle_id' => $vehicle2->id,
            'status' => 'active',
            'end_date' => now()->addDays(20),
        ]);

        // Execute job for specific organization only
        $job = new SendContractExpiryAlerts($this->organization->id);
        $job->handle();

        // Only users from specified organization should receive notifications
        Notification::assertSentTo($this->organizationAdmin, ContractExpiringNotification::class);
        Notification::assertNotSentTo($otherUser, ContractExpiringNotification::class);
    }
}
