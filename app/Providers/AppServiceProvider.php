<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Artisan;
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
		Schema::defaultStringLength(191);

//        Artisan::call('migrate');
        // نقش admin همه دسترسی‌ها را دارد (حتی دسترسی‌های جدیدی که هنوز seed نشده‌اند)
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasRole') && $user->hasRole('admin') ? true : null;
        });

        // همگام‌سازی خودکار موجودی/قیمت/محصول با مارکت‌پلیس‌ها (app/Marketplaces)
        foreach ([\App\Models\InventoryItem::class, \App\Models\ProductVariant::class, \App\Models\Product::class] as $model) {
            $model::observe(\App\Marketplaces\Observers\CatalogObserver::class);
        }

//         Artisan::call('migrate');


    }
}
