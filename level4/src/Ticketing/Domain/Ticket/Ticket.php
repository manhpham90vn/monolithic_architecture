<?php

namespace Ticketing\Domain\Ticket;

use DateTimeImmutable;
use Ticketing\Domain\Exception\TicketAlreadyUsed;

/**
 * Aggregate soát vé (§11). Ranh giới giao dịch của việc soát là MỘT vé, nên
 * Ticket là aggregate riêng chứ không nạp cả Order để đổi trạng thái một vé.
 * POPO thuần (QĐ-4.1): bất biến YC-11.3 (không soát quá một lần) được ép
 * trong checkIn().
 */
final class Ticket
{
    private function __construct(
        private readonly TicketId $id,
        private readonly string $token,
        private readonly int $ticketTypeId,
        private readonly string $ticketTypeName,
        private readonly int $eventId,
        private readonly int $userId,
        private TicketStatus $status,
        private ?DateTimeImmutable $usedAt,
    ) {}

    public static function reconstitute(
        TicketId $id,
        string $token,
        int $ticketTypeId,
        string $ticketTypeName,
        int $eventId,
        int $userId,
        TicketStatus $status,
        ?DateTimeImmutable $usedAt,
    ): self {
        return new self($id, $token, $ticketTypeId, $ticketTypeName, $eventId, $userId, $status, $usedAt);
    }

    /**
     * Soát vé vào cửa (YC-11.2). Guard bất biến YC-11.3: vé đã dùng thì ném
     * TicketAlreadyUsed thay vì đánh dấu lần hai.
     */
    public function checkIn(DateTimeImmutable $now): void
    {
        if ($this->status === TicketStatus::Used) {
            throw TicketAlreadyUsed::withToken($this->token);
        }

        $this->status = TicketStatus::Used;
        $this->usedAt = $now;
    }

    public function isUsed(): bool
    {
        return $this->status === TicketStatus::Used;
    }

    public function id(): TicketId
    {
        return $this->id;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function ticketTypeId(): int
    {
        return $this->ticketTypeId;
    }

    public function ticketTypeName(): string
    {
        return $this->ticketTypeName;
    }

    public function eventId(): int
    {
        return $this->eventId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function status(): TicketStatus
    {
        return $this->status;
    }

    public function usedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }
}
