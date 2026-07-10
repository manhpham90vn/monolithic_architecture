<?php

namespace Ticketing\Application;

use Catalog\Contracts\CatalogApi;
use Illuminate\Support\Facades\DB;
use Ticketing\Contracts\OrderPaid;
use Ticketing\Domain\Order\OrderId;
use Ticketing\Domain\Order\OrderRepository;
use Ticketing\Domain\Order\OrderStatus;
use Ticketing\Domain\Order\TokenGenerator;

/**
 * Use-case xác nhận đơn đã thanh toán (YC-9.2) và phát hành vé (YC-7.4,
 * YC-10.1). Việc phát hành vé nằm trong aggregate (markPaid), điều phối
 * transaction/side-effect nằm ở đây.
 *
 * Idempotent (YC-9.3): nạp aggregate dưới khoá, chỉ đơn đang chờ mới xử lý;
 * gọi lặp lại là no-op. Việc phụ (gửi mail, Catalog chốt kho) đẩy qua event
 * OrderPaid xử lý sau commit (QĐ-3.12).
 */
final class ConfirmOrderPaidHandler
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly OrderRepository $orders,
        private readonly TokenGenerator $tokens,
    ) {}

    public function handle(int $orderId): void
    {
        DB::transaction(function () use ($orderId): void {
            $order = $this->orders->findForUpdate(new OrderId($orderId));

            // Không tìm thấy (webhook lạ) hoặc đã ở trạng thái cuối → no-op,
            // bảo đảm idempotent (YC-9.3).
            if ($order === null || $order->status() !== OrderStatus::Pending) {
                return;
            }

            $now = now()->toDateTimeImmutable();

            // Đơn đã quá hạn giữ vé → cho hết hạn và trả vé, không phát hành
            // nữa để tránh bán quá số (YC-9.1). Thực tế sẽ cần hoàn tiền.
            if ($order->hasExpiredBy($now)) {
                $order->expire();
                $this->orders->save($order);
                $this->catalog->releaseTickets($order->quantities());

                return;
            }

            $order->markPaid($now, $this->tokens);
            $this->orders->save($order);

            // Công bố cho module khác + việc phụ nội bộ; xử lý sau commit
            // (QĐ-3.5, QĐ-3.12). Payload chỉ scalar (QĐ-3.4).
            OrderPaid::dispatch($orderId, $order->quantities());
        });
    }
}
