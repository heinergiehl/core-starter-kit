<?php

namespace App\Notifications;

use App\Mail\SubscriptionTrialStartedMail;
use App\Notifications\Concerns\SetsMailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a subscription trial starts.
 */
class SubscriptionTrialStartedNotification extends Notification implements ShouldQueue
{
    use Queueable, SetsMailRecipient;

    public function __construct(
        private readonly ?string $planName = null,
        private readonly ?string $trialEndsAt = null,
        private readonly array $features = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): SubscriptionTrialStartedMail
    {
        return $this->setMailRecipient($notifiable, new SubscriptionTrialStartedMail(
            user: $notifiable,
            planName: $this->planName,
            trialEndsAt: $this->trialEndsAt,
            features: $this->features,
        ));
    }
}
