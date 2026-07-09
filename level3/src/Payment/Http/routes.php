<?php

use Illuminate\Support\Facades\Route;
use Payment\Http\StripeWebhookController;

/*
| Webhook Stripe — xác nhận thanh toán server-side (YC-9.2). Middleware
| 'web' để đi qua pipeline chuẩn; CSRF đã except trong bootstrap/app.php.
*/
Route::middleware('web')->group(function (): void {
    Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');
});
