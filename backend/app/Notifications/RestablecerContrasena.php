<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo con el enlace para crear una contraseña nueva. El enlace abre la
 * pantalla de restablecimiento del frontend con el token y el correo.
 */
class RestablecerContrasena extends Notification
{
    public function __construct(#[\SensitiveParameter] private readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutos = config('auth.passwords.users.expire');
        $url = rtrim(config('fet.url_frontend'), '/').'/#/restablecer?'.http_build_query([
            'token' => $this->token,
            'correo' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Restablece tu contraseña de APP FET')
            ->greeting('Hola')
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta en APP FET.')
            ->action('Crear contraseña nueva', $url)
            ->line("El enlace vence en {$minutos} minutos.")
            ->line('Si no lo solicitaste, ignora este correo: tu contraseña no cambiará.')
            ->salutation('Coordinación de prácticas FET');
    }
}
