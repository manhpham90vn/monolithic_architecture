<?php

namespace Payment\Providers;

use Illuminate\Support\ServiceProvider;
use Payment\Contracts\PaymentApi;
use Payment\PaymentApiImpl;

/**
 * Module tự đăng ký route/migration và binding Public API (QĐ-3.2).
 */
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentApi::class, PaymentApiImpl::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
