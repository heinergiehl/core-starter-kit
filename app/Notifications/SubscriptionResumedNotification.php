<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionResumedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $planName,
        public ?string $accessUntil = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Subscription Resumed')
            ->line('Your subscription to '.$this->planName.' has been resumed.')
            ->lineIf($this->accessUntil, 'You will have access until '.$this->accessUntil.'.')
            ->action('View Subscription', url('/billing'))
            ->line('Thank you for continuing with us!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
