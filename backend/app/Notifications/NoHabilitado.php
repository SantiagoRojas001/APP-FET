<?php

namespace App\Notifications;

use App\Models\Estudiante;
use App\Models\Parametro;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Al estudiante que no alcanzó el promedio: aún no está habilitado, con un
 * mensaje de ánimo.
 */
class NoHabilitado extends Notification
{
    public function __construct(private readonly Estudiante $estudiante) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minimo = number_format(Parametro::promedioMinimo(), 1, ',', '');

        return (new MailMessage)
            ->subject('Resultado de tu habilitación para pasantías y prácticas · APP FET')
            ->greeting("Hola, {$this->estudiante->nombres}")
            ->line("En esta ocasión aún no cumples el requisito para realizar pasantías o prácticas: el promedio requerido es {$minimo}.")
            ->line('Este resultado no define tu camino. Cada semestre es una nueva oportunidad: apóyate en tus docentes, '
                .'organiza tu tiempo y sigue avanzando. Estamos seguros de que pronto lo lograrás.')
            ->line('Si tienes dudas sobre tu promedio, comunícate con la coordinación de prácticas de tu programa.')
            ->salutation('Coordinación de prácticas FET');
    }
}
