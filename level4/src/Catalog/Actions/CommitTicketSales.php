<?php

namespace Catalog\Actions;

use Catalog\Models\TicketType;
use Illuminate\Support\Facades\DB;

/**
 * Chốt bán: chuyển vé từ "đang giữ" sang "đã bán" khi đơn được thanh toán —
 * vé chỉ bị trừ vĩnh viễn khỏi kho ở bước này (YC-8.4).
 */
class CommitTicketSales
{
    /**
     * @param  array<int, int>  $quantities  [ticket_type_id => số lượng]
     */
    public function handle(array $quantities): void
    {
        DB::transaction(function () use ($quantities): void {
            $ticketTypes = TicketType::query()
                ->whereKey(array_keys($quantities))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($quantities as $ticketTypeId => $quantity) {
                /** @var TicketType|null $ticketType */
                $ticketType = $ticketTypes->get($ticketTypeId);

                $ticketType?->update([
                    'reserved_count' => max(0, $ticketType->reserved_count - $quantity),
                    'sold_count' => $ticketType->sold_count + $quantity,
                ]);
            }
        });
    }
}
