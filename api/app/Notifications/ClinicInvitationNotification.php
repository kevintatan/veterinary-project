<?php

namespace App\Notifications;

use App\Auth\ClinicRole;
use App\Models\Clinic;
use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClinicInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly Clinic $clinic,
        public readonly string $plainToken,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/invitacion/'.$this->plainToken;
        $role = ClinicRole::from($this->invitation->role)->label();

        return (new MailMessage)
            ->subject("Te invitaron a {$this->clinic->name} en Zooetia")
            ->greeting('¡Hola!')
            ->line("{$this->invitation->inviter->name} te invitó a unirte a **{$this->clinic->name}** como **{$role}**.")
            ->action('Aceptar invitación', $url)
            ->line('La invitación vence el '.$this->invitation->expires_at->timezone($this->clinic->timezone)->format('d/m/Y').'.')
            ->line('Si no esperabas este correo, puedes ignorarlo.');
    }
}
