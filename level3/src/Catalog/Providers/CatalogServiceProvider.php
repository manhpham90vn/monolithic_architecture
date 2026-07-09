<?php

namespace Catalog\Providers;

use Catalog\CatalogApiImpl;
use Catalog\Contracts\CatalogApi;
use Catalog\Listeners\CommitTicketSalesOnOrderPaid;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Ticketing\Contracts\OrderPaid;

/**
 * Module tự đăng ký route/migration/view và binding Public API (QĐ-3.2).
 */
class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CatalogApi::class, CatalogApiImpl::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'catalog');

        Event::listen(OrderPaid::class, CommitTicketSalesOnOrderPaid::class);
    }
}
