<?php

namespace App\Notifications;

use App\Mail\SubscriptionStartedMail;
use App\Notifications\Concerns\SetsMailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a subscription is successfully started.
 */
class SubscriptionStartedNotification extends Notification implements ShouldQueue
{
    use Queueable, SetsMailRecipient;

    public function __construct(
        private readonly ?string $planName = null,
        private readonly ?int $amount = null,
        private readonly ?string $currency = null,
        private readonly array $features = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): SubscriptionStartedMail
    {
        return $this->setMailRecipient($notifiable, new SubscriptionStartedMail(
            user: $notifiable,
            planName: $this->planName,
            amount: $this->amount,
            currency: $this->currency,
            features: $this->features,
        ));
    }
}
