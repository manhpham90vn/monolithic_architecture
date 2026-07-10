<?php

namespace Ticketing\Infrastructure\Persistence;

use DateTimeImmutable;
use Ticketing\Domain\Order\LineItem;
use Ticketing\Domain\Order\Order;
use Ticketing\Domain\Order\OrderId;
use Ticketing\Domain\Order\OrderStatus;
use Ticketing\Domain\Shared\Money;

/**
 * Mapper giữa aggregate domain Order và model persistence (QĐ-4.3). Đây là
 * chỗ DUY NHẤT biết cả hai thế giới — domain không biết Eloquent, Eloquent
 * không biết bất biến.
 */
final class OrderMapper
{
    public function toDomain(OrderEloquentModel $model): Order
    {
        $items = $model->items
            ->map(fn (OrderItemEloquentModel $item): LineItem => new LineItem(
                ticketTypeId: $item->ticket_type_id,
                ticketTypeName: $item->ticket_type_name,
                quantity: $item->quantity,
                unitPrice: Money::yen($item->unit_price),
            ))
            ->all();

        return Order::reconstitute(
            id: new OrderId($model->id),
            userId: $model->user_id,
            eventId: $model->event_id,
            status: OrderStatus::from($model->status),
            items: $items,
            expiresAt: $this->toImmutable($model->expires_at),
            paidAt: $this->toImmutable($model->paid_at),
        );
    }

    private function toImmutable(mixed $value): ?DateTimeImmutable
    {
        return $value instanceof \DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : null;
    }
}
