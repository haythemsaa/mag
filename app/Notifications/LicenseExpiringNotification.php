<?php

namespace App\Notifications;

use App\Models\Driver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LicenseExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Driver $driver,
        public int $daysUntilExpiry
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
        $daysText = $this->daysUntilExpiry === 0 ? 'expire aujourd\'hui' : "expire dans {$this->daysUntilExpiry} jour(s)";

        return (new MailMessage)
            ->subject("⚠️ Permis de conduire arrivant à expiration : {$this->driver->first_name} {$this->driver->last_name}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le permis de conduire d'un conducteur {$daysText} :")
            ->line("**Conducteur** : {$this->driver->first_name} {$this->driver->last_name}")
            ->line("**Numéro de permis** : {$this->driver->license_number}")
            ->line("**Type de permis** : {$this->driver->license_type}")
            ->line("**Date d'expiration** : {$this->driver->license_expiry_date->format('d/m/Y')}")
            ->when($this->driver->email, function ($mail) {
                return $mail->line("**Email** : {$this->driver->email}");
            })
            ->when($this->driver->phone, function ($mail) {
                return $mail->line("**Téléphone** : {$this->driver->phone}");
            })
            ->action('Voir le conducteur', url("/drivers/{$this->driver->id}"))
            ->line($this->daysUntilExpiry <= 7
                ? '🔴 **ACTION URGENTE REQUISE** - Le conducteur ne peut plus conduire après expiration !'
                : '⚠️ **Action requise** - Prenez contact avec le conducteur pour renouveler son permis.')
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
            'type' => 'license_expiring',
            'driver_id' => $this->driver->id,
            'driver_name' => $this->driver->first_name . ' ' . $this->driver->last_name,
            'license_number' => $this->driver->license_number,
            'license_type' => $this->driver->license_type,
            'expiry_date' => $this->driver->license_expiry_date->toDateString(),
            'days_until_expiry' => $this->daysUntilExpiry,
            'urgency' => $this->daysUntilExpiry <= 7 ? 'high' : 'medium',
        ];
    }
}

