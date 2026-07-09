<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cho hết hạn đơn quá 15 phút mỗi phút (YC-9.1). Command thuộc module
// Ticketing, đăng ký trong TicketingServiceProvider (QĐ-3.2).
Schedule::command('orders:expire')->everyMinute();
