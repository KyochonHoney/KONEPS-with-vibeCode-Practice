<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Bootstrap 4 스타일 페이지네이션 사용
        Paginator::defaultView('custom.pagination.bootstrap-4');
        Paginator::defaultSimpleView('custom.pagination.bootstrap-4');
    }
}
