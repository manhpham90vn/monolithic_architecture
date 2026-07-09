<?php

namespace Ticketing\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Payment\Contracts\PaymentSucceeded;
use Ticketing\Console\ExpireStaleOrdersCommand;
use Ticketing\Contracts\OrderPaid;
use Ticketing\Contracts\TicketingApi;
use Ticketing\Listeners\HandlePaymentSucceeded;
use Ticketing\Listeners\SendOrderConfirmation;
use Ticketing\Models\Order;
use Ticketing\Models\Ticket;
use Ticketing\Policies\OrderPolicy;
use Ticketing\Policies\TicketPolicy;
use Ticketing\TicketingApiImpl;

/**
 * Module tự đăng ký route/migration/view/command/policy và binding Public
 * API (QĐ-3.2).
 */
class TicketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TicketingApi::class, TicketingApiImpl::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'ticketing');

        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);

        // Phản ứng với event chéo module của Payment (QĐ-3.5) + việc phụ
        // nội bộ sau khi đơn đã thanh toán.
        Event::listen(PaymentSucceeded::class, HandlePaymentSucceeded::class);
        Event::listen(OrderPaid::class, SendOrderConfirmation::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireStaleOrdersCommand::class]);
        }
    }
}
