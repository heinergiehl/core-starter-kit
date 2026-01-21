<?php

namespace App\Notifications;

use App\Mail\WelcomeMail;
use App\Notifications\Concerns\SetsMailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Welcome notification sent when a new user registers.
 */
class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable, SetsMailRecipient;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): WelcomeMail
    {
        return $this->setMailRecipient($notifiable, new WelcomeMail($notifiable));
    }
}
