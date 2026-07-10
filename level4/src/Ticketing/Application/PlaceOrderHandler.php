<?php

namespace Ticketing\Application;

use Catalog\Contracts\CatalogApi;
use Catalog\Contracts\NotEnoughTickets;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ticketing\Data\PlaceOrderData;
use Ticketing\Domain\Order\LineItem;
use Ticketing\Domain\Order\Order;
use Ticketing\Domain\Order\OrderRepository;
use Ticketing\Domain\Shared\Money;

/**
 * Use-case tạo đơn chờ thanh toán (§7, §8). Tầng Application điều phối
 * framework (transaction, gọi Catalog) rồi giao quyết định nghiệp vụ cho
 * aggregate: Order::place() tự ép ≤ 10 vé và chốt giá (YC-8.1, YC-8.5).
 *
 * Giữ vé qua CatalogApi ngay trong transaction của mình — đặc quyền
 * monolith một database (QĐ-3.11): giữ vé và lưu đơn commit/rollback cùng.
 */
final class PlaceOrderHandler
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly OrderRepository $orders,
    ) {}

    public function handle(PlaceOrderData $data): Order
    {
        try {
            return DB::transaction(function () use ($data): Order {
                // Catalog khoá tồn kho, tăng số giữ và trả thông tin hạng vé
                // chụp dưới khoá đó (YC-8.2, YC-8.3).
                $reserved = $this->catalog->reserveTickets($data->quantities);

                $items = [];

                foreach ($data->quantities as $ticketTypeId => $quantity) {
                    $ticketType = $reserved[$ticketTypeId];

                    // Giá và tên chốt vào LineItem bất biến (YC-8.5).
                    $items[] = new LineItem(
                        ticketTypeId: $ticketType->id,
                        ticketTypeName: $ticketType->name,
                        quantity: $quantity,
                        unitPrice: Money::yen($ticketType->price),
                    );
                }

                // Tầng Application đọc đồng hồ của framework rồi trao cho
                // domain thuần (domain không gọi now() của Laravel).
                $order = Order::place($data->userId, $data->eventId, $items, now()->toDateTimeImmutable());

                return $this->orders->save($order);
            });
        } catch (NotEnoughTickets $exception) {
            // Dịch lỗi nghiệp vụ của Catalog sang lỗi validation cho form mua vé.
            throw ValidationException::withMessages(['quantities' => $exception->getMessage()]);
        }
    }
}
