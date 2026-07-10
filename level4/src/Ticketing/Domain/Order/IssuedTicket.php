<?php

namespace Ticketing\Domain\Order;

/**
 * Vé điện tử vừa được aggregate Order phát hành khi đơn chuyển sang đã
 * thanh toán (YC-10.1). Value Object: một token QR duy nhất gắn với một
 * hạng vé. `event_id`/`user_id` khi lưu lấy từ chính Order, không lặp ở đây.
 */
final class IssuedTicket
{
    public function __construct(
        public readonly string $token,
        public readonly int $ticketTypeId,
        public readonly string $ticketTypeName,
    ) {}
}
