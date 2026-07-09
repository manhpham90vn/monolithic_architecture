<?php

use CheckIn\Http\CheckInController;
use Illuminate\Support\Facades\Route;

/*
| Soát vé — chỉ nhân viên soát vé (§11, YC-4.2).
*/
Route::middleware(['web', 'auth', 'can:check-in'])->group(function (): void {
    Route::get('/check-in', [CheckInController::class, 'create'])->name('checkin.create');
    Route::post('/check-in', [CheckInController::class, 'store'])->name('checkin.store');
});
