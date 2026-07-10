<?php

use Ticketing\Domain\Exception\OrderMustHaveItems;
use Ticketing\Domain\Exception\OrderNotPending;
use Ticketing\Domain\Exception\TooManyTicketsPerOrder;
use Ticketing\Domain\Order\LineItem;
use Ticketing\Domain\Order\Order;
use Ticketing\Domain\Order\OrderId;
use Ticketing\Domain\Order\OrderStatus;
use Ticketing\Domain\Order\TokenGenerator;
use Ticketing\Domain\Shared\Money;

/*
| Test bất biến của aggregate Order — chạy KHÔNG cần boot Laravel, KHÔNG cần
| database (QĐ-4.4, QĐ-8.4). Đây là lời hứa cốt lõi của mức 4: quy tắc nghiệp
| vụ §8/§9 kiểm được tức thì bằng PHP thuần.
*/

/**
 * Dựng danh sách LineItem từ map [unitPrice => quantity]; mỗi phần tử một
 * ticketTypeId tăng dần bắt đầu từ 1.
 *
 * @param  array<int, int>  $lines
 * @return LineItem[]
 */
function lineItems(array $lines): array
{
    $items = [];
    $id = 1;
    foreach ($lines as $price => $quantity) {
        $items[] = new LineItem($id, "Hạng {$id}", $quantity, Money::yen($price));
        $id++;
    }

    return $items;
}

function sequentialTokens(): TokenGenerator
{
    return new class implements TokenGenerator
    {
        private int $n = 0;

        public function generate(): string
        {
            return 'tok-'.(++$this->n);
        }
    };
}

// --- Tạo đơn: bất biến số lượng (YC-8.1, §7.1) ---------------------------

it('rejects an order with no items (§7.1)', function () {
    Order::place(1, 10, [], new DateTimeImmutable);
})->throws(OrderMustHaveItems::class);

it('rejects more than 10 tickets across ticket types (YC-8.1)', function () {
    // 6 + 5 = 11 vé.
    Order::place(1, 10, lineItems([5000 => 6, 3000 => 5]), new DateTimeImmutable);
})->throws(TooManyTicketsPerOrder::class);

it('rejects more than 10 tickets within a single type (YC-8.1)', function () {
    Order::place(1, 10, lineItems([5000 => 11]), new DateTimeImmutable);
})->throws(TooManyTicketsPerOrder::class);

it('allows exactly the 10-ticket maximum (YC-8.1)', function () {
    $order = Order::place(1, 10, lineItems([5000 => 7, 3000 => 3]), new DateTimeImmutable);

    expect($order->status())->toBe(OrderStatus::Pending);
});

// --- Chốt giá (YC-8.5) ---------------------------------------------------

it('locks the price into the total at creation (YC-8.5)', function () {
    $order = Order::place(1, 10, lineItems([5000 => 2, 3000 => 1]), new DateTimeImmutable);

    expect($order->totalAmount()->amount)->toBe(13000);
});

it('supports free tickets with a zero total', function () {
    $order = Order::place(1, 10, lineItems([0 => 3]), new DateTimeImmutable);

    expect($order->totalAmount()->amount)->toBe(0)
        ->and($order->status())->toBe(OrderStatus::Pending);
});

// --- Trạng thái ban đầu & giữ vé (YC-7.2, YC-9.1) -----------------------

it('starts pending and holds tickets for 15 minutes (YC-7.2, YC-9.1)', function () {
    $now = new DateTimeImmutable('2026-07-10 12:00:00');
    $order = Order::place(1, 10, lineItems([5000 => 1]), $now);

    expect($order->status())->toBe(OrderStatus::Pending)
        ->and($order->expiresAt()?->format('Y-m-d H:i:s'))->toBe('2026-07-10 12:15:00')
        ->and($order->paidAt())->toBeNull()
        ->and($order->issuedTickets())->toBe([]);
});

// --- Thanh toán & phát hành vé (YC-7.4, YC-10.1) ------------------------

it('issues one ticket per unit and becomes paid when confirmed (YC-7.4, YC-10.1)', function () {
    $order = Order::place(1, 10, lineItems([5000 => 2, 3000 => 1]), new DateTimeImmutable);

    $order->markPaid(new DateTimeImmutable, sequentialTokens());

    expect($order->status())->toBe(OrderStatus::Paid)
        ->and($order->paidAt())->not->toBeNull()
        ->and($order->expiresAt())->toBeNull()
        ->and($order->issuedTickets())->toHaveCount(3)
        ->and($order->issuedTickets()[0]->token)->toBe('tok-1');
});

