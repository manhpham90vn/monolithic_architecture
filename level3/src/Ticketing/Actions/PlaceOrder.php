<?php

namespace Ticketing\Actions;

use Catalog\Contracts\CatalogApi;
use Catalog\Contracts\NotEnoughTickets;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ticketing\Data\PlaceOrderData;
use Ticketing\Models\Order;

/**
 * Tạo đơn ở trạng thái chờ thanh toán (§7, §8). Giữ vé qua CatalogApi ngay
 * trong transaction của mình — đặc quyền monolith một database (QĐ-3.11):
 * giữ vé và tạo đơn commit hoặc rollback cùng nhau.
 */
class PlaceOrder
{
    public function __construct(private readonly CatalogApi $catalog) {}

    public function handle(PlaceOrderData $data): Order
    {
        try {
            return DB::transaction(function () use ($data): Order {
                // Catalog khoá tồn kho, tăng số giữ và trả thông tin hạng vé
                // chụp dưới khoá đó (YC-8.2, YC-8.3).
                $reserved = $this->catalog->reserveTickets($data->quantities);

                $total = 0;
                $items = [];

                foreach ($data->quantities as $ticketTypeId => $quantity) {
                    $ticketType = $reserved[$ticketTypeId];

                    // Chốt giá và tên hạng vé tại thời điểm tạo đơn (YC-8.5).
                    $total += $ticketType->price * $quantity;
                    $items[] = [
                        'ticket_type_id' => $ticketType->id,
                        'ticket_type_name' => $ticketType->name,
                        'quantity' => $quantity,
                        'unit_price' => $ticketType->price,
                    ];
                }

                $order = Order::create([
                    'user_id' => $data->userId,
                    'event_id' => $data->eventId,
                    'status' => Order::STATUS_PENDING,
                    'total_amount' => $total,
                    // Giữ vé tối đa 15 phút (YC-7.2, YC-9.1).
                    'expires_at' => now()->addMinutes(15),
                ]);

                $order->items()->createMany($items);

                return $order;
            });
        } catch (NotEnoughTickets $exception) {
            // Dịch lỗi nghiệp vụ của Catalog sang lỗi validation cho form
            // mua vé, giữ nguyên hành vi mức 2.
            throw ValidationException::withMessages(['quantities' => $exception->getMessage()]);
        }
    }
}
