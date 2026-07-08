<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cho hết hạn đơn quá 15 phút mỗi phút (YC-9.1).
Schedule::command('orders:expire')->everyMinute();
