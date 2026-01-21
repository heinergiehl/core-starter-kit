<?php

namespace App\Notifications;

use App\Mail\PaymentFailedMail;
use App\Notifications\Concerns\SetsMailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a subscription payment fails.
 */
class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable, SetsMailRecipient;

    public function __construct(
        private readonly ?string $planName = null,
        private readonly ?int $amount = null,
        private readonly ?string $currency = null,
        private readonly ?string $failureReason = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): PaymentFailedMail
    {
        return $this->setMailRecipient($notifiable, new PaymentFailedMail(
            user: $notifiable,
            planName: $this->planName,
            amount: $this->amount,
            currency: $this->currency,
            failureReason: $this->failureReason,
        ));
    }
}
