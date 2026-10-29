<?php

namespace App\Notifications;

use App\Models\Vinculacion;
use App\Services\CartaBienvenida;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * El docente autorizó la práctica. Al estudiante le llega la carta de
 * bienvenida (adjunta en el correo); a la empresa, el aviso de inicio.
 */
class PracticaAutorizada extends Aviso
{
    public function __construct(private readonly Vinculacion $vinculacion, private readonly bool $paraEstudiante) {}

    protected function titulo(): string
    {
        return $this->paraEstudiante ? "Te damos la bienvenida a tu {$this->modalidad()}" : 'Práctica autorizada';
    }

    protected function mensaje(): string
    {
        $inicio = $this->vinculacion->fecha_inicio->format('d/m/Y');

        return $this->paraEstudiante
            ? "La FET autorizó tu {$this->modalidad()} en {$this->vinculacion->empresa->razon_social} desde el {$inicio}. "
                .'Te enviamos la carta de bienvenida. Sube tu ARL y reporta tus horas cada semana en APP FET.'
            : "La FET autorizó la {$this->modalidad()} de {$this->vinculacion->estudiante->nombre_completo} desde el {$inicio}. "
                .'Solo tendrás que aprobar sus horas semanales y evaluarlo al final.';
    }

    protected function enlace(): string
    {
        return $this->paraEstudiante ? '/pasantia' : "/vinculacion/{$this->vinculacion->id}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $correo = parent::toMail($notifiable);

        if ($this->paraEstudiante) {
            $correo->attachData(
                app(CartaBienvenida::class)->pdf($this->vinculacion),
                'carta-de-bienvenida-'.Str::slug($this->vinculacion->estudiante->nombre_completo).'.pdf',
                ['mime' => 'application/pdf'],
            );
        }

        return $correo;
    }

    private function modalidad(): string
    {
        return mb_strtolower($this->vinculacion->tipo->label());
    }
}
