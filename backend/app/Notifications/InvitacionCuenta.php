<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo con el enlace para activar una cuenta que creó la administración:
 * la persona define su contraseña y acepta la política de datos.
 */
class InvitacionCuenta extends Notification
{
    public function __construct(#[\SensitiveParameter] private readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dias = intdiv(config('auth.passwords.invitaciones.expire'), 60 * 24);
        $url = rtrim(config('fet.url_frontend'), '/').'/#/activar?'.http_build_query([
            'token' => $this->token,
            'correo' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Activa tu cuenta de APP FET')
            ->greeting('Hola')
            ->line('La Fundación Escuela Tecnológica de Neiva te creó una cuenta en APP FET, la plataforma de pasantías y prácticas.')
            ->action('Activar mi cuenta', $url)
            ->line("El enlace vence en {$dias} días. Si vence, pide a la coordinación de prácticas que te envíe uno nuevo.")
            ->salutation('Coordinación de prácticas FET');
    }
}
