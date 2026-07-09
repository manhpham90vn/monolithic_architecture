<?php

namespace Ticketing\Contracts;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event chéo module: đơn vừa được xác nhận thanh toán (YC-7.4). Nằm trong
 * Contracts\ vì công bố cho module khác; payload chỉ gồm scalar — KHÔNG
 * nhét Eloquent Model vào event chéo module (QĐ-3.5, QĐ-3.4).
 *
 * ShouldDispatchAfterCommit: listener chỉ chạy sau khi transaction phát
 * event đã commit, tránh module khác phản ứng trên dữ liệu có thể rollback
 * (QĐ-3.12).
 */
class OrderPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  array<int, int>  $quantities  [ticket_type_id => số lượng] của đơn
     */
    public function __construct(
        public int $orderId,
        public array $quantities,
    ) {}
}
