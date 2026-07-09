@extends('layouts.app')

@section('title', $event->title)

@section('content')
    <div class="card">
        <div class="between">
            <h1 style="margin:0;">{{ $event->title }}</h1>
            <a class="muted" href="{{ route('events.index') }}">← Tất cả sự kiện</a>
        </div>
        <p class="muted">📍 {{ $event->venue }} &nbsp;•&nbsp; 🗓️ {{ $event->starts_at->format('d/m/Y H:i') }}</p>
        <p>{{ $event->description }}</p>
    </div>

    <div class="card">
        <h2>Chọn vé</h2>

        @auth
            <form method="POST" action="{{ route('orders.store', $event) }}">
                @csrf
                <table>
                    <thead>
                        <tr><th>Hạng vé</th><th>Giá</th><th>Còn lại</th><th style="width:100px;">Số lượng</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($event->ticketTypes as $ticketType)
                            @php($remaining = $ticketType->remaining())
                            <tr>
                                <td>{{ $ticketType->name }}</td>
                                <td class="price">¥{{ number_format($ticketType->price) }}</td>
                                <td>
                                    @if ($remaining <= 0)
                                        <span class="badge bad">Hết vé</span>
                                    @else
                                        <span class="badge ok">{{ $remaining }}</span>
                                    @endif
                                </td>
                                <td>
                                    <input class="qty" type="number" min="0"
                                        max="{{ min($remaining, 10) }}"
                                        name="quantities[{{ $ticketType->id }}]"
                                        value="0" {{ $remaining <= 0 ? 'disabled' : '' }}>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="muted" style="font-size:13px;">Tối đa 10 vé mỗi đơn. Vé được giữ 15 phút để hoàn tất thanh toán.</p>
                <button class="btn" type="submit">Mua vé</button>
            </form>
        @else
            <table>
                <thead>
                    <tr><th>Hạng vé</th><th>Giá</th><th>Còn lại</th></tr>
                </thead>
                <tbody>
                    @foreach ($event->ticketTypes as $ticketType)
                        @php($remaining = $ticketType->remaining())
                        <tr>
                            <td>{{ $ticketType->name }}</td>
                            <td class="price">¥{{ number_format($ticketType->price) }}</td>
                            <td>
                                @if ($remaining <= 0)
                                    <span class="badge bad">Hết vé</span>
                                @else
                                    <span class="badge ok">{{ $remaining }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="muted">Bạn cần đăng nhập để mua vé.</p>
            <a class="btn" href="{{ route('login', ['from' => route('events.show', $event)]) }}">Đăng nhập để mua vé</a>
        @endauth
    </div>
@endsection
