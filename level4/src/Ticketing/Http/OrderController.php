<?php

namespace Ticketing\Http;

use App\Http\Controllers\Controller;
use Catalog\Contracts\CatalogApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Payment\Contracts\CheckoutLineItem;
use Payment\Contracts\CheckoutSessionData;
use Payment\Contracts\PaymentApi;
use Ticketing\Application\CancelOrderHandler;
use Ticketing\Application\PlaceOrderHandler;
use Ticketing\Data\PlaceOrderData;
use Ticketing\Domain\Order\LineItem;
use Ticketing\Infrastructure\Persistence\OrderEloquentModel;

/**
 * Controller nhận request đã validate, gọi use-case của tầng Application,
 * trả response (QĐ-2.4). Đường ghi (store/cancel) đi qua aggregate; đường
 * đọc (show) nạp thẳng model persistence. Sự kiện chỉ đi qua ID + CatalogApi,
 * thanh toán qua PaymentApi (QĐ-3.3).
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
        PlaceOrderHandler $placeOrder,
    ): RedirectResponse {
        $eventInfo = $catalog->eventInfo($event);

        abort_unless($eventInfo?->isPublished ?? false, 404);

        // Handler trả về aggregate domain đã lưu (đã có OrderId).
        $order = $placeOrder->handle(PlaceOrderData::fromRequest($request, $eventInfo->id));
        $orderId = $order->id()->value;

        // Chuyển sang Stripe với số tiền bằng đúng tổng đơn (YC-7.3).
        $checkoutUrl = $payment->createCheckoutSession(new CheckoutSessionData(
            orderId: $orderId,
            customerEmail: $request->user()->email,
            amount: $order->totalAmount()->amount,
            lineItems: array_map(fn (LineItem $item): CheckoutLineItem => new CheckoutLineItem(
                name: $eventInfo->title.' — '.$item->ticketTypeName,
                unitPrice: $item->unitPrice->amount,
                quantity: $item->quantity,
            ), $order->items()),
            successUrl: route('orders.show', $orderId).'?checkout=success',
            cancelUrl: route('orders.show', $orderId).'?checkout=cancel',
        ));

        return $checkoutUrl === null
            ? redirect()->route('orders.show', $orderId)
            : redirect()->away($checkoutUrl);
    }

    /**
     * Trang đơn hàng: chờ thanh toán / đã thanh toán (kèm vé). Success/cancel
     * URL của Stripe đều quay về đây (YC-7.4, YC-7.5).
     */
    public function show(OrderEloquentModel $order, CatalogApi $catalog): View
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
    public function cancel(OrderEloquentModel $order, CancelOrderHandler $cancelOrder): RedirectResponse
    {
        $this->authorize('view', $order);

        $cancelOrder->handle($order->id);

        return redirect()->route('events.show', $order->event_id)
            ->with('status', 'Đơn đã được hủy.');
    }
}
