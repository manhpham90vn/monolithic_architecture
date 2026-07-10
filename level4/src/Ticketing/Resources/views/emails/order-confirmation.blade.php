@php
    use SimpleSoftwareIO\QrCode\Facades\QrCode;
@endphp
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif; color:#1a1a2e;">
    <h1 style="font-size:20px;">Cảm ơn bạn đã mua vé!</h1>

    <p>Đơn hàng <strong>#{{ $order->id }}</strong> đã được thanh toán thành công.</p>

    @if ($eventInfo !== null)
        <h2 style="font-size:17px;">{{ $eventInfo->title }}</h2>
        <p>
            🗓️ {{ $eventInfo->startsAt->format('d/m/Y H:i') }}<br>
            📍 {{ $eventInfo->venue }}
        </p>
    @endif

    <h3 style="font-size:15px;">Vé của bạn ({{ $order->tickets->count() }})</h3>

    @foreach ($order->tickets as $ticket)
        <div style="border:1px solid #e5e7eb; border-radius:8px; padding:14px; margin-bottom:12px;">
            <strong>{{ $ticket->ticket_type_name }}</strong><br>
            <div style="margin:8px 0;">{!! QrCode::format('svg')->size(160)->margin(1)->generate($ticket->token) !!}</div>
            <code style="font-size:12px;">{{ $ticket->token }}</code>
        </div>
    @endforeach

    <p style="color:#6b7280; font-size:13px;">Xuất trình mã QR này tại cửa để vào sự kiện.</p>
</body>
</html>
