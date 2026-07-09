<?php

namespace Ticketing\Actions;

use Catalog\Contracts\CatalogApi;
use Illuminate\Support\Facades\DB;
use Ticketing\Models\Order;

/**
 * Hủy đơn còn đang chờ thanh toán và trả lại vé đã giữ cho Catalog
 * (YC-8.4). Đổi trạng thái và trả vé nằm trong cùng transaction (QĐ-3.11).
 */
class CancelOrder
{
    public function __construct(private readonly CatalogApi $catalog) {}

    public function handle(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $order->isPending()) {
                return;
            }

            $order->update(['status' => Order::STATUS_CANCELLED, 'expires_at' => null]);

            $this->catalog->releaseTickets($order->itemQuantities());
        });
    }
}
