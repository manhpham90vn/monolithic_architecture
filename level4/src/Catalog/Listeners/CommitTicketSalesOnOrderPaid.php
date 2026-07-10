<?php

namespace Catalog\Listeners;

use Catalog\Actions\CommitTicketSales;
use Ticketing\Contracts\OrderPaid;

/**
 * Phản ứng với event chéo module OrderPaid của Ticketing (QĐ-3.5): đơn đã
 * thanh toán thì trừ vĩnh viễn số vé đã giữ (YC-8.4). Event implement
 * ShouldDispatchAfterCommit nên listener chỉ chạy sau khi transaction của
 * Ticketing đã commit (QĐ-3.12). An toàn khi chạy lặp: số giữ chỉ bị trừ
 * theo payload, không âm.
 */
class CommitTicketSalesOnOrderPaid
{
    public function __construct(private readonly CommitTicketSales $commitTicketSales) {}

    public function handle(OrderPaid $event): void
    {
        $this->commitTicketSales->handle($event->quantities);
    }
}
