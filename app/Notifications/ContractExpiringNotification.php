<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Contract $contract,
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
        $vehicle = $this->contract->vehicle;
        $daysText = $this->daysUntilExpiry === 0 ? 'expire aujourd\'hui' : "expire dans {$this->daysUntilExpiry} jour(s)";
        $typeLabels = [
            'lease' => 'Location',
            'insurance' => 'Assurance',
            'maintenance' => 'Maintenance',
            'rental' => 'Location courte durée',
        ];
        $typeLabel = $typeLabels[$this->contract->type] ?? $this->contract->type;

        return (new MailMessage)
            ->subject("Contrat arrivant à expiration : {$vehicle->registration_number}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Un contrat {$daysText} pour le véhicule suivant :")
            ->line("**Véhicule** : {$vehicle->make} {$vehicle->model} ({$vehicle->registration_number})")
            ->line("**Type de contrat** : {$typeLabel}")
            ->line("**Numéro** : {$this->contract->contract_number}")
            ->line("**Fournisseur** : {$this->contract->supplier_name}")
            ->line("**Date d'expiration** : {$this->contract->end_date->format('d/m/Y')}")
            ->when($this->contract->auto_renewal, function ($mail) {
                return $mail->line('**⚠️ Renouvellement automatique activé**');
            })
            ->when($this->contract->monthly_cost, function ($mail) {
                return $mail->line("**Coût mensuel** : {$this->contract->monthly_cost} EUR");
            })
            ->action('Voir le contrat', url("/contracts/{$this->contract->id}"))
            ->line($this->daysUntilExpiry <= 7
                ? '⚠️ **Action requise rapidement !**'
                : 'Pensez à préparer le renouvellement ou la résiliation.')
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
            'type' => 'contract_expiring',
            'contract_id' => $this->contract->id,
            'vehicle_id' => $this->contract->vehicle_id,
            'vehicle_registration' => $this->contract->vehicle->registration_number,
            'contract_type' => $this->contract->type,
            'contract_number' => $this->contract->contract_number,
            'supplier_name' => $this->contract->supplier_name,
            'end_date' => $this->contract->end_date->toDateString(),
            'days_until_expiry' => $this->daysUntilExpiry,
            'auto_renewal' => $this->contract->auto_renewal,
            'monthly_cost' => $this->contract->monthly_cost,
        ];
    }
}
