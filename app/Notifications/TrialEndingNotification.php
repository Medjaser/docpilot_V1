<?php

namespace App\Notifications;

use App\Models\Company;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Fin d'essai à J-3 et J0, envoyé aux administrateurs de l'entreprise. */
class TrialEndingNotification extends Notification
{
    use BrandedMail, Queueable;

    public function __construct(public Company $company, public int $daysLeft) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = $this->brandedMail($this->company)
            ->greeting(trim('Bonjour '.($notifiable->first_name ?? '')).',')
            ->salutation('L\'équipe DocPilot');

        if ($this->daysLeft === 0) {
            $mail->subject('Votre essai DocPilot se termine aujourd\'hui')
                ->line("La période d'essai de {$this->company->name} se termine aujourd'hui.")
                ->line('Sans abonnement, votre espace passera demain en lecture seule.');
        } else {
            $mail->subject("Plus que {$this->daysLeft} jours d'essai sur DocPilot")
                ->line("La période d'essai de {$this->company->name} se termine dans {$this->daysLeft} jours.")
                ->line('Choisissez une offre pour continuer à suivre vos sous-traitants sans interruption.');
        }

        return $mail->action('Choisir une offre', route('subscription'));
    }
}
