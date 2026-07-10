<?php

namespace Ticketing\Domain\Order;

use DateTimeImmutable;
use Ticketing\Domain\Exception\OrderMustHaveItems;
use Ticketing\Domain\Exception\OrderNotPending;
use Ticketing\Domain\Exception\TooManyTicketsPerOrder;
use Ticketing\Domain\Shared\Money;

/**
 * Aggregate root của Ticketing (mức 4, QĐ-4.1). POPO thuần — KHÔNG extends
 * Model, KHÔNG import Laravel: mọi bất biến §8/§9 được bảo vệ ở đây thay vì
 * rải trong Action.
 *
 *  - Tối đa 10 vé mỗi đơn (YC-8.1) — ép trong place().
 *  - Giá chốt tại thời điểm tạo đơn (YC-8.5) — nằm trong LineItem bất biến.
 *  - Máy trạng thái §9 — mọi chuyển trạng thái đi qua method có guard.
 *  - Phát hành vé khi thanh toán (YC-10.1) — markPaid() sinh IssuedTicket.
 *
 * Trạng thái không hợp lệ không thể biểu diễn được: không cách nào tạo một
 * Order 11 vé hay chuyển một đơn đã huỷ sang đã thanh toán.
 */
final class Order
{
    /**
     * Số vé tối đa mỗi đơn (YC-8.1).
     */
    public const int MAX_TICKETS = 10;

    /**
     * Thời gian giữ vé trước khi đơn hết hạn, tính bằng phút (YC-9.1).
     */
    public const int HOLD_MINUTES = 15;

    /** @var LineItem[] */
    private array $items;

    /** @var IssuedTicket[] */
    private array $issuedTickets;

    /**
     * @param  LineItem[]  $items
     * @param  IssuedTicket[]  $issuedTickets
     */
    private function __construct(
        private ?OrderId $id,
        private readonly int $userId,
        private readonly int $eventId,
        private OrderStatus $status,
        array $items,
        private ?DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $paidAt,
        array $issuedTickets = [],
    ) {
        $this->items = array_values($items);
        $this->issuedTickets = array_values($issuedTickets);
    }

    /**
     * Tạo đơn mới ở trạng thái chờ thanh toán, giữ vé 15 phút (YC-7.2).
     * Ép bất biến YC-8.1 (≤ 10 vé) và §7.1 (≥ 1 vé) ngay khi khởi tạo.
     *
     * @param  LineItem[]  $items
     */
    public static function place(int $userId, int $eventId, array $items, DateTimeImmutable $now): self
    {
        $items = array_values($items);

        if ($items === []) {
            throw new OrderMustHaveItems;
        }

        $totalQuantity = array_sum(array_map(
            static fn (LineItem $item): int => $item->quantity,
            $items,
        ));

        if ($totalQuantity > self::MAX_TICKETS) {
            throw TooManyTicketsPerOrder::max($totalQuantity, self::MAX_TICKETS);
        }

        return new self(
            id: null,
            userId: $userId,
            eventId: $eventId,
            status: OrderStatus::Pending,
            items: $items,
            expiresAt: $now->modify('+'.self::HOLD_MINUTES.' minutes'),
            paidAt: null,
        );
    }

    /**
     * Dựng lại aggregate từ dữ liệu đã lưu (dùng bởi Repository). Không chạy
     * lại các guard của place(): dữ liệu đã ở DB coi như đã hợp lệ.
     *
     * @param  LineItem[]  $items
     */
    public static function reconstitute(
        OrderId $id,
        int $userId,
        int $eventId,
        OrderStatus $status,
        array $items,
        ?DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $paidAt,
    ): self {
        return new self($id, $userId, $eventId, $status, $items, $expiresAt, $paidAt);
    }

    /**
     * Tổng tiền đơn, tính từ giá đã chốt trong các LineItem (YC-8.5).
     */
    public function totalAmount(): Money
    {
        $total = Money::zero();

        foreach ($this->items as $item) {
            $total = $total->add($item->subtotal());
        }

        return $total;
    }

    /**
     * Xác nhận đã thanh toán và phát hành vé (YC-7.4, YC-10.1). Mỗi vé một
     * token QR riêng. Guard máy trạng thái §9: chỉ đơn đang chờ mới chuyển
     * được — gọi trên đơn đã ở trạng thái cuối sẽ ném OrderNotPending, là
     * nền cho tính idempotent của tầng Application (YC-9.3).
     */
    public function markPaid(DateTimeImmutable $now, TokenGenerator $tokens): void
    {
        $this->ensurePending();

        $this->status = OrderStatus::Paid;
        $this->paidAt = $now;
        $this->expiresAt = null;

        foreach ($this->items as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                $this->issuedTickets[] = new IssuedTicket(
                    token: $tokens->generate(),
                    ticketTypeId: $item->ticketTypeId,
                    ticketTypeName: $item->ticketTypeName,
                );
            }
        }
    }

    /**
     * Cho đơn hết hạn khi quá thời gian giữ vé (YC-9.1). Việc trả vé cho
     * Catalog do tầng Application điều phối sau khi lưu.
     */
    public function expire(): void
    {
        $this->ensurePending();

        $this->status = OrderStatus::Expired;
        $this->expiresAt = null;
    }

    /**
     * Người dùng chủ động huỷ đơn còn đang chờ thanh toán (YC-8.4).
     */
    public function cancel(): void
    {
        $this->ensurePending();

        $this->status = OrderStatus::Cancelled;
        $this->expiresAt = null;
    }

    /**
     * Đơn đã quá hạn giữ vé tính đến $now hay chưa (YC-9.1).
     */
    public function hasExpiredBy(DateTimeImmutable $now): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= $now;
    }

    private function ensurePending(): void
    {
        if ($this->status !== OrderStatus::Pending) {
            throw OrderNotPending::is($this->status);
        }
    }

    public function id(): ?OrderId
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function eventId(): int
    {
        return $this->eventId;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    /** @return LineItem[] */
    public function items(): array
    {
        return $this->items;
    }

    /** @return IssuedTicket[] */
    public function issuedTickets(): array
    {
        return $this->issuedTickets;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function paidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    /**
     * Số vé đã giữ theo hạng — dùng khi trả/chốt vé với Catalog.
     *
     * @return array<int, int> [ticket_type_id => số lượng]
     */
    public function quantities(): array
    {
        $quantities = [];

        foreach ($this->items as $item) {
            $quantities[$item->ticketTypeId] = ($quantities[$item->ticketTypeId] ?? 0) + $item->quantity;
        }

        return $quantities;
    }
}
