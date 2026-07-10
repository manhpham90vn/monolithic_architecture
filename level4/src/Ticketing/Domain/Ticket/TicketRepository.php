<?php

namespace Ticketing\Domain\Ticket;

/**
 * Ranh giới persistence cho aggregate Ticket (QĐ-4.2). Interface ở Domain/,
 * hiện thực Eloquent ở Infrastructure/.
 */
interface TicketRepository
{
    /**
     * Nạp vé theo token kèm khoá bi quan để chặn hai lần quét đồng thời
     * (YC-11.3). BẮT BUỘC gọi trong một transaction.
     */
    public function findByTokenForUpdate(string $token): ?Ticket;

    public function save(Ticket $ticket): void;
}
