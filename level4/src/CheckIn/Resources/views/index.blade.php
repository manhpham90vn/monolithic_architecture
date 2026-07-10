@extends('layouts.app')

@section('title', 'Soát vé')

@section('content')
    <h1>Soát vé</h1>

    @isset($result)
        <div class="card" style="text-align:center;">
            @switch($result)
                @case(\Ticketing\Contracts\CheckInStatus::Valid)
                    <div class="badge ok" style="font-size:16px; padding:8px 18px;">✓ Hợp lệ — đã cho vào cửa</div>
                    @break
                @case(\Ticketing\Contracts\CheckInStatus::Used)
                    <div class="badge bad" style="font-size:16px; padding:8px 18px;">✗ Đã dùng</div>
                    @break
                @case(\Ticketing\Contracts\CheckInStatus::Nonexistent)
                    <div class="badge bad" style="font-size:16px; padding:8px 18px;">✗ Vé không tồn tại</div>
                    @break
            @endswitch

            @isset($ticket)
                <p class="muted" style="margin-top:12px;">
                    {{ $ticket->eventTitle }} — {{ $ticket->ticketTypeName }}<br>
                    Người mua: {{ $ticket->buyerName }}
                    @if ($result === \Ticketing\Contracts\CheckInStatus::Used && $ticket->usedAt !== null)
                        <br>Đã soát lúc: {{ $ticket->usedAt->format('d/m/Y H:i:s') }}
                    @endif
                </p>
            @else
                <p class="muted" style="margin-top:12px;">Mã: <code>{{ $scannedToken }}</code></p>
            @endisset
        </div>
    @endisset

    <div class="card">
        <p class="muted">Quét hoặc nhập mã trên vé (token trong QR).</p>
        <form method="POST" action="{{ route('checkin.store') }}">
            @csrf
            <div class="field">
                <label for="token">Mã vé</label>
                <input id="token" type="text" name="token" required autofocus autocomplete="off">
            </div>
            <button class="btn" type="submit">Soát vé</button>
        </form>
    </div>
@endsection
