<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\View\View;

class EventController extends Controller
{
    /**
     * Danh sách sự kiện đã công bố (YC-4.1, YC-6.2).
     */
    public function index(): View
    {
        $events = Event::query()
            ->published()
            ->withCount('ticketTypes')
            ->orderBy('starts_at')
            ->paginate(12);

        return view('events.index', ['events' => $events]);
    }

    /**
     * Chi tiết sự kiện kèm số vé còn lại từng hạng (YC-6.3, YC-6.4, YC-6.5).
     */
    public function show(Event $event): View
    {
        abort_unless($event->isPublished(), 404);

        $event->load('ticketTypes');

        return view('events.show', ['event' => $event]);
    }
}
