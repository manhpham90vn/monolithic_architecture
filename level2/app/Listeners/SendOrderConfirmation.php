<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Mail\OrderConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Gửi email xác nhận kèm vé và mã QR (YC-7.4, YC-12.1).
 */
class SendOrderConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * OrderPaid được phát bên trong transaction của ConfirmPayment; chỉ gửi
     * mail sau khi transaction đã commit để không gửi cho đơn bị rollback.
     */
    public bool $afterCommit = true;

    public function handle(OrderPaid $event): void
    {
        $order = $event->order->fresh(['user', 'event', 'tickets.ticketType']);

        Mail::to($order->user->email)->send(new OrderConfirmationMail($order));
    }
}
