<?php

namespace App\Notifications;

use App\Models\Parametro;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Al estudiante que no puede formar parte del programa de pasantías y
 * prácticas (por su semestre o su promedio): su nombre, su código, el motivo
 * y un mensaje de ánimo. Se envía a la dirección del listado, porque quien no
 * queda habilitado no se registra en la app.
 */
class NoHabilitado extends Notification
{
    public const POR_SEMESTRE = 'semestre';

    public const POR_PROMEDIO = 'promedio';

    /**
     * @param  array{nombres: string, apellidos: string, codigo?: string|null, semestre?: int|null, promedio?: float|null}  $estudiante
     */
    public function __construct(private readonly array $estudiante, private readonly string $motivo) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $e = $this->estudiante;

        return (new MailMessage)
            ->subject('Resultado de tu inscripción a pasantías y prácticas · APP FET')
            ->greeting("Hola, {$e['nombres']}")
            ->line("Estudiante: {$e['nombres']} {$e['apellidos']}".(filled($e['codigo'] ?? null) ? " · Código: {$e['codigo']}" : ''))
            ->line('En esta ocasión no puedes formar parte del programa de pasantías y prácticas de la FET '.$this->razon().'.')
            ->line('Este resultado no define tu camino. Cada semestre es una nueva oportunidad: apóyate en tus docentes, '
                .'organiza tu tiempo y sigue avanzando. Estamos seguros de que pronto lo lograrás.')
            ->line('Si crees que hay un error, comunícate con la coordinación de prácticas de tu programa.')
            ->salutation('Coordinación de prácticas FET');
    }

    private function razon(): string
    {
        $formato = fn (float $n) => number_format($n, 2, ',', '');

        if ($this->motivo === self::POR_PROMEDIO) {
            return "porque tu promedio ({$formato((float) $this->estudiante['promedio'])}) es inferior al mínimo requerido ({$formato(Parametro::promedioMinimo())})";
        }

        $habilitados = implode(', ', array_map(fn (int $s) => "{$s}.º", Parametro::semestresHabilitados()));

        return "porque cursas {$this->estudiante['semestre']}.º semestre y en este periodo el proceso es para los semestres {$habilitados}";
    }
}
