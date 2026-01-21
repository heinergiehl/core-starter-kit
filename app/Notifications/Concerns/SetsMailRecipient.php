<?php

namespace App\Notifications\Concerns;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;

trait SetsMailRecipient
{
    protected function setMailRecipient(object $notifiable, Mailable $mail): Mailable
    {
        $route = $this->resolveMailRoute($notifiable);

        if ($route) {
            $mail->to($route);
        }

        return $mail;
    }

    private function resolveMailRoute(object $notifiable): array|string|Address|null
    {
        if (method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor('mail', $this);

            if (!empty($route)) {
                return $route;
            }
        }

        $email = data_get($notifiable, 'email');

        return $email ?: null;
    }
}
