<?php

namespace App\Notifications;

use App\Models\SolicitudConvenio;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * A la empresa que pidió el convenio (aún no tiene cuenta): el convenio
 * adjunto y el enlace para registrarse con él.
 */
class ConvenioParaRegistro extends Notification
{
    public function __construct(private readonly SolicitudConvenio $solicitud, private readonly string $pdf) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Convenio de prácticas con la FET · APP FET')
            ->greeting("Hola, {$this->solicitud->contacto_nombre}")
            ->line("Te enviamos el convenio de pasantías y prácticas entre la FET y {$this->solicitud->razon_social}.")
            ->line('Fírmalo con tu representante legal y registra tu empresa en APP FET adjuntando el convenio firmado.')
            ->action('Registrar mi empresa', rtrim(config('fet.url_frontend'), '/').'/empresasRegistro')
            ->attachData($this->pdf, 'convenio-fet-'.Str::slug($this->solicitud->razon_social).'.pdf', ['mime' => 'application/pdf'])
            ->salutation('Coordinación de prácticas FET');
    }
}
