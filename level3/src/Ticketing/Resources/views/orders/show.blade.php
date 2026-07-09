@extends('layouts.app')

@section('title', 'Đơn hàng #'.$order->id)

@section('content')
    <div class="card">
        <div class="between">
            <h1 style="margin:0;">Đơn hàng #{{ $order->id }}</h1>
            @switch($order->status)
                @case(\Ticketing\Models\Order::STATUS_PAID)
                    <span class="badge ok">Đã thanh toán</span> @break
                @case(\Ticketing\Models\Order::STATUS_PENDING)
                    <span class="badge warn">Chờ thanh toán</span> @break
                @case(\Ticketing\Models\Order::STATUS_EXPIRED)
                    <span class="badge bad">Hết hạn</span> @break
                @case(\Ticketing\Models\Order::STATUS_CANCELLED)
                    <span class="badge bad">Đã hủy</span> @break
            @endswitch
        </div>
        @if ($eventInfo !== null)
            <p class="muted">{{ $eventInfo->title }} — {{ $eventInfo->startsAt->format('d/m/Y H:i') }}</p>
        @endif

        @if (request('checkout') === 'cancel' && $order->isPending())
            <div class="alert bad">Bạn đã rời trang thanh toán. Đơn vẫn được giữ chờ thanh toán trong 15 phút.</div>
        @endif

        <table>
            <thead><tr><th>Hạng vé</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->ticket_type_name }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td class="price">¥{{ number_format($item->unit_price) }}</td>
                        <td class="price">¥{{ number_format($item->subtotal()) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="3" style="text-align:right; font-weight:700;">Tổng cộng</td>
                    <td class="price" style="font-weight:700;">¥{{ number_format($order->total_amount) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if ($order->isPaid())
        <div class="card">
            <h2>Vé của bạn</h2>
            <p class="muted">Đã gửi email xác nhận kèm vé. Xem toàn bộ tại <a href="{{ route('tickets.index') }}">Vé của tôi</a>.</p>
            <div class="grid">
                @foreach ($order->tickets as $ticket)
                    <div class="card" style="text-align:center;">
                        <div class="muted">{{ $ticket->ticket_type_name }}</div>
                        <div class="ticket-qr"><img src="{{ route('tickets.qr', $ticket) }}" alt="QR"></div>
                        <code style="font-size:12px;">{{ $ticket->token }}</code>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif ($order->isPending())
        <div class="card">
            <p class="muted">Đơn đang chờ xác nhận thanh toán từ Stripe. Vé sẽ được phát hành sau khi thanh toán thành công.</p>
            <form class="inline" method="POST" action="{{ route('orders.cancel', $order) }}">
                @csrf
                <button class="btn ghost" type="submit">Hủy đơn</button>
            </form>
        </div>
    @endif
@endsection
