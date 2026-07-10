<?php

namespace Ticketing\Application;

use Illuminate\Support\Facades\DB;
use Ticketing\Contracts\CheckInStatus;
use Ticketing\Data\CheckInOutcome;
use Ticketing\Domain\Ticket\TicketRepository;

/**
 * Use-case soát một mã QR (YC-11.1): trả kết quả hợp lệ / đã dùng / không
 * tồn tại. Vé hợp lệ được aggregate Ticket.checkIn() chuyển sang "đã dùng";
 * khoá bi quan trong repository chặn hai lần quét đồng thời (YC-11.2,
 * YC-11.3).
 */
final class CheckInTicketHandler
{
    public function __construct(private readonly TicketRepository $tickets) {}

    public function handle(string $token): CheckInOutcome
    {
        return DB::transaction(function () use ($token): CheckInOutcome {
            $ticket = $this->tickets->findByTokenForUpdate($token);

            if ($ticket === null) {
                return new CheckInOutcome(CheckInStatus::Nonexistent, null);
            }

            if ($ticket->isUsed()) {
                return new CheckInOutcome(CheckInStatus::Used, $ticket);
            }

            $ticket->checkIn(now()->toDateTimeImmutable());
            $this->tickets->save($ticket);

            return new CheckInOutcome(CheckInStatus::Valid, $ticket);
        });
    }
}
