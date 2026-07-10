<?php

namespace Ticketing\Application;

use Catalog\Contracts\CatalogApi;
use Illuminate\Support\Facades\DB;
use Ticketing\Domain\Order\OrderId;
use Ticketing\Domain\Order\OrderRepository;
use Ticketing\Domain\Order\OrderStatus;

/**
 * Use-case huỷ đơn còn đang chờ thanh toán và trả lại vé đã giữ cho Catalog
 * (YC-8.4). Aggregate.cancel() ép guard máy trạng thái; trả vé nằm cùng
 * transaction (QĐ-3.11).
 */
final class CancelOrderHandler
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly OrderRepository $orders,
    ) {}

    public function handle(int $orderId): void
    {
        DB::transaction(function () use ($orderId): void {
            $order = $this->orders->findForUpdate(new OrderId($orderId));

            if ($order === null || $order->status() !== OrderStatus::Pending) {
                return;
            }

            $order->cancel();
            $this->orders->save($order);

            $this->catalog->releaseTickets($order->quantities());
        });
    }
}
