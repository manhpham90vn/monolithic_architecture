<?php

namespace Ticketing\Http;

use App\Http\Controllers\Controller;
use Catalog\Contracts\CatalogApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Payment\Contracts\CheckoutLineItem;
use Payment\Contracts\CheckoutSessionData;
use Payment\Contracts\PaymentApi;
use Ticketing\Actions\CancelOrder;
use Ticketing\Actions\PlaceOrder;
use Ticketing\Data\PlaceOrderData;
use Ticketing\Models\Order;

/**
 * Controller chỉ làm ba việc: nhận request đã validate, gọi Action, trả
 * response (QĐ-2.4). Sự kiện chỉ đi qua ID + CatalogApi, thanh toán đi qua
 * PaymentApi (QĐ-3.3).
 */
class OrderController extends Controller
{
    /**
     * Tạo đơn chờ thanh toán rồi chuyển sang Stripe (§7, §8).
     */
    public function store(
        StoreOrderRequest $request,
        int $event,
        CatalogApi $catalog,
        PaymentApi $payment,
        PlaceOrder $placeOrder,
    ): RedirectResponse {
        $eventInfo = $catalog->eventInfo($event);

        abort_unless($eventInfo?->isPublished ?? false, 404);

        $order = $placeOrder->handle(PlaceOrderData::fromRequest($request, $eventInfo->id));

        // Chuyển sang Stripe với số tiền bằng đúng tổng đơn (YC-7.3).
        $checkoutUrl = $payment->createCheckoutSession(new CheckoutSessionData(
            orderId: $order->id,
            customerEmail: $request->user()->email,
            amount: $order->total_amount,
            lineItems: $order->items->map(fn ($item): CheckoutLineItem => new CheckoutLineItem(
                name: $eventInfo->title.' — '.$item->ticket_type_name,
                unitPrice: $item->unit_price,
                quantity: $item->quantity,
            ))->all(),
            successUrl: route('orders.show', $order).'?checkout=success',
            cancelUrl: route('orders.show', $order).'?checkout=cancel',
        ));

        return $checkoutUrl === null
            ? redirect()->route('orders.show', $order)
            : redirect()->away($checkoutUrl);
    }

    /**
     * Trang đơn hàng: chờ thanh toán / đã thanh toán (kèm vé). Success/cancel
     * URL của Stripe đều quay về đây (YC-7.4, YC-7.5).
     */
    public function show(Order $order, CatalogApi $catalog): View
    {
        $this->authorize('view', $order);

        $order->load(['items', 'tickets']);

        return view('ticketing::orders.show', [
            'order' => $order,
            'eventInfo' => $catalog->eventInfo($order->event_id),
        ]);
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
