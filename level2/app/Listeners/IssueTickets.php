<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Models\Ticket;
use Illuminate\Support\Str;

/**
 * Phát hành mỗi vé một mã QR riêng khi đơn được thanh toán (YC-10.1).
 * Listener sync: chạy ngay trong transaction của ConfirmPayment nên việc
 * phát hành vé là nguyên tử với việc đổi trạng thái đơn.
 */
class IssueTickets
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        foreach ($order->items as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                Ticket::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $item->ticket_type_id,
                    'event_id' => $order->event_id,
                    'user_id' => $order->user_id,
                    'token' => (string) Str::ulid(),
                    'status' => Ticket::STATUS_ISSUED,
                ]);
            }
        }
    }
}
