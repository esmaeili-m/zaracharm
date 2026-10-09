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
Route::livewire('/user/dashboard', 'pages::main.user.dashboard')->name('user.dashboard')->middleware(['auth']);
Route::livewire('/product', 'pages::main.user.dashboard')->name('product.show')->middleware(['auth']);
Route::livewire('/products/{product}', 'pages::main.products.show')->name('products.show');
Route::livewire('/compare', 'pages::main.compare.index')->name('compare.index');
Route::livewire('/cartItem', 'pages::main.cart.cart-item')->name('cartItem')->middleware(['auth']);
Route::livewire('/checkout/{code}', 'pages::main.cart.checkout')->name('checkout')->middleware(['auth']);
// بازگشت از درگاه بانکی (بدون auth: ممکن است نشست کاربر در بازگشت از درگاه از بین رفته باشد؛ اعتبارسنجی با uuid + Authority)
Route::livewire('/payment/{uuid}/callback', 'pages::main.payment.callback')->name('payment.callback');
// درگاه‌هایی که با فرم POST برمی‌گردند (اسنپ‌پی، دیجی‌پی). بدون Session/CSRF: درخواست cross-site است
// و ساختن نشست جدید، کوکی نشست کاربر را بازنویسی (خارج) می‌کرد؛ نتیجه با redirect GET نمایش داده می‌شود.
Route::post('/payment/{uuid}/callback', function (string $uuid) {
    $payment = app(\App\Payments\PaymentManager::class)->method('gateway')
        ->handleCallbackFor($uuid, request()->post());

    return redirect()->route('order.payment.result', [
        'code' => $payment->order?->order_number,
        'payment' => $payment->uuid,
    ], 303);
})->withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
])->name('payment.callback.post');

// ─── Marketplaces (ورودی از مارکت‌پلیس‌ها؛ بدون Session/CSRF) ─────────────
// وب‌هوک: اعتبار با secret یکتای هر مارکت‌پلیس در آدرس
Route::post('/marketplaces/{provider}/webhook/{secret}', function (string $provider, string $secret) {
    $marketplace = \App\Models\Marketplace::where('provider', $provider)->firstOrFail();

    abort_unless($marketplace->webhook_secret && hash_equals($marketplace->webhook_secret, $secret), 404);
    abort_unless($marketplace->isUsable() && $marketplace->supports(\App\Marketplaces\Capability::WEBHOOK), 404);

    try {
        $result = app(\App\Marketplaces\Services\SyncService::class)->handleWebhook($marketplace, request());
    } catch (\Throwable $e) {
        report($e);

        return response()->json(['ok' => false], 500); // مارکت‌پلیس وب‌هوک را دوباره ارسال می‌کند
    }

    return response()->json(['ok' => true, 'message' => $result->message]);
})->withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
])->middleware('throttle:120,1')->name('marketplaces.webhook');

// خوراک محصولات برای مارکت‌پلیس‌هایی که از فروشگاه Pull می‌کنند (ترب: API نسخه ۳)
Route::match(['get', 'post'], '/marketplaces/{provider}/feed', function (string $provider) {
    $marketplace = \App\Models\Marketplace::where('provider', $provider)->firstOrFail();

    abort_unless($marketplace->is_active && $marketplace->supports(\App\Marketplaces\Capability::PRODUCT_FEED), 404);

    $manager = app(\App\Marketplaces\MarketplaceManager::class);

    return $marketplace->driver()->feed($marketplace, $manager->client($marketplace), request());
})->withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
])->middleware('throttle:300,1')->name('marketplaces.feed');
Route::livewire('/order/{code}/payment', 'pages::main.cart.payment')
    ->name('order.payment.result')->middleware(['auth']);
// ─── Static Pages ────────────────────────────────────────
Route::livewire('/search', 'pages::main.search.index')->name('search');

