<?php

namespace App\Notifications;

use App\Mail\SubscriptionPlanChangedMail;
use App\Notifications\Concerns\SetsMailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a subscription plan is updated.
 */
class SubscriptionPlanChangedNotification extends Notification implements ShouldQueue
{
    use Queueable, SetsMailRecipient;

    public function __construct(
        private readonly ?string $previousPlanName = null,
        private readonly ?string $newPlanName = null,
        private readonly ?string $effectiveDate = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): SubscriptionPlanChangedMail
    {
        return $this->setMailRecipient($notifiable, new SubscriptionPlanChangedMail(
            user: $notifiable,
            previousPlanName: $this->previousPlanName,
            newPlanName: $this->newPlanName,
            effectiveDate: $this->effectiveDate,
        ));
    }
}
