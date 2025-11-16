<?php

namespace Tests\Unit;

use App\Jobs\SendLicenseExpiryAlerts;
use App\Models\Driver;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\LicenseExpiringNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SendLicenseExpiryAlertsJobTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $organizationAdmin;
    private User $fleetManager;
    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organization
        $this->organization = Organization::factory()->create();

        // Create roles
        $adminRole = Role::create(['name' => 'organization-admin']);
        $fleetManagerRole = Role::create(['name' => 'fleet-manager']);
        $driverRole = Role::create(['name' => 'driver']);

        // Create users with different roles
        $this->organizationAdmin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        $this->organizationAdmin->assignRole($adminRole);

        $this->fleetManager = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        $this->fleetManager->assignRole($fleetManagerRole);

        $this->driver = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        $this->driver->assignRole($driverRole);
    }

    public function test_sends_notifications_for_licenses_expiring_in_60_days(): void
    {
        Notification::fake();

        // Create driver with license expiring in 45 days
        $driver = Driver::factory()->create([
            'organization_id' => $this->organization->id,
            'license_expiry_date' => now()->addDays(45),
        ]);

        // Execute the job
        $job = new SendLicenseExpiryAlerts();
        $job->handle();

        // Admin and fleet manager should receive notifications
        Notification::assertSentTo($this->organizationAdmin, LicenseExpiringNotification::class);
        Notification::assertSentTo($this->fleetManager, LicenseExpiringNotification::class);
    }

    public function test_does_not_send_notifications_for_licenses_beyond_60_days(): void
    {
        Notification::fake();

        // Create driver with license expiring in 65 days (beyond 60 day window)
        Driver::factory()->create([
            'organization_id' => $this->organization->id,
            'license_expiry_date' => now()->addDays(65),
        ]);

        // Execute the job
        $job = new SendLicenseExpiryAlerts();
        $job->handle();

        // No notifications should be sent
        Notification::assertNothingSent();
    }

    public function test_sends_notifications_for_expired_licenses(): void
    {
        Notification::fake();

        // Create driver with already expired license
        Driver::factory()->create([
            'organization_id' => $this->organization->id,
            'license_expiry_date' => now()->subDays(5),
        ]);

        // Execute the job
        $job = new SendLicenseExpiryAlerts();
        $job->handle();

        // Should still send critical notifications
        Notification::assertSentTo($this->organizationAdmin, LicenseExpiringNotification::class);
        Notification::assertSentTo($this->fleetManager, LicenseExpiringNotification::class);
    }

    public function test_only_sends_to_users_with_relevant_roles(): void
    {
        Notification::fake();

        Driver::factory()->create([
            'organization_id' => $this->organization->id,
            'license_expiry_date' => now()->addDays(30),
        ]);

        // Execute the job
        $job = new SendLicenseExpiryAlerts();
        $job->handle();

        // Admin and fleet manager should receive notifications
        Notification::assertSentTo($this->organizationAdmin, LicenseExpiringNotification::class);
        Notification::assertSentTo($this->fleetManager, LicenseExpiringNotification::class);

        // Driver should NOT receive notification
        Notification::assertNotSentTo($this->driver, LicenseExpiringNotification::class);
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

        Driver::factory()->create([
            'organization_id' => $this->organization->id,
            'license_expiry_date' => now()->addDays(30),
        ]);

        // Execute the job
        $job = new SendLicenseExpiryAlerts();
        $job->handle();

        // Inactive user should NOT receive notification
        Notification::assertNotSentTo($inactiveUser, LicenseExpiringNotification::class);
    }

    public function test_sends_multiple_notifications_at_different_intervals(): void
    {
        Notification::fake();

        // Create drivers at different expiry intervals
        $driver60Days = Driver::factory()->create([
            'organization_id' => $this->organization->id,
            'license_expiry_date' => now()->addDays(60),
        ]);

        $driver30Days = Driver::factory()->create([
            'organization_id' => $this->organization->id,
            'license_expiry_date' => now()->addDays(30),
        ]);

        $driver7Days = Driver::factory()->create([
            'organization_id' => $this->organization->id,
            'license_expiry_date' => now()->addDays(7),
        ]);

        // Execute the job
        $job = new SendLicenseExpiryAlerts();
        $job->handle();

        // Should send 3 notifications to each user (one for each driver)
        Notification::assertSentTo(
            $this->organizationAdmin,
            LicenseExpiringNotification::class,
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

        Driver::factory()->create([
            'organization_id' => $this->organization->id,
            'license_expiry_date' => now()->addDays(30),
        ]);

        Driver::factory()->create([
            'organization_id' => $otherOrganization->id,
            'license_expiry_date' => now()->addDays(30),
        ]);

        // Execute job for specific organization only
        $job = new SendLicenseExpiryAlerts($this->organization->id);
        $job->handle();

        // Only users from specified organization should receive notifications
        Notification::assertSentTo($this->organizationAdmin, LicenseExpiringNotification::class);
        Notification::assertNotSentTo($otherUser, LicenseExpiringNotification::class);
    }

    public function test_does_not_send_for_drivers_without_license_expiry_date(): void
    {
        Notification::fake();

        // Create driver without license expiry date
        Driver::factory()->create([
            'organization_id' => $this->organization->id,
            'license_expiry_date' => null,
        ]);

        // Execute the job
        $job = new SendLicenseExpiryAlerts();
        $job->handle();

        // No notifications should be sent
        Notification::assertNothingSent();
    }

    public function test_notification_includes_correct_urgency_level(): void
    {
        Notification::fake();

        // Create driver with license expiring in 5 days (high urgency)
        Driver::factory()->create([
            'organization_id' => $this->organization->id,
            'license_expiry_date' => now()->addDays(5),
        ]);

        // Execute the job
        $job = new SendLicenseExpiryAlerts();
        $job->handle();

        Notification::assertSentTo(
            $this->organizationAdmin,
            LicenseExpiringNotification::class,
            function ($notification) {
                // Verify the notification contains the driver and days until expiry
                return $notification->daysUntilExpiry === 5;
            }
        );
    }
}
