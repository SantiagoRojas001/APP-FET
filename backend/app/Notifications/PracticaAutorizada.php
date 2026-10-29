<?php

namespace App\Notifications;

use App\Models\Vinculacion;
use App\Services\CartaBienvenida;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Al estudiante, cuando el director envía la carta de presentación: empieza
 * su práctica y recibe la carta de bienvenida (adjunta en el correo).
 */
class PracticaAutorizada extends Aviso
{
    public function __construct(private readonly Vinculacion $vinculacion) {}

    protected function titulo(): string
    {
        return "Te damos la bienvenida a tu {$this->modalidad()}";
    }

    protected function mensaje(): string
    {
        $inicio = $this->vinculacion->fecha_inicio->format('d/m/Y');

        return "La FET autorizó tu {$this->modalidad()} en {$this->vinculacion->empresa->razon_social} desde el {$inicio}. "
            .'Te enviamos la carta de bienvenida. Sube tu ARL y reporta tus horas cada semana en APP FET.';
    }

    protected function enlace(): string
    {
        return '/pasantia';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return parent::toMail($notifiable)->attachData(
            app(CartaBienvenida::class)->pdf($this->vinculacion),
            'carta-de-bienvenida-'.Str::slug($this->vinculacion->estudiante->nombre_completo).'.pdf',
            ['mime' => 'application/pdf'],
        );
    }

    private function modalidad(): string
    {
        return mb_strtolower($this->vinculacion->tipo->label());
    }
}
