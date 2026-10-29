<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso que llega dentro de la app (campana de notificaciones) y por correo.
 * Cada aviso concreto define el título, el mensaje y la ruta del frontend
 * que abre al hacer clic.
 */
abstract class Aviso extends Notification
{
    abstract protected function titulo(): string;

    abstract protected function mensaje(): string;

    /** Ruta del frontend (sin #), por ejemplo '/vinculacion/4'. */
    protected function enlace(): ?string
    {
        return null;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array{titulo: string, mensaje: string, enlace: string|null} */
    public function toArray(object $notifiable): array
    {
        return ['titulo' => $this->titulo(), 'mensaje' => $this->mensaje(), 'enlace' => $this->enlace()];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $correo = (new MailMessage)
            ->subject($this->titulo().' · APP FET')
            ->greeting('Hola')
            ->line($this->mensaje());

        if ($enlace = $this->enlace()) {
            $correo->action('Abrir en APP FET', rtrim(config('fet.url_frontend'), '/').'/#'.$enlace);
        }

        return $correo->salutation('Coordinación de prácticas FET');
    }
}
