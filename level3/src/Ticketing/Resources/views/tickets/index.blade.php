@extends('layouts.app')

@section('title', 'Vé của tôi')

@section('content')
    <h1>Vé của tôi</h1>

    @if ($ticketsByEvent->isEmpty())
        <div class="card muted">Bạn chưa có vé nào. <a href="{{ route('events.index') }}">Xem sự kiện</a></div>
    @else
        @foreach ($ticketsByEvent as $eventId => $tickets)
            @php($eventInfo = $eventInfos[$eventId] ?? null)
            <div class="card">
                <h2>{{ $eventInfo?->title ?? 'Sự kiện #'.$eventId }}</h2>
                @if ($eventInfo !== null)
                    <p class="muted">📍 {{ $eventInfo->venue }} • 🗓️ {{ $eventInfo->startsAt->format('d/m/Y H:i') }}</p>
                @endif
                <div class="grid">
                    @foreach ($tickets as $ticket)
                        <div class="card" style="text-align:center;">
                            <div class="muted">{{ $ticket->ticket_type_name }}</div>
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
