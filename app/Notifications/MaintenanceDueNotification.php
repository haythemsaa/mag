<?php

namespace App\Notifications;

use App\Models\Maintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Maintenance $maintenance,
        public int $daysUntilDue
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $vehicle = $this->maintenance->vehicle;
        $daysText = $this->daysUntilDue === 0 ? 'aujourd\'hui' : "dans {$this->daysUntilDue} jour(s)";

        return (new MailMessage)
            ->subject("Maintenance programmée : {$vehicle->registration_number}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Une maintenance est prévue {$daysText} pour le véhicule suivant :")
            ->line("**Véhicule** : {$vehicle->make} {$vehicle->model} ({$vehicle->registration_number})")
            ->line("**Type** : {$this->maintenance->type}")
            ->line("**Date prévue** : {$this->maintenance->scheduled_date->format('d/m/Y')}")
            ->line("**Atelier** : {$this->maintenance->workshop?->name ?? 'Non assigné'}")
            ->when($this->maintenance->description, function ($mail) {
                return $mail->line("**Description** : {$this->maintenance->description}");
            })
            ->action('Voir les détails', url("/maintenances/{$this->maintenance->id}"))
            ->line('Merci d\'utiliser FleetManager Pro.')
            ->salutation('Cordialement, L\'équipe FleetManager');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'maintenance_due',
            'maintenance_id' => $this->maintenance->id,
            'vehicle_id' => $this->maintenance->vehicle_id,
            'vehicle_registration' => $this->maintenance->vehicle->registration_number,
            'maintenance_type' => $this->maintenance->type,
            'scheduled_date' => $this->maintenance->scheduled_date->toDateString(),
            'days_until_due' => $this->daysUntilDue,
            'workshop_name' => $this->maintenance->workshop?->name,
        ];
    }
}
