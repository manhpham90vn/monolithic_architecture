<?php

namespace Ticketing\Infrastructure\Persistence;

use DateTimeImmutable;
use Ticketing\Domain\Ticket\Ticket;
use Ticketing\Domain\Ticket\TicketId;
use Ticketing\Domain\Ticket\TicketRepository;
use Ticketing\Domain\Ticket\TicketStatus;

/**
 * Hiện thực TicketRepository bằng Eloquent (QĐ-4.2).
 */
final class EloquentTicketRepository implements TicketRepository
{
    public function findByTokenForUpdate(string $token): ?Ticket
    {
        /** @var TicketEloquentModel|null $model */
        $model = TicketEloquentModel::query()->where('token', $token)->lockForUpdate()->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function save(Ticket $ticket): void
    {
        TicketEloquentModel::query()->whereKey($ticket->id()->value)->update([
            'status' => $ticket->status()->value,
            'used_at' => $ticket->usedAt(),
        ]);
    }

    private function toDomain(TicketEloquentModel $model): Ticket
    {
        return Ticket::reconstitute(
            id: new TicketId($model->id),
            token: $model->token,
            ticketTypeId: $model->ticket_type_id,
            ticketTypeName: $model->ticket_type_name,
            eventId: $model->event_id,
            userId: $model->user_id,
            status: TicketStatus::from($model->status),
            usedAt: $model->used_at instanceof \DateTimeInterface
                ? DateTimeImmutable::createFromInterface($model->used_at)
                : null,
        );
    }
}
