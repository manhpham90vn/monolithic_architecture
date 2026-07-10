<?php

namespace Ticketing\Mail;

use Catalog\Contracts\EventInfo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Ticketing\Infrastructure\Persistence\OrderEloquentModel;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OrderEloquentModel $order,
        public ?EventInfo $eventInfo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Xác nhận vé — '.($this->eventInfo?->title ?? 'Đơn hàng #'.$this->order->id),
        );
    }

    public function content(): Content
    {
        // Email kèm tên sự kiện, thời gian, địa điểm và các vé với mã QR
        // (YC-12.1).
        return new Content(
            view: 'ticketing::emails.order-confirmation',
            with: ['order' => $this->order, 'eventInfo' => $this->eventInfo],
        );
    }
}
