<?php

use App\Providers\AppServiceProvider;
use Catalog\Providers\CatalogServiceProvider;
use CheckIn\Providers\CheckInServiceProvider;
use Payment\Providers\PaymentServiceProvider;
use Ticketing\Providers\TicketingServiceProvider;

/*
 * Mỗi module tự đăng ký route/migration/view và binding Public API của nó
 * qua ServiceProvider riêng (QĐ-3.2).
 */
return [
    AppServiceProvider::class,
    CatalogServiceProvider::class,
    TicketingServiceProvider::class,
    PaymentServiceProvider::class,
    CheckInServiceProvider::class,
];
