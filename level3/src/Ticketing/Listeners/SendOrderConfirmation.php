<?php

namespace Ticketing\Listeners;

use Catalog\Contracts\CatalogApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Ticketing\Contracts\OrderPaid;
use Ticketing\Mail\OrderConfirmationMail;
use Ticketing\Models\Order;

/**
 * Gửi email xác nhận kèm vé và mã QR (YC-7.4, YC-12.1). Listener nội bộ
 * module trên event OrderPaid; event đã ShouldDispatchAfterCommit nên chỉ
 * chạy sau khi transaction xác nhận thanh toán commit (QĐ-3.12) — không
 * gửi mail cho đơn bị rollback.
 */
class SendOrderConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public function __construct(private readonly CatalogApi $catalog) {}

    public function handle(OrderPaid $event): void
    {
        /** @var Order|null $order */
        $order = Order::query()->with(['user', 'tickets'])->find($event->orderId);

        if ($order === null) {
            return;
        }

        // Tên sự kiện, thời gian, địa điểm lấy qua Public API của Catalog
        // (YC-12.1, QĐ-3.3).
        $eventInfo = $this->catalog->eventInfo($order->event_id);

        Mail::to($order->user->email)->send(new OrderConfirmationMail($order, $eventInfo));
    }
}
