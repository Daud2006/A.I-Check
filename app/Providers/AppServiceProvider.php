<?php

namespace App\Providers;

use App\Models\Plan;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;

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
        View::composer('app', static function ($view): void {
            $view->with(
                'premiumPlans',
                Schema::hasTable('plans')
                    ? Plan::query()
                        ->whereIn('slug', ['premium-monthly', 'premium-yearly'])
                        ->where('is_active', true)
                        ->get()
                        ->keyBy('slug')
                    : collect(),
            );
        });
    }
}
