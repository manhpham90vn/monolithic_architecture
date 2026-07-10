<?php

namespace Ticketing\Application;

use Catalog\Contracts\CatalogApi;
use Illuminate\Support\Facades\DB;
use Ticketing\Domain\Order\OrderRepository;
use Ticketing\Domain\Order\OrderStatus;

/**
 * Use-case cho các đơn chờ thanh toán quá 15 phút hết hạn và trả lại vé đã
 * giữ (YC-9.1, YC-8.4). Mỗi đơn một transaction: nạp lại dưới khoá và kiểm
 * tra lại trước khi đổi trạng thái để không đụng độ webhook xác nhận thanh
 * toán chạy song song.
 */
final class ExpireStaleOrdersHandler
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly OrderRepository $orders,
    ) {}

    /**
     * @return int Số đơn vừa được cho hết hạn.
     */
    public function handle(): int
    {
        $staleIds = $this->orders->pendingExpiredIds(now()->toDateTimeImmutable());

        $expired = 0;

        foreach ($staleIds as $id) {
            $expired += DB::transaction(function () use ($id): int {
                $order = $this->orders->findForUpdate($id);

                // Kiểm tra lại dưới khoá: đơn có thể vừa được thanh toán.
                if ($order === null
                    || $order->status() !== OrderStatus::Pending
                    || ! $order->hasExpiredBy(now()->toDateTimeImmutable())
                ) {
                    return 0;
                }

                $order->expire();
                $this->orders->save($order);

                $this->catalog->releaseTickets($order->quantities());

                return 1;
            });
        }

        return $expired;
    }
}
