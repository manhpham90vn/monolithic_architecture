<?php

namespace Ticketing\Actions;

use Catalog\Contracts\CatalogApi;
use Illuminate\Support\Facades\DB;
use Ticketing\Models\Order;

/**
 * Đưa các đơn chờ thanh toán quá 15 phút sang trạng thái hết hạn và trả
 * lại vé đã giữ cho Catalog (YC-9.1, YC-8.4). Mỗi đơn một transaction:
 * đổi trạng thái và trả vé là nguyên tử (QĐ-3.11); khoá hàng để không đụng
 * độ với webhook xác nhận thanh toán chạy song song.
 */
class ExpireStaleOrders
{
    public function __construct(private readonly CatalogApi $catalog) {}

    /**
     * @return int Số đơn vừa được cho hết hạn.
     */
    public function handle(): int
    {
        $staleOrderIds = Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->pluck('id');

        $expired = 0;

        foreach ($staleOrderIds as $orderId) {
            $expired += DB::transaction(function () use ($orderId): int {
                /** @var Order|null $order */
                $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

                // Kiểm tra lại dưới khoá: đơn có thể vừa được thanh toán.
                if ($order === null || ! $order->isPending() || $order->expires_at === null || $order->expires_at->isFuture()) {
                    return 0;
                }

                $order->update(['status' => Order::STATUS_EXPIRED]);

                $this->catalog->releaseTickets($order->itemQuantities());

                return 1;
            });
        }

        return $expired;
    }
}
