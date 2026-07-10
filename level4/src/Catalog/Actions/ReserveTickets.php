<?php

namespace Catalog\Actions;

use Catalog\Contracts\NotEnoughTickets;
use Catalog\Contracts\TicketTypeInfo;
use Catalog\Models\TicketType;
use Illuminate\Support\Facades\DB;

/**
 * Tạm giữ vé: khoá các hàng hạng vé rồi tăng bộ đếm đang giữ (YC-8.2,
 * YC-8.3). Mức 3 vẫn là Action một method handle() như mức 2 (QĐ-2.1) —
 * chỉ khác là nằm trong ranh giới module.
 */
class ReserveTickets
{
    /**
     * @param  array<int, int>  $quantities  [ticket_type_id => số lượng > 0]
     * @return array<int, TicketTypeInfo>
     *
     * @throws NotEnoughTickets
     */
    public function handle(array $quantities): array
    {
        // DB::transaction lồng trong transaction của module gọi trở thành
        // savepoint — không tự commit giữa chừng (QĐ-3.11).
        return DB::transaction(function () use ($quantities): array {
            // Khoá các hàng hạng vé để chống bán quá số khi mua đồng thời
            // (YC-8.2, YC-8.3). Trên MySQL/Postgres đây là pessimistic lock.
            $ticketTypes = TicketType::query()
                ->whereKey(array_keys($quantities))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $infos = [];

            foreach ($quantities as $ticketTypeId => $quantity) {
                /** @var TicketType $ticketType */
                $ticketType = $ticketTypes->get($ticketTypeId);

                if ($quantity > $ticketType->remaining()) {
                    throw NotEnoughTickets::forTicketType($ticketType->name, $ticketType->remaining());
                }

                $ticketType->update(['reserved_count' => $ticketType->reserved_count + $quantity]);

                // Chụp thông tin dưới cùng một khoá — module gọi dùng giá
                // này để chốt giá đơn (YC-8.5).
                $infos[$ticketType->id] = new TicketTypeInfo(
                    id: $ticketType->id,
                    eventId: $ticketType->event_id,
                    name: $ticketType->name,
                    price: $ticketType->price,
                    remaining: $ticketType->remaining(),
                );
            }

            return $infos;
        });
    }
}
