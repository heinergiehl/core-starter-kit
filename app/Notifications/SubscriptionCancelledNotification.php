<?php

namespace App\Notifications;

use App\Mail\SubscriptionCancelledMail;
use App\Notifications\Concerns\SetsMailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a subscription is cancelled.
 */
class SubscriptionCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable, SetsMailRecipient;

    public function __construct(
        private readonly ?string $planName = null,
        private readonly ?string $accessUntil = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): SubscriptionCancelledMail
    {
        return $this->setMailRecipient($notifiable, new SubscriptionCancelledMail(
            user: $notifiable,
            planName: $this->planName,
            accessUntil: $this->accessUntil,
        ));
    }
}
