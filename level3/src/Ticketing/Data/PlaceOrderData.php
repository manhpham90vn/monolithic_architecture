<?php

namespace Ticketing\Data;

use Spatie\LaravelData\Data;
use Ticketing\Http\StoreOrderRequest;

/**
 * Dữ liệu tạo đơn truyền từ Controller sang Action (QĐ-2.3) — DTO nội bộ
 * module, không nằm trong Contracts vì không module nào khác dùng.
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

    public static function fromRequest(StoreOrderRequest $request, int $eventId): self
    {
        return new self(
            userId: (int) $request->user()->id,
            eventId: $eventId,
            quantities: $request->selectedQuantities(),
        );
    }
}
