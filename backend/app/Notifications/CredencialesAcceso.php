<?php

namespace App\Notifications;

use App\Models\Estudiante;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Al estudiante habilitado: usuario y contraseña para su primer ingreso.
 * Solo por correo (la contraseña nunca se guarda en los avisos de la app).
 */
class CredencialesAcceso extends Notification
{
    public function __construct(private readonly Estudiante $estudiante, #[\SensitiveParameter] private readonly string $password) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $modalidad = mb_strtolower($this->estudiante->modalidad_grado?->label() ?? 'práctica');

        return (new MailMessage)
            ->subject('Ya puedes iniciar tu proceso de '.$modalidad.' · APP FET')
            ->greeting("Hola, {$this->estudiante->nombres}")
            ->line("¡Felicitaciones! Con tu promedio de {$this->formato($this->estudiante->promedio)} cumples el requisito para realizar tu {$modalidad}.")
            ->line('Estos son tus datos para ingresar a APP FET por primera vez:')
            ->line("Usuario: {$notifiable->correo}")
            ->line("Contraseña: {$this->password}")
            ->action('Ingresar a APP FET', rtrim(config('fet.url_frontend'), '/').'/#/login')
            ->line('Al ingresar deberás cambiar la contraseña. Luego crea y publica tu hoja de vida para que las empresas te encuentren.')
            ->salutation('Coordinación de prácticas FET');
    }

    private function formato(float $promedio): string
    {
        return number_format($promedio, 2, ',', '');
    }
}
