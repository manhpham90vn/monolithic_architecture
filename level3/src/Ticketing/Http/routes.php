<?php

use Illuminate\Support\Facades\Route;
use Ticketing\Http\OrderController;
use Ticketing\Http\TicketController;

/*
| Mua vé & vé của tôi — cần đăng nhập (§7, §10). {event} chỉ là ID: Ticketing
| không route-bind Model của Catalog (QĐ-3.4).
*/
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('/events/{event}/orders', [OrderController::class, 'store'])
        ->whereNumber('event')->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    Route::get('/my-tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}/qr', [TicketController::class, 'qr'])->name('tickets.qr');
});
