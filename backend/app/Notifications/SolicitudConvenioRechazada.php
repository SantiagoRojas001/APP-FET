<?php

namespace App\Notifications;

use App\Models\SolicitudConvenio;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A la empresa que pidió el convenio: la FET no puede firmarlo y el motivo.
 */
class SolicitudConvenioRechazada extends Notification
{
    public function __construct(private readonly SolicitudConvenio $solicitud) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu solicitud de convenio con la FET · APP FET')
            ->greeting("Hola, {$this->solicitud->contacto_nombre}")
            ->line("Revisamos la solicitud de convenio de {$this->solicitud->razon_social} y por ahora no podemos firmarlo.")
            ->line("Motivo: {$this->solicitud->observacion}")
            ->line('Si tienes preguntas, responde este correo o escribe a la coordinación de prácticas.')
            ->salutation('Coordinación de prácticas FET');
    }
}
