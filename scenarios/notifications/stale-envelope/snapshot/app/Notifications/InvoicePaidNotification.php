<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoicePaidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invoice $invoice, public string $recipientEmail)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function envelope(): \Illuminate\Notifications\Messages\Envelope
    {
        return new \Illuminate\Notifications\Messages\Envelope(
            to: [$this->recipientEmail],
            subject: 'Invoice ' . $this->invoice->number . ' has been paid',
        );
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->greeting('Thank you for your business.')
            ->line('Your invoice ' . $this->invoice->number . ' has been settled.');
    }
}
