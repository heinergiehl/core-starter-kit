<?php

namespace App\Notifications;

use App\Mail\PaymentSuccessfulMail;
use App\Notifications\Concerns\SetsMailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a one-time payment is successful.
 */
class PaymentSuccessfulNotification extends Notification implements ShouldQueue
{
    use Queueable, SetsMailRecipient;

    public function __construct(
        private readonly ?string $planName = null,
        private readonly ?int $amount = null,
        private readonly ?string $currency = null,
        private readonly ?string $receiptUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): PaymentSuccessfulMail
    {
        return $this->setMailRecipient($notifiable, new PaymentSuccessfulMail(
            user: $notifiable,
            planName: $this->planName,
            amount: $this->amount,
            currency: $this->currency,
            receiptUrl: $this->receiptUrl,
        ));
    }
}