it('issues tickets carrying the locked type id and name of each line', function () {
    $order = Order::place(1, 10, lineItems([5000 => 2, 3000 => 1]), new DateTimeImmutable);

    $order->markPaid(new DateTimeImmutable, sequentialTokens());
    $issued = $order->issuedTickets();

    // 2 vé của hạng 1, 1 vé của hạng 2 — đúng thứ tự dòng đơn.
    expect($issued[0]->ticketTypeId)->toBe(1)
        ->and($issued[0]->ticketTypeName)->toBe('Hạng 1')
        ->and($issued[1]->ticketTypeId)->toBe(1)
        ->and($issued[2]->ticketTypeId)->toBe(2)
        ->and($issued[2]->ticketTypeName)->toBe('Hạng 2');
});

// --- Máy trạng thái §9: mọi chuyển trạng thái không hợp lệ đều bị chặn ----

it('cannot be paid twice — the state machine forbids it (YC-9.3)', function () {
    $order = Order::place(1, 10, lineItems([5000 => 1]), new DateTimeImmutable);
    $order->markPaid(new DateTimeImmutable, sequentialTokens());

    $order->markPaid(new DateTimeImmutable, sequentialTokens());
})->throws(OrderNotPending::class);

it('cannot be paid after it was cancelled (§9)', function () {
    $order = Order::place(1, 10, lineItems([5000 => 1]), new DateTimeImmutable);
    $order->cancel();

    $order->markPaid(new DateTimeImmutable, sequentialTokens());
})->throws(OrderNotPending::class);

it('cannot be paid after it expired (§9)', function () {
    $order = Order::place(1, 10, lineItems([5000 => 1]), new DateTimeImmutable);
    $order->expire();

    $order->markPaid(new DateTimeImmutable, sequentialTokens());
})->throws(OrderNotPending::class);

it('cannot cancel an order that is already paid (§9)', function () {
    $order = Order::place(1, 10, lineItems([5000 => 1]), new DateTimeImmutable);
    $order->markPaid(new DateTimeImmutable, sequentialTokens());

    $order->cancel();
})->throws(OrderNotPending::class);

it('cannot expire an order that was cancelled (§9)', function () {
    $order = Order::place(1, 10, lineItems([5000 => 1]), new DateTimeImmutable);
    $order->cancel();

    $order->expire();
})->throws(OrderNotPending::class);

// --- Huỷ & hết hạn (YC-8.4, YC-9.1) -------------------------------------

it('cancels only while pending and drops the hold (YC-8.4)', function () {
    $order = Order::place(1, 10, lineItems([5000 => 1]), new DateTimeImmutable);

    $order->cancel();

    expect($order->status())->toBe(OrderStatus::Cancelled)
        ->and($order->expiresAt())->toBeNull();
});

it('expires only while pending and drops the hold (YC-9.1)', function () {
    $order = Order::place(1, 10, lineItems([5000 => 1]), new DateTimeImmutable);

    $order->expire();

    expect($order->status())->toBe(OrderStatus::Expired)
        ->and($order->expiresAt())->toBeNull();
});

it('reports whether the hold has lapsed by a given time (YC-9.1)', function () {
    $now = new DateTimeImmutable('2026-07-10 12:00:00');
    $order = Order::place(1, 10, lineItems([5000 => 1]), $now);

    expect($order->hasExpiredBy(new DateTimeImmutable('2026-07-10 12:10:00')))->toBeFalse()
        ->and($order->hasExpiredBy(new DateTimeImmutable('2026-07-10 12:20:00')))->toBeTrue();
});

it('is never reported expired once it is no longer pending', function () {
    $order = Order::place(1, 10, lineItems([5000 => 1]), new DateTimeImmutable('2026-07-10 12:00:00'));
    $order->markPaid(new DateTimeImmutable, sequentialTokens());

    // expiresAt đã null sau khi thanh toán → không bao giờ "quá hạn".
    expect($order->hasExpiredBy(new DateTimeImmutable('2030-01-01 00:00:00')))->toBeFalse();
});

// --- Tổng hợp số lượng ---------------------------------------------------

it('aggregates quantities per ticket type', function () {
    $order = Order::place(1, 10, lineItems([5000 => 2, 3000 => 3]), new DateTimeImmutable);

    expect($order->quantities())->toBe([1 => 2, 2 => 3]);
});

// --- Dựng lại từ persistence ---------------------------------------------

it('reconstitutes a stored aggregate without re-running creation guards', function () {
    // 20 vé — vượt trần 10 — nhưng reconstitute KHÔNG kiểm lại (dữ liệu DB
    // coi như đã hợp lệ), khác với place().
    $order = Order::reconstitute(
        id: new OrderId(42),
        userId: 1,
        eventId: 10,
        status: OrderStatus::Paid,
        items: lineItems([5000 => 20]),
        expiresAt: null,
        paidAt: new DateTimeImmutable('2026-07-10 12:00:00'),
    );

    expect($order->id()?->value)->toBe(42)
        ->and($order->status())->toBe(OrderStatus::Paid)
        ->and($order->totalAmount()->amount)->toBe(100000)
        ->and($order->paidAt())->not->toBeNull();
});
