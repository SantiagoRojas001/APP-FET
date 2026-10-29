<?php

namespace App\Notifications;

use App\Models\Cita;
use App\Services\CartaPresentacion;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * A la empresa: el director de programa le presenta al estudiante (carta
 * adjunta en el correo) y la práctica queda registrada.
 */
class CartaPresentacionEnviada extends Aviso
{
    public function __construct(private readonly Cita $cita) {}

    protected function titulo(): string
    {
        return "Carta de presentación de {$this->cita->estudiante->nombre_completo}";
    }

    protected function mensaje(): string
    {
        return "La FET te presenta a {$this->cita->estudiante->nombre_completo}, que inicia su práctica el "
            .$this->cita->vinculacion->fecha_inicio->format('d/m/Y').'. Solo tendrás que aprobar sus horas semanales y evaluarlo al final.';
    }

    protected function enlace(): string
    {
        return "/vinculacion/{$this->cita->vinculacion_id}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        return parent::toMail($notifiable)->attachData(
            app(CartaPresentacion::class)->pdf($this->cita),
            'carta-de-presentacion-'.Str::slug($this->cita->estudiante->nombre_completo).'.pdf',
            ['mime' => 'application/pdf'],
        );
    }
}
