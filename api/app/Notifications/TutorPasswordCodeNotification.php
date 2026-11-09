<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Code to set a new password in the owner portal. */
class TutorPasswordCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly int $minutes,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $first = Str::of($this->name)->trim()->explode(' ')->first();

        return (new MailMessage)
            ->subject("{$this->code} es tu código de Zooetia")
            ->greeting("Hola, {$first}")
            ->line('Usa este código para crear una contraseña nueva en el portal de tus mascotas:')
            ->line("**{$this->code}**")
            ->line("Vence en {$this->minutes} minutos. Si no lo pediste, ignora este correo: tu contraseña no cambia.");
    }
}
