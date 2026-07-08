@extends('layouts.app')

@section('title', 'Sự kiện')

@section('content')
    <h1>Sự kiện sắp diễn ra</h1>

    @if ($events->isEmpty())
        <div class="card muted">Chưa có sự kiện nào được công bố.</div>
    @else
        <div class="grid">
            @foreach ($events as $event)
                <div class="card">
                    <h2><a href="{{ route('events.show', $event) }}">{{ $event->title }}</a></h2>
                    <p class="muted">
                        📍 {{ $event->venue }}<br>
                        🗓️ {{ $event->starts_at->format('d/m/Y H:i') }}
                    </p>
                    <a class="btn secondary" href="{{ route('events.show', $event) }}">Xem chi tiết</a>
                </div>
            @endforeach
        </div>

        <div style="margin-top:16px;">{{ $events->links() }}</div>
    @endif
@endsection
