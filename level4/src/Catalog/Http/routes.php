<?php

use Catalog\Http\EventController;
use Illuminate\Support\Facades\Route;

/*
| Catalog — công khai (YC-4.1, YC-6.2). Route load qua ServiceProvider nên
| phải tự khai middleware 'web'.
*/
Route::middleware('web')->group(function (): void {
    Route::get('/', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
});
