<?php

namespace App\Data;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Event;
use Spatie\LaravelData\Data;

/**
 * Dữ liệu tạo đơn truyền từ Controller sang Action (QĐ-2.3) — không truyền
 * `array $data` mù mờ giữa các tầng (QĐ-2.4). Gọi tường minh
 * `PlaceOrderData::fromRequest($request, $event)` — quantities cần bước lọc
 * riêng (selectedQuantities) nên không để `Data::from()` tự map payload.
 */
class PlaceOrderData extends Data
{
    /**
     * @param  array<int, int>  $quantities  [ticket_type_id => số lượng > 0]
     */
    public function __construct(
        public int $userId,
        public int $eventId,
        public array $quantities,
    ) {}

    public static function fromRequest(StoreOrderRequest $request, Event $event): self
    {
        return new self(
            userId: (int) $request->user()->id,
            eventId: (int) $event->id,
            quantities: $request->selectedQuantities(),
        );
    }
}
