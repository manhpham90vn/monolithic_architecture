<?php

namespace Ticketing\Infrastructure\Persistence;

use DateTimeImmutable;
use Ticketing\Domain\Order\IssuedTicket;
use Ticketing\Domain\Order\LineItem;
use Ticketing\Domain\Order\Order;
use Ticketing\Domain\Order\OrderId;
use Ticketing\Domain\Order\OrderRepository;

/**
 * Hiện thực OrderRepository bằng Eloquent (QĐ-4.2). Dịch giữa aggregate và
 * các bảng orders/order_items/tickets, và ngược lại qua OrderMapper.
 */
final class EloquentOrderRepository implements OrderRepository
{
    public function __construct(private readonly OrderMapper $mapper) {}

    public function save(Order $order): Order
    {
        $model = $order->id() === null
            ? $this->insert($order)
            : $this->update($order);

        return $this->mapper->toDomain($model->load('items'));
    }

    private function insert(Order $order): OrderEloquentModel
    {
        $model = OrderEloquentModel::query()->create([
            'user_id' => $order->userId(),
            'event_id' => $order->eventId(),
            'status' => $order->status()->value,
            'total_amount' => $order->totalAmount()->amount,
            'expires_at' => $order->expiresAt(),
            'paid_at' => $order->paidAt(),
        ]);

        foreach ($order->items() as $item) {
            /** @var LineItem $item */
            $model->items()->create([
                'ticket_type_id' => $item->ticketTypeId,
                'ticket_type_name' => $item->ticketTypeName,
                'quantity' => $item->quantity,
                'unit_price' => $item->unitPrice->amount,
            ]);
        }

        return $model;
    }

    private function update(Order $order): OrderEloquentModel
    {
        /** @var OrderEloquentModel $model */
        $model = OrderEloquentModel::query()->findOrFail($order->id()->value);

        $model->update([
            'status' => $order->status()->value,
            'expires_at' => $order->expiresAt(),
            'paid_at' => $order->paidAt(),
        ]);

        // Phát hành vé đã sinh trong aggregate (markPaid). firstOrCreate theo
        // token bảo đảm không chèn trùng nếu vì lý do nào đó save chạy lại
        // (nền idempotent, YC-9.3).
        foreach ($order->issuedTickets() as $ticket) {
            /** @var IssuedTicket $ticket */
            TicketEloquentModel::query()->firstOrCreate(
                ['token' => $ticket->token],
                [
                    'order_id' => $order->id()->value,
                    'ticket_type_id' => $ticket->ticketTypeId,
                    'ticket_type_name' => $ticket->ticketTypeName,
                    'event_id' => $order->eventId(),
                    'user_id' => $order->userId(),
                    'status' => TicketEloquentModel::STATUS_ISSUED,
                ],
            );
        }

        return $model;
    }

    public function find(OrderId $id): ?Order
    {
        $model = OrderEloquentModel::query()->with('items')->find($id->value);

        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function findForUpdate(OrderId $id): ?Order
    {
        /** @var OrderEloquentModel|null $model */
        $model = OrderEloquentModel::query()->whereKey($id->value)->lockForUpdate()->first();

        if ($model === null) {
            return null;
        }

        $model->load('items');

        return $this->mapper->toDomain($model);
    }

    public function pendingExpiredIds(DateTimeImmutable $now): array
    {
        return OrderEloquentModel::query()
            ->where('status', OrderEloquentModel::STATUS_PENDING)
            ->where('expires_at', '<=', $now)
            ->pluck('id')
            ->map(fn (int $id): OrderId => new OrderId($id))
            ->all();
    }
}
