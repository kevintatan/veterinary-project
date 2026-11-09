<?php

namespace App\Notifications;

use App\Models\Clinic;
use App\Models\Documents\PetDocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** "Please read and sign this document" (remote signing by email, D3). */
class DocumentSignRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Clinic $clinic,
        public readonly string $ownerName,
        public readonly string $title,
        public readonly string $petName,
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
            ->subject("{$this->clinic->name}: documento para firmar de {$this->petName}")
            ->greeting("Hola, {$first}")
            ->line("**{$this->clinic->name}** te envió el documento **{$this->title}** de {$this->petName} para que lo leas y lo firmes desde tu celular o computador.")
            ->action('Leer y firmar', $this->url)
            ->line('Para confirmar que eres tú, te pediremos los últimos 4 dígitos de tu documento de identidad.')
            ->line('El enlace vence en '.PetDocumentRequest::HOURS.' horas y sirve una sola vez.');
    }
}
