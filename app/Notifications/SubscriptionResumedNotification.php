<?php

namespace App\Notifications;

use App\Mail\SubscriptionResumedMail;
use App\Notifications\Concerns\SetsMailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a subscription is resumed.
 */
class SubscriptionResumedNotification extends Notification implements ShouldQueue
{
    use Queueable, SetsMailRecipient;

    public function __construct(
        private readonly ?string $planName = null,
        private readonly ?string $accessUntil = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): SubscriptionResumedMail
    {
        return $this->setMailRecipient($notifiable, new SubscriptionResumedMail(
            user: $notifiable,
            planName: $this->planName,
        ));
    }
}

