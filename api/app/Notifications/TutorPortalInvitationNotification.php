<?php

namespace App\Notifications;

use App\Models\Clinic;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Portal invitation by email (free channel; WhatsApp goes through the clinic's own app). */
class TutorPortalInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Clinic $clinic,
        public readonly string $ownerName,
        public readonly string $url,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $first = Str::of($this->ownerName)->trim()->explode(' ')->first();

        return (new MailMessage)
            ->subject("{$this->clinic->name} te invita a ver a tu mascota en Zooetia")
            ->greeting("¡Hola, {$first}!")
            ->line("**{$this->clinic->name}** te invita a su portal: ahí ves el carné con QR de tu mascota, sus vacunas y tus próximas citas.")
            ->action('Activar mi acceso', $this->url)
            ->line('Solo lo activas una vez; después tu sesión queda abierta en tu celular.')
            ->line('El enlace vence en 7 días. Si no esperabas este correo, puedes ignorarlo.');
    }
}
