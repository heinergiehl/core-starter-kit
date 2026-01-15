<?php

namespace App\Notifications;

use App\Domain\Billing\Models\CheckoutIntent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class CheckoutClaimNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly CheckoutIntent $intent)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'checkout.claim',
            now()->addHours(24),
            ['intent' => $this->intent->id]
        );

        return (new MailMessage)
            ->subject('Confirm your purchase')
            ->greeting('Thanks for your purchase')
            ->line('Please confirm your purchase to activate your workspace.')
            ->action('Claim purchase', $url)
            ->line('This link expires in 24 hours. If you did not make this purchase, you can ignore this email.');
    }
}
