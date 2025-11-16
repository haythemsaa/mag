<?php

namespace Tests\Unit;

use App\Models\Maintenance;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\MaintenanceDueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class MaintenanceDueNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $user;
    private Vehicle $vehicle;
    private Maintenance $maintenance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
            'registration_number' => 'AB-123-CD',
            'make' => 'Renault',
            'model' => 'Kangoo',
        ]);
        $this->maintenance = Maintenance::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'type' => 'preventive',
            'description' => 'Révision annuelle',
            'scheduled_date' => now()->addDays(5),
        ]);
    }

    public function test_notification_uses_correct_channels(): void
    {
        $notification = new MaintenanceDueNotification($this->maintenance, 5);

        $channels = $notification->via($this->user);

        $this->assertContains('mail', $channels);
        $this->assertContains('database', $channels);
    }

    public function test_mail_notification_has_correct_structure(): void
    {
        $notification = new MaintenanceDueNotification($this->maintenance, 5);

        $mailMessage = $notification->toMail($this->user);

        $this->assertEquals("Maintenance programmée : AB-123-CD", $mailMessage->subject);
        $this->assertStringContainsString('dans 5 jour(s)', $mailMessage->introLines[0]);
        $this->assertStringContainsString('Renault', $mailMessage->introLines[1]);
        $this->assertStringContainsString('Kangoo', $mailMessage->introLines[1]);
        $this->assertStringContainsString('Révision annuelle', $mailMessage->introLines[2]);
    }

    public function test_mail_notification_shows_today_when_due_now(): void
    {
        $notification = new MaintenanceDueNotification($this->maintenance, 0);

        $mailMessage = $notification->toMail($this->user);

        $this->assertStringContainsString("aujourd'hui", $mailMessage->introLines[0]);
    }

    public function test_database_notification_has_correct_structure(): void
    {
        $notification = new MaintenanceDueNotification($this->maintenance, 5);

        $databaseData = $notification->toDatabase($this->user);

        $this->assertArrayHasKey('maintenance_id', $databaseData);
        $this->assertArrayHasKey('vehicle_id', $databaseData);
        $this->assertArrayHasKey('vehicle_registration', $databaseData);
        $this->assertArrayHasKey('type', $databaseData);
        $this->assertArrayHasKey('scheduled_date', $databaseData);
        $this->assertArrayHasKey('days_until_due', $databaseData);
        $this->assertArrayHasKey('message', $databaseData);

        $this->assertEquals($this->maintenance->id, $databaseData['maintenance_id']);
        $this->assertEquals($this->vehicle->id, $databaseData['vehicle_id']);
        $this->assertEquals('AB-123-CD', $databaseData['vehicle_registration']);
        $this->assertEquals(5, $databaseData['days_until_due']);
    }

    public function test_notification_action_url_is_correct(): void
    {
        $notification = new MaintenanceDueNotification($this->maintenance, 5);

        $mailMessage = $notification->toMail($this->user);

        $this->assertStringContainsString(
            "/maintenances/{$this->maintenance->id}",
            $mailMessage->actionUrl
        );
    }

    public function test_notification_can_be_sent_successfully(): void
    {
        NotificationFacade::fake();

        $this->user->notify(new MaintenanceDueNotification($this->maintenance, 5));

        NotificationFacade::assertSentTo(
            $this->user,
            MaintenanceDueNotification::class,
            function ($notification) {
                return $notification->maintenance->id === $this->maintenance->id
                    && $notification->daysUntilDue === 5;
            }
        );
    }

    public function test_notification_is_queued(): void
    {
        $notification = new MaintenanceDueNotification($this->maintenance, 5);

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification);
    }
}