// ─── Resources ───────────────────────────────────────────
Route::livewire('/articles/{slug}', 'pages::main.blogs.show')->name('articles.show');
Route::livewire('/categories/{slug}', 'pages::main.categories.show')->name('categories.show');
Route::livewire('/brands', 'pages::main.brands.index')->name('brands.list');
Route::livewire('/brands/{slug}', 'pages::main.brands.show')->name('brands.show');
Route::livewire('/tags/{slug}', 'pages::main.tags.show')->name('tags.show');


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

    Route::livewire('/invoices', 'pages::dashboard.invoices.index')->name('invoices.index');
    Route::livewire('/{id}/invoices', 'pages::dashboard.invoices.details')->name('invoices.details');
    Route::livewire('/invoices/create', 'pages::dashboard.invoices.form')->name('invoices.create');
    Route::livewire('/invoices/{invoice}/edit', 'pages::dashboard.invoices.form')->name('invoices.edit');
    Route::livewire('/returns', 'pages::dashboard.returns.index')->name('returns.index');
    Route::livewire('/delivery', 'pages::dashboard.delivery.index')->name('delivery.index');
    Route::livewire('/payments', 'pages::dashboard.payments.index')->name('payments.index');
    Route::livewire('/payments/settings', 'pages::dashboard.payments.settings')->name('payments.settings');

    // حسابداری
    Route::livewire('/accounting', 'pages::dashboard.accounting.index')->name('accounting.index');
    Route::livewire('/accounting/entries', 'pages::dashboard.accounting.entries')->name('accounting.entries');
    Route::livewire('/accounting/accounts', 'pages::dashboard.accounting.accounts')->name('accounting.accounts');
    Route::livewire('/accounting/categories', 'pages::dashboard.accounting.categories')->name('accounting.categories');
    Route::livewire('/suppliers', 'pages::dashboard.purchases.suppliers')->name('suppliers.index');
    Route::livewire('/purchases', 'pages::dashboard.purchases.index')->name('purchases.index');
    Route::livewire('/purchases/create', 'pages::dashboard.purchases.form')->name('purchases.create');
    Route::livewire('/purchases/{purchase}/edit', 'pages::dashboard.purchases.form')->name('purchases.edit');
    Route::livewire('/marketplaces', 'pages::dashboard.marketplaces.index')->name('marketplaces.index');
    Route::livewire('/marketplaces/listings', 'pages::dashboard.marketplaces.listings')->name('marketplaces.listings');
    Route::livewire('/marketplaces/orders', 'pages::dashboard.marketplaces.orders')->name('marketplaces.orders');
    Route::livewire('/marketplaces/logs', 'pages::dashboard.marketplaces.logs')->name('marketplaces.logs');
    Route::livewire('/tickets', 'pages::dashboard.tickets.index')->name('tickets.index');
    Route::livewire('/messages', 'pages::dashboard.contact.index')->name('messages.index');
    Route::livewire('/comments', 'pages::dashboard.comments.index')->name('comments.index');
    Route::livewire('/product-questions', 'pages::dashboard.product-questions.index')->name('product-questions.index');
    Route::livewire('/product-questions/trash', 'pages::dashboard.product-questions.trash')->name('product-questions.trash');

    Route::livewire('/social-links', 'pages::dashboard.social-media.index')->name('social-links.index');
    Route::livewire('/invoices', 'pages::dashboard.invoices.index')->name('invoices.index');

    Route::livewire('/products', 'pages::dashboard.products.index')->name('products.index');
    // مدیریت مرحله‌ای محصول (مشخصات فنی ← ویژگی‌های قیمت‌ساز ← قیمت/SKU/بارکد ← موجودی)
    // مسیرهای قدیمی همان صفحه را در مرحله متناظر باز می‌کنند (نام routeها برای لینک‌های موجود حفظ شده است)
    Route::livewire('/products/{product}/manage', 'pages::dashboard.products.manage')->name('products.manage');
    Route::livewire('/products/{product}/settings', 'pages::dashboard.products.manage')->name('products.settings');
    Route::livewire('/products/{product}/prices', 'pages::dashboard.products.manage')->name('products.prices');
    Route::livewire('/products/{product}/specifications', 'pages::dashboard.products.manage')->name('products.specifications');
    Route::livewire('/products/trash', 'pages::dashboard.products.trash')->name('products.trash');

    Route::livewire('/brands', 'pages::dashboard.brands.index')->name('brands.index');
    Route::livewire('/brands/trash', 'pages::dashboard.brands.trash')->name('brands.trash');

    Route::livewire('/stories', 'pages::dashboard.stories.index')->name('stories.index');
    Route::livewire('/stories/trash', 'pages::dashboard.stories.trash')->name('stories.trash');

    Route::livewire('/campaign', 'pages::dashboard.campaign.index')->name('campaign.index');
    Route::livewire('/campaign/trash', 'pages::dashboard.campaign.trash')->name('campaign.trash');
    // ویزارد کمپین (اطلاعات ← محصولات ← تخفیف ← محدودیت‌ها ← بررسی نهایی)
    // مسیرهای قدیمی همان ویزارد را در مرحله متناظر باز می‌کنند (نام routeها حفظ شده است)
    Route::livewire('/campaign/create', 'pages::dashboard.campaign.wizard')->name('campaign.create');
    Route::livewire('/campaign/{campaign}/edit', 'pages::dashboard.campaign.wizard')->name('campaign.edit');
    Route::livewire('/campaign/{campaign}/targets', 'pages::dashboard.campaign.wizard')->name('campaign.targets');
    Route::livewire('/campaign/{campaign}/conditions', 'pages::dashboard.campaign.wizard')->name('campaign.conditions');
    Route::livewire('/campaign/{campaign}/rewards', 'pages::dashboard.campaign.wizard')->name('campaign.rewards');

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


    Route::livewire('/categories', 'pages::dashboard.categories.index')->name('categories.index');
    // ویژگی‌ها و فیلترهای دسته (منبع فیلترهای صفحه دسته‌بندی)
    Route::livewire('/categories/{category}/attributes', 'pages::dashboard.categories.attributes')->name('categories.attributes');
    Route::livewire('/subcategory/{id}', 'pages::dashboard.categories.subcategory')->name('categories.subcategory');
    Route::livewire('/categories/trash', 'pages::dashboard.categories.trash')->name('categories.trash');

    Route::livewire('/tags', 'pages::dashboard.tags.index')->name('tags.index');
    Route::livewire('/seo', 'pages::dashboard.seo.index')->name('seo.index');
    Route::livewire('/faq', 'pages::dashboard.faq.index')->name('faq.index');
    Route::livewire('/menus', 'pages::dashboard.menus.index')->name('menus.index');
    Route::livewire('/menus/{id}', 'pages::dashboard.menus.items')->name('menus.item');


    Route::livewire('/pages', 'pages::dashboard.pages.index')->name('pages.index');
    Route::livewire('/pages/{page}/rows', 'pages::dashboard.pages.rows.index')->name('pages.rows');
    Route::livewire('/pages/{page}/builder', 'pages::dashboard.pages.builder')->name('pages.builder');
    Route::livewire('/pages/{row}/sections', 'pages::dashboard.pages.sections.page-section')->name('pages.rows.sections');

    Route::livewire('/galleries', 'pages::dashboard.galleries.index')->name('galleries.index');
    Route::livewire('/storage', 'pages::dashboard.storage.index')->name('storage.index');
    Route::livewire('/pages/{id}', 'pages::dashboard.pages.items')->name('pages.item');

    Route::livewire('/sections', 'pages::dashboard.pages.sections.index')->name('sections.index');
    Route::livewire('/settings', 'pages::dashboard.settings.index')->name('settings.index');
    Route::livewire('/sections/{id}/page', 'pages::dashboard.pages.sections.page-section')->name('sections.page');
});

