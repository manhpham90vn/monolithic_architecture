<?php

namespace App\Actions\Order;

use App\Data\PlaceOrderData;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tạo đơn ở trạng thái chờ thanh toán: khoá tồn kho, chốt giá, tạm giữ vé
 * (§7, §8). Nghiệp vụ đóng gói trong Action với một method handle() —
 * bản mức 2 (QĐ-2.1), tách khỏi tầng HTTP.
 */
class PlaceOrder
{
    public function handle(PlaceOrderData $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            // Khoá các hàng hạng vé để chống bán quá số khi mua đồng thời
            // (YC-8.2, YC-8.3). Trên MySQL/Postgres đây là pessimistic lock.
            $ticketTypes = TicketType::query()
                ->whereKey(array_keys($data->quantities))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $total = 0;
            $items = [];

            foreach ($data->quantities as $ticketTypeId => $quantity) {
                /** @var TicketType $ticketType */
                $ticketType = $ticketTypes->get($ticketTypeId);

                // Kiểm tra tồn kho bên trong transaction đã khoá (YC-8.2).
                if ($quantity > $ticketType->remaining()) {
                    throw ValidationException::withMessages([
                        'quantities' => "Hạng vé \"{$ticketType->name}\" không đủ vé (còn {$ticketType->remaining()}).",
                    ]);
                }

                // Chốt giá tại thời điểm tạo đơn (YC-8.5).
                $total += $ticketType->price * $quantity;
                $items[] = [
                    'ticket_type_id' => $ticketType->id,
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
    }
}
