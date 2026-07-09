<?php

namespace Catalog\Actions;

use Catalog\Models\TicketType;
use Illuminate\Support\Facades\DB;

/**
 * Trả lại vé đã giữ khi đơn hết hạn hoặc bị hủy (YC-8.4, YC-9.1).
 */
class ReleaseTickets
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
                ]);
            }
        });
    }
}
