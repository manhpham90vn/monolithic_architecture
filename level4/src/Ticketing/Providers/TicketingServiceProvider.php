<?php

namespace Ticketing\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Payment\Contracts\PaymentSucceeded;
use Ticketing\Console\ExpireStaleOrdersCommand;
use Ticketing\Contracts\OrderPaid;
use Ticketing\Contracts\TicketingApi;
use Ticketing\Domain\Order\OrderRepository;
use Ticketing\Domain\Order\TokenGenerator;
use Ticketing\Domain\Ticket\TicketRepository;
use Ticketing\Infrastructure\Persistence\EloquentOrderRepository;
use Ticketing\Infrastructure\Persistence\EloquentTicketRepository;
use Ticketing\Infrastructure\Persistence\OrderEloquentModel;
use Ticketing\Infrastructure\Persistence\TicketEloquentModel;
use Ticketing\Infrastructure\UlidTokenGenerator;
use Ticketing\Listeners\HandlePaymentSucceeded;
use Ticketing\Listeners\SendOrderConfirmation;
use Ticketing\Policies\OrderPolicy;
use Ticketing\Policies\TicketPolicy;
use Ticketing\TicketingApiImpl;

/**
 * Module tự đăng ký route/migration/view/command/policy và binding Public
 * API (QĐ-3.2). Ở mức 4, đây cũng là chỗ nối ranh giới Domain ↔ Infrastructure:
 * bind interface Repository/TokenGenerator (Domain) với hiện thực Eloquent
 * (Infrastructure) — QĐ-4.2.
 */
class TicketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TicketingApi::class, TicketingApiImpl::class);

        // Ranh giới thật giữa domain thuần và persistence (QĐ-4.2).
        $this->app->bind(OrderRepository::class, EloquentOrderRepository::class);
        $this->app->bind(TicketRepository::class, EloquentTicketRepository::class);
        $this->app->bind(TokenGenerator::class, UlidTokenGenerator::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'ticketing');

        // Policy/route-binding gắn với model persistence (đường đọc/uỷ quyền).
        Gate::policy(OrderEloquentModel::class, OrderPolicy::class);
        Gate::policy(TicketEloquentModel::class, TicketPolicy::class);

        // Phản ứng với event chéo module của Payment (QĐ-3.5) + việc phụ
        // nội bộ sau khi đơn đã thanh toán.
        Event::listen(PaymentSucceeded::class, HandlePaymentSucceeded::class);
        Event::listen(OrderPaid::class, SendOrderConfirmation::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireStaleOrdersCommand::class]);
        }
    }
}
