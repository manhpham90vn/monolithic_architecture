<?php

use App\Models\User;
use Catalog\Models\Event;
use Catalog\Models\TicketType;
use Illuminate\Support\Facades\Mail;
use Ticketing\Application\ConfirmOrderPaidHandler;
use Ticketing\Application\PlaceOrderHandler;
use Ticketing\Data\PlaceOrderData;
use Ticketing\Infrastructure\Persistence\OrderEloquentModel as Order;
use Ticketing\Infrastructure\Persistence\TicketEloquentModel as Ticket;
use Ticketing\Mail\OrderConfirmationMail;

/*
| Xác nhận thanh toán (YC-9.2) → phát hành vé (YC-7.4, YC-10.1) → chốt kho
| (YC-8.4), và tính idempotent (YC-9.3). Đây là các bất biến ở mức tích hợp:
| chúng đi qua aggregate + Repository + khoá DB + event chéo module, nên
| kiểm bằng feature test (có DB) chứ không phải unit test domain thuần.
*/

beforeEach(function () {
    $this->event = Event::factory()->create();
    $this->ticketType = TicketType::factory()->for($this->event)->create(['price' => 5000, 'quantity' => 10]);
    $this->user = User::factory()->create();
});

/**
 * Đặt một đơn thật (Catalog giữ vé) và trả về id đơn.
 */
function placePendingOrder(int $userId, int $eventId, array $quantities): int
{
    return app(PlaceOrderHandler::class)
        ->handle(new PlaceOrderData($userId, $eventId, $quantities))
        ->id()->value;
}

it('marks the order paid and issues one ticket per unit (YC-7.4, YC-10.1)', function () {
    $orderId = placePendingOrder($this->user->id, $this->event->id, [$this->ticketType->id => 2]);

    app(ConfirmOrderPaidHandler::class)->handle($orderId);

    expect(Order::find($orderId)->status)->toBe(Order::STATUS_PAID)
        ->and(Order::find($orderId)->paid_at)->not->toBeNull()
        ->and(Ticket::where('order_id', $orderId)->count())->toBe(2);
});

it('permanently commits the reserved tickets as sold on payment (YC-8.4)', function () {
    $orderId = placePendingOrder($this->user->id, $this->event->id, [$this->ticketType->id => 2]);

    expect($this->ticketType->fresh()->reserved_count)->toBe(2);

    app(ConfirmOrderPaidHandler::class)->handle($orderId);

    // Vé chuyển từ "đang giữ" sang "đã bán": remaining vẫn 8, reserved về 0.
    expect($this->ticketType->fresh()->remaining())->toBe(8)
        ->and($this->ticketType->fresh()->reserved_count)->toBe(0);
});

it('is idempotent — confirming twice issues tickets only once (YC-9.3)', function () {
    $orderId = placePendingOrder($this->user->id, $this->event->id, [$this->ticketType->id => 3]);

    app(ConfirmOrderPaidHandler::class)->handle($orderId);
    app(ConfirmOrderPaidHandler::class)->handle($orderId);

    expect(Ticket::where('order_id', $orderId)->count())->toBe(3)
        ->and(Order::find($orderId)->status)->toBe(Order::STATUS_PAID)
        ->and($this->ticketType->fresh()->remaining())->toBe(7);
});

it('sends a confirmation email with the tickets (YC-12.1)', function () {
    Mail::fake();
    $orderId = placePendingOrder($this->user->id, $this->event->id, [$this->ticketType->id => 1]);

    app(ConfirmOrderPaidHandler::class)->handle($orderId);

    Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail): bool {
        return $mail->hasTo($this->user->email);
    });
});

it('expires instead of paying when the hold already lapsed (YC-9.1)', function () {
    $orderId = placePendingOrder($this->user->id, $this->event->id, [$this->ticketType->id => 4]);

    // Quá 15 phút mới có xác nhận thanh toán → cho hết hạn, trả vé, KHÔNG
    // phát hành để tránh bán quá số.
    $this->travel(16)->minutes();
    app(ConfirmOrderPaidHandler::class)->handle($orderId);

    expect(Order::find($orderId)->status)->toBe(Order::STATUS_EXPIRED)
        ->and(Ticket::where('order_id', $orderId)->count())->toBe(0)
        ->and($this->ticketType->fresh()->remaining())->toBe(10)
        ->and($this->ticketType->fresh()->reserved_count)->toBe(0);
});

it('ignores confirmation for an unknown order id', function () {
    app(ConfirmOrderPaidHandler::class)->handle(999_999);

    expect(Ticket::count())->toBe(0);
});
