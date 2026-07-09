<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Xác nhận vé — '.$this->order->event->title,
        );
    }

    public function content(): Content
    {
        // Email kèm tên sự kiện, thời gian, địa điểm và các vé với mã QR
        // (YC-12.1).
        return new Content(
            view: 'emails.order-confirmation',
            with: ['order' => $this->order],
        );
    }
}
