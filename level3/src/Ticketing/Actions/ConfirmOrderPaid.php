<?php

namespace Ticketing\Actions;

use Catalog\Contracts\CatalogApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ticketing\Contracts\OrderPaid;
use Ticketing\Models\Order;
use Ticketing\Models\Ticket;

/**
 * Đánh dấu đơn đã thanh toán khi Payment xác nhận (YC-9.2) và phát hành vé
 * (YC-7.4, YC-10.1). Idempotent: gọi lặp lại KHÔNG phát hành vé hay gửi
 * mail quá một lần (YC-9.3) nhờ khoá hàng + kiểm tra trạng thái.
 *
 * Phát hành vé nằm NGAY TRONG transaction (nguyên tử với đổi trạng thái);
 * việc phụ sau đó (gửi mail, Catalog chốt kho) đi qua event OrderPaid xử lý
 * sau commit (QĐ-3.12).
 */
class ConfirmOrderPaid
{
    public function __construct(private readonly CatalogApi $catalog) {}

    public function handle(int $orderId): void
    {
        DB::transaction(function () use ($orderId): void {
            /** @var Order|null $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            // Đơn không tìm thấy → bỏ qua (webhook lạ). Đã ở trạng thái
            // cuối → no-op, đảm bảo idempotent (YC-9.3).
            if ($order === null || ! $order->isPending()) {
                return;
            }

            // Đơn đã quá hạn giữ vé → cho hết hạn và trả vé, không phát
            // hành nữa để tránh bán quá số (YC-9.1). Thực tế sẽ cần hoàn tiền.
            if ($order->expires_at !== null && $order->expires_at->isPast()) {
                $order->update(['status' => Order::STATUS_EXPIRED]);

                $this->catalog->releaseTickets($order->itemQuantities());

                return;
            }

            $order->update([
                'status' => Order::STATUS_PAID,
                'paid_at' => now(),
                'expires_at' => null,
            ]);

            // Phát hành mỗi vé một mã QR riêng (YC-10.1).
            foreach ($order->items as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    Ticket::create([
                        'order_id' => $order->id,
                        'ticket_type_id' => $item->ticket_type_id,
                        'ticket_type_name' => $item->ticket_type_name,
                        'event_id' => $order->event_id,
                        'user_id' => $order->user_id,
                        'token' => (string) Str::ulid(),
                        'status' => Ticket::STATUS_ISSUED,
                    ]);
                }
            }

            // Công bố cho module khác + việc phụ nội bộ; xử lý sau commit
            // (QĐ-3.5, QĐ-3.12). Payload chỉ scalar, không Model (QĐ-3.4).
            OrderPaid::dispatch($order->id, $order->itemQuantities());
        });
    }
}
