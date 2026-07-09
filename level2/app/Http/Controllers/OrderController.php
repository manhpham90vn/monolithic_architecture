<?php

namespace App\Http\Controllers;

use App\Actions\Order\CancelOrder;
use App\Actions\Order\PlaceOrder;
use App\Actions\Payment\CreateStripeCheckout;
use App\Data\PlaceOrderData;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Controller chỉ làm ba việc: nhận request đã validate, gọi Action, trả
 * response (QĐ-2.4) — bản mức 2, nghiệp vụ nằm trong app/Actions.
 */
class OrderController extends Controller
{
    /**
     * Tạo đơn chờ thanh toán rồi chuyển sang Stripe (§7, §8).
     */
    public function store(
        StoreOrderRequest $request,
        Event $event,
        PlaceOrder $placeOrder,
        CreateStripeCheckout $createStripeCheckout,
    ): RedirectResponse {
        abort_unless($event->isPublished(), 404);

        $order = $placeOrder->handle(PlaceOrderData::fromRequest($request, $event));

        $checkoutUrl = $createStripeCheckout->handle($order);

        return $checkoutUrl === null
            ? redirect()->route('orders.show', $order)
            : redirect()->away($checkoutUrl);
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
     * Người dùng chủ động hủy đơn còn đang chờ thanh toán (YC-8.4).
     */
    public function cancel(Order $order, CancelOrder $cancelOrder): RedirectResponse
    {
        $this->authorize('view', $order);

        $cancelOrder->handle($order);

        return redirect()->route('events.show', $order->event_id)
            ->with('status', 'Đơn đã được hủy.');
    }
}
