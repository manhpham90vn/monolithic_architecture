<?php

namespace Catalog;

use Catalog\Actions\CommitTicketSales;
use Catalog\Actions\ReleaseTickets;
use Catalog\Actions\ReserveTickets;
use Catalog\Contracts\CatalogApi;
use Catalog\Contracts\EventInfo;
use Catalog\Contracts\TicketTypeInfo;
use Catalog\Models\Event;
use Catalog\Models\TicketType;

/**
 * Implementation của Catalog\Contracts\CatalogApi — internal, bind trong
 * CatalogServiceProvider (QĐ-3.2, QĐ-3.3). Nghiệp vụ ghi dữ liệu ủy quyền
 * cho Action (QĐ-2.1).
 */
class CatalogApiImpl implements CatalogApi
{
    public function __construct(
        private readonly ReserveTickets $reserveTickets,
        private readonly ReleaseTickets $releaseTickets,
        private readonly CommitTicketSales $commitTicketSales,
    ) {}

    public function eventInfo(int $eventId): ?EventInfo
    {
        $event = Event::find($eventId);

        return $event === null ? null : $this->toEventInfo($event);
    }

    public function eventInfos(array $eventIds): array
    {
        return Event::query()
            ->whereKey($eventIds)
            ->get()
            ->mapWithKeys(fn (Event $event): array => [$event->id => $this->toEventInfo($event)])
            ->all();
    }

    public function ticketTypeInfos(array $ticketTypeIds): array
    {
        return TicketType::query()
            ->whereKey($ticketTypeIds)
            ->get()
            ->mapWithKeys(fn (TicketType $ticketType): array => [
                $ticketType->id => new TicketTypeInfo(
                    id: $ticketType->id,
                    eventId: $ticketType->event_id,
                    name: $ticketType->name,
                    price: $ticketType->price,
                    remaining: $ticketType->remaining(),
                ),
            ])
            ->all();
    }

    public function reserveTickets(array $quantities): array
    {
        return $this->reserveTickets->handle($quantities);
    }

    public function releaseTickets(array $quantities): void
    {
        $this->releaseTickets->handle($quantities);
    }

    public function commitTicketSales(array $quantities): void
    {
        $this->commitTicketSales->handle($quantities);
    }

    private function toEventInfo(Event $event): EventInfo
    {
        return new EventInfo(
            id: $event->id,
            title: $event->title,
            venue: $event->venue,
            startsAt: $event->starts_at->toImmutable(),
            isPublished: $event->isPublished(),
        );
    }
}
