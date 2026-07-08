@extends('layouts.app')

@section('title', 'Vé của tôi')

@section('content')
    <h1>Vé của tôi</h1>

    @if ($ticketsByEvent->isEmpty())
        <div class="card muted">Bạn chưa có vé nào. <a href="{{ route('events.index') }}">Xem sự kiện</a></div>
    @else
        @foreach ($ticketsByEvent as $tickets)
            @php($event = $tickets->first()->event)
            <div class="card">
                <h2>{{ $event->title }}</h2>
                <p class="muted">📍 {{ $event->venue }} • 🗓️ {{ $event->starts_at->format('d/m/Y H:i') }}</p>
                <div class="grid">
                    @foreach ($tickets as $ticket)
                        <div class="card" style="text-align:center;">
                            <div class="muted">{{ $ticket->ticketType->name }}</div>
                            <div class="ticket-qr"><img src="{{ route('tickets.qr', $ticket) }}" alt="QR"></div>
                            <code style="font-size:12px;">{{ $ticket->token }}</code><br>
                            @if ($ticket->isUsed())
                                <span class="badge bad">Đã dùng</span>
                            @else
                                <span class="badge ok">Hợp lệ</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
@endsection
