<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class OrderController extends Controller
{
    /**
     * Tạo đơn ở trạng thái chờ thanh toán, tạm giữ vé, chốt giá, rồi chuyển
     * sang Stripe (§7, §8). Toàn bộ nghiệp vụ nằm ngay trong controller —
     * đây là bản mức 1 (QĐ-1.1, QĐ-1.3).
     */
    public function store(StoreOrderRequest $request, Event $event): RedirectResponse
    {
        abort_unless($event->isPublished(), 404);

        $quantities = $request->selectedQuantities();

        $order = DB::transaction(function () use ($event, $quantities): Order {
            // Khoá các hàng hạng vé để chống bán quá số khi mua đồng thời
            // (YC-8.2, YC-8.3). Trên MySQL/Postgres đây là pessimistic lock.
            $ticketTypes = TicketType::query()
                ->whereKey(array_keys($quantities))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $total = 0;
            $items = [];

            foreach ($quantities as $ticketTypeId => $quantity) {
                /** @var TicketType $ticketType */
                $ticketType = $ticketTypes->get($ticketTypeId);

                // Kiểm tra tồn kho bên trong transaction đã khoá (YC-8.2).
                if ($quantity > $ticketType->remaining()) {
                    throw ValidationException::withMessages([
                        'quantities' => "Hạng vé \"{$ticketType->name}\" không đủ vé (còn {$ticketType->remaining()}).",
                    ]);
                }

                // Chốt giá tại thời điểm tạo đơn (YC-8.5).
                $total += $ticketType->price * $quantity;
                $items[] = [
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $quantity,
                    'unit_price' => $ticketType->price,
                ];
            }

            $order = Order::create([
                'user_id' => auth()->id(),
                'event_id' => $event->id,
                'status' => Order::STATUS_PENDING,
                'total_amount' => $total,
                // Giữ vé tối đa 15 phút (YC-7.2, YC-9.1).
                'expires_at' => now()->addMinutes(15),
            ]);

            $order->items()->createMany($items);

            return $order;
        });

        return $this->redirectToPayment($order);
    }

    /**
     * Trang đơn hàng: chờ thanh toán / đã thanh toán (kèm vé). Success/cancel
     * URL của Stripe đều quay về đây (YC-7.4, YC-7.5).
     */
    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        $order->load(['event', 'items.ticketType', 'tickets.ticketType']);

        return view('orders.show', ['order' => $order]);
    }

    /**
     * Người dùng chủ động hủy đơn còn đang chờ thanh toán; vé đã giữ được
     * trả lại (YC-8.4). Không đụng vào đơn đã ở trạng thái cuối.
     */
    public function cancel(Order $order): RedirectResponse
    {
        $this->authorize('view', $order);

        if ($order->isPending()) {
            $order->update(['status' => Order::STATUS_CANCELLED, 'expires_at' => null]);
        }

        return redirect()->route('events.show', $order->event_id)
            ->with('status', 'Đơn đã được hủy.');
    }

    /**
     * Chuyển người dùng sang Stripe Checkout với số tiền bằng đúng tổng đơn
     * (YC-7.3). Khi chưa cấu hình STRIPE_SECRET (môi trường test/dev không
     * có khoá), bỏ qua bước Stripe và về thẳng trang đơn.
     */
    protected function redirectToPayment(Order $order): RedirectResponse
    {
        $secret = config('services.stripe.secret');

        if (blank($secret)) {
            return redirect()->route('orders.show', $order);
        }

        $order->loadMissing(['event', 'items.ticketType']);

        Stripe::setApiKey($secret);

        $session = Session::create([
            'mode' => 'payment',
            'customer_email' => $order->user->email,
            'line_items' => $order->items->map(fn ($item): array => [
                'quantity' => $item->quantity,
                'price_data' => [
                    'currency' => 'jpy', // Tiền tệ JPY (YC-2.2).
                    'unit_amount' => $item->unit_price,
                    'product_data' => [
                        'name' => $order->event->title.' — '.$item->ticketType->name,
                    ],
                ],
            ])->all(),
            'success_url' => route('orders.show', $order).'?checkout=success',
            'cancel_url' => route('orders.show', $order).'?checkout=cancel',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $order->update(['stripe_session_id' => $session->id]);

        return redirect($session->url);
    }
}
