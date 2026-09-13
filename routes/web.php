<?php

use Illuminate\Support\Facades\Route;

// ─── Auth ───────────────────────────────────────────────
Route::post('/logout', function () {
    \Illuminate\Support\Facades\Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout');

Route::livewire('/login', 'pages::auth.login')->name('login');
Route::livewire('/user/dashboard', 'pages::main.user.dashboard')->name('user.dashboard');
Route::livewire('/product', 'pages::main.user.dashboard')->name('product.show');
Route::livewire('/products/{product}', 'pages::main.products.show')->name('products.show');
Route::livewire('/cartItem', 'pages::main.cart.cart-item')->name('cartItem');
Route::livewire('/checkout/{code}', 'pages::main.cart.checkout')->name('checkout');
Route::livewire('/order/{code}/payment', 'pages::main.cart.payment')
    ->name('order.payment.result');
// ─── Static Pages ────────────────────────────────────────
Route::livewire('/cart', 'pages::main.cart.index')->name('cart.index');
Route::livewire('/search', 'pages::main.search.index')->name('search');

// ─── Resources ───────────────────────────────────────────
Route::livewire('/articles/{slug}', 'pages::main.blogs.show')->name('articles.show');
Route::livewire('/services/{slug}', 'pages::main.services.show')->name('services.show');
Route::livewire('/categories/{slug}', 'pages::main.categories.show')->name('categories.show');
Route::livewire('/tags/{slug}', 'pages::main.tags.show')->name('tags.show');

Route::livewire('/courses/{course}/learn', 'pages::main.courses.learn')->name('courses.learn');
Route::livewire('/courses/{slug}', 'pages::main.courses.show')->name('courses.show');

// ─── Catch-all (باید آخر باشه) ───────────────────────────
Route::livewire('/{slug}', 'pages::main.pages.show')->name('page.show');
Route::livewire('/', 'pages::main.pages.show')->name('home');

Route::prefix('dashboard') ->middleware([
    'auth',
    'permission:settings.dashboard'
])->group(function (){

    Route::livewire('/panel', 'pages::dashboard.index')->name('dashboard');
    Route::livewire('/users', 'pages::dashboard.users.index')->name('users.index');
    Route::livewire('/users/trash', 'pages::dashboard.users.trash')->name('users.trash');

    Route::livewire('/roles', 'pages::dashboard.roles.index')->name('roles.index');
    Route::livewire('/roles/trash', 'pages::dashboard.roles.trash')->name('roles.trash');

    Route::livewire('/posts', 'pages::dashboard.posts.index')->name('posts.index');
    Route::livewire('/posts/trash', 'pages::dashboard.posts.trash')->name('posts.trash');

    Route::livewire('/articles', 'pages::dashboard.articles.index')->name('articles.index');
    Route::livewire('/articles/trash', 'pages::dashboard.articles.trash')->name('articles.trash');

    Route::livewire('/hospitals', 'pages::dashboard.hospitals.index')->name('hospitals.index');
    Route::livewire('/services', 'pages::dashboard.services.index')->name('services.index');
    Route::livewire('/invoices', 'pages::dashboard.invoices.index')->name('invoices.index');
    Route::livewire('/{id}/invoices', 'pages::dashboard.invoices.details')->name('invoices.details');
    Route::livewire('/tickets', 'pages::dashboard.tickets.index')->name('tickets.index');
    Route::livewire('/messages', 'pages::dashboard.contact.index')->name('messages.index');
    Route::livewire('/comments', 'pages::dashboard.comments.index')->name('comments.index');
    Route::livewire('/services/trash', 'pages::dashboard.services.trash')->name('services.trash');

    Route::livewire('/courses', 'pages::dashboard.courses.index')->name('orders.index');
    Route::livewire('/invoices', 'pages::dashboard.invoices.index')->name('invoices.index');
    Route::livewire('/orders/trash', 'pages::dashboard.courses.trash')->name('orders.show');
    Route::livewire('/tickets/trash', 'pages::dashboard.courses.trash')->name('tickets.show');

    Route::livewire('/products', 'pages::dashboard.products.index')->name('products.index');
    Route::livewire('/products/{product}/settings', 'pages::dashboard.products.settings')->name('products.settings');
    Route::livewire('/products/{product}/prices', 'pages::dashboard.products.prices')->name('products.prices');
    Route::livewire('/products/{product}/specifications', 'pages::dashboard.products.specifications')->name('products.specifications');
    Route::livewire('/products/trash', 'pages::dashboard.products.trash')->name('products.trash');

    Route::livewire('/brands', 'pages::dashboard.brands.index')->name('brands.index');
    Route::livewire('/brands/trash', 'pages::dashboard.brands.trash')->name('brands.trash');

    Route::livewire('/stories', 'pages::dashboard.stories.index')->name('stories.index');
    Route::livewire('/stories/trash', 'pages::dashboard.stories.trash')->name('stories.trash');

    Route::livewire('/campaign', 'pages::dashboard.campaign.index')->name('campaign.index');
    Route::livewire('/campaign/trash', 'pages::dashboard.campaign.trash')->name('campaign.trash');
    Route::livewire('/campaign/{campaign}/targets', 'pages::dashboard.campaign.targets')->name('campaign.targets');
    Route::livewire('/campaign/{campaign}/conditions', 'pages::dashboard.campaign.conditions')->name('campaign.conditions');
    Route::livewire('/campaign/{campaign}/rewards', 'pages::dashboard.campaign.rewards')->name('campaign.rewards');

    Route::livewire('/sliders', 'pages::dashboard.sliders.index')->name('sliders.index');
    Route::livewire('/sliders/trash', 'pages::dashboard.sliders.trash')->name('sliders.trash');
    Route::livewire('/sliders/{slider}/item', 'pages::dashboard.sliders.item')->name('sliders.item');

    Route::livewire('/inventories', 'pages::dashboard.inventories.index')->name('inventories.index');
    Route::livewire('/inventories/trash', 'pages::dashboard.inventories.trash')->name('inventories.trash');

    Route::livewire('/discounts', 'pages::dashboard.discounts.index')->name('discounts.index');
    Route::livewire('/discounts/trash', 'pages::dashboard.discounts.trash')->name('discounts.trash');

    Route::livewire('/coupons', 'pages::dashboard.coupons.index')->name('coupons.index');
    Route::livewire('/coupons/trash', 'pages::dashboard.coupons.trash')->name('coupons.trash');

    Route::livewire('/discounts/{discount}/target', 'pages::dashboard.discounts.target')->name('discounts.target');

    Route::livewire('/specifications', 'pages::dashboard.specifications.index')->name('specifications.index');
    Route::livewire('/specifications/trash', 'pages::dashboard.specifications.trash')->name('specifications.trash');

    Route::livewire('/options', 'pages::dashboard.options.index')->name('options.index');
    Route::livewire('/options/trash', 'pages::dashboard.options.trash')->name('options.trash');
    Route::livewire('/options/{option}/value', 'pages::dashboard.options.value')->name('options.value');

    Route::livewire('/sections/{section}/lessons', 'pages::dashboard.courses.lessons.index')->name('sections.lessons');
    Route::livewire('/courses/{course}/sections', 'pages::dashboard.courses.sections.index')->name('courses.sections');
    Route::livewire('/courses/{course}/lessons/trash', 'pages::dashboard.courses.lessons.trash')->name('courses.lessons.trash');

    Route::livewire('/categories', 'pages::dashboard.categories.index')->name('categories.index');
    Route::livewire('/subcategory/{id}', 'pages::dashboard.categories.subcategory')->name('categories.subcategory');
    Route::livewire('/categories/trash', 'pages::dashboard.categories.trash')->name('categories.trash');

    Route::livewire('/tags', 'pages::dashboard.tags.index')->name('tags.index');
    Route::livewire('/seo', 'pages::dashboard.seo.index')->name('seo.index');
    Route::livewire('/faq', 'pages::dashboard.faq.index')->name('faq.index');
    Route::livewire('/menus', 'pages::dashboard.menus.index')->name('menus.index');
    Route::livewire('/menus/{id}', 'pages::dashboard.menus.items')->name('menus.item');


    Route::livewire('/pages', 'pages::dashboard.pages.index')->name('pages.index');
    Route::livewire('/pages/{page}/rows', 'pages::dashboard.pages.rows.index')->name('pages.rows');
    Route::livewire('/pages/{row}/sections', 'pages::dashboard.pages.sections.page-section')->name('pages.rows.sections');

    Route::livewire('/galleries', 'pages::dashboard.galleries.index')->name('galleries.index');
    Route::livewire('/storage', 'pages::dashboard.storage.index')->name('storage.index');
    Route::livewire('/pages/{id}', 'pages::dashboard.pages.items')->name('pages.item');

    Route::livewire('/sections', 'pages::dashboard.pages.sections.index')->name('sections.index');
    Route::livewire('/settings', 'pages::dashboard.settings.index')->name('settings.index');
    Route::livewire('/sections/{id}/page', 'pages::dashboard.pages.sections.page-section')->name('sections.page');
});

