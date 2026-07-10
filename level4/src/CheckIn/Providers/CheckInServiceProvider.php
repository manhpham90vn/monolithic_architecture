<?php

namespace CheckIn\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Module tự đăng ký route/view (QĐ-3.2). CheckIn không sở hữu bảng nào và
 * không có Public API — không module nào phụ thuộc vào nó nên không cần
 * Contracts (QĐ-3.3).
 */
class CheckInServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'checkin');
    }
}
