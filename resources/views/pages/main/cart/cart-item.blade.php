
<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\Coupon;
use Illuminate\Support\Str;
use App\Models\Order;
use App\Models\Invoice;
new class extends Component
{
    private const MAX_QTY_PER_ITEM = 20;
    public string $couponCode = '';

    public ?Coupon $appliedCoupon = null;

    public int $discountAmount = 0;
    public function mount(): void
    {
        if (! Auth::check()) {
            abort(403);
        }

        if ($this->items->isEmpty()) {
            // در صورت نیاز می‌توانی به route سبد خرید تغییرش بدهی
            // $this->redirectRoute('cart');
        }
    }
    public function applyCoupon(): void
    {
        $this->resetErrorBag('couponCode');

        $code = trim($this->couponCode);

        if ($code === '') {
            $this->addError('couponCode', 'لطفاً کد تخفیف را وارد کنید.');
            return;
        }

        $coupon = Coupon::query()
            ->with('discount')
            ->where('code', $code)
            ->where('status', true)
            ->first();

        if (! $coupon) {
            $this->addError('couponCode', 'کد تخفیف وارد شده معتبر نیست.');
            return;
        }

        $discount = $coupon->discount;

        if (! $discount || ! $discount->status) {
            $this->addError('couponCode', 'این کد تخفیف فعال نیست.');
            return;
        }

        $now = now();

        if ($discount->starts_at && $now->lt($discount->starts_at)) {
            $this->addError('couponCode', 'زمان استفاده از این کد تخفیف هنوز شروع نشده است.');
            return;
        }

        if ($discount->ends_at && $now->gt($discount->ends_at)) {
            $this->addError('couponCode', 'زمان استفاده از این کد تخفیف به پایان رسیده است.');
            return;
        }

        if (
            $coupon->usage_limit !== null &&
            $coupon->used_count >= $coupon->usage_limit
        ) {
            $this->addError('couponCode', 'ظرفیت استفاده از این کد تخفیف تکمیل شده است.');
            return;
        }

        $userUsage = DB::table('coupon_usages')
            ->where('coupon_id', $coupon->id)
            ->where('user_id', Auth::id())
            ->count();

        if ($userUsage >= $coupon->usage_per_user) {
            $this->addError(
                'couponCode',
                'شما قبلاً به حداکثر میزان مجاز از این کد تخفیف استفاده کرده‌اید.'
            );

            return;
        }

        $subtotal = $this->subtotal;

        if (
            $discount->minimum_purchase !== null &&
            $subtotal < $discount->minimum_purchase
        ) {
            $this->addError(
                'couponCode',
                'حداقل مبلغ خرید برای این کد تخفیف ' .
                number_format($discount->minimum_purchase) .
                ' تومان است.'
            );

            return;
        }

        $this->appliedCoupon = $coupon;

        $this->discountAmount = $this->calculateDiscount(
            $subtotal,
            $discount
        );
    }
    private function calculateDiscount(int $subtotal, $discount): int
    {
        if ($discount->type === 1) {

            $amount = (int) floor(
                $subtotal * $discount->value / 100
            );

            if ($discount->maximum_discount !== null) {
                $amount = min(
                    $amount,
                    (int) $discount->maximum_discount
                );
            }

            return min($amount, $subtotal);
        }

        if ($discount->type === 2) {
            return min(
                (int) $discount->value,
                $subtotal
            );
        }

        return 0;
    }
    public function removeCoupon(): void
    {
        $this->couponCode = '';
        $this->appliedCoupon = null;
        $this->discountAmount = 0;

        $this->resetErrorBag('couponCode');
    }
    /**
     * شناسه سبد فعال کاربر
     */
    private function currentCartId(): ?int
    {
        return DB::table('carts')
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->value('id');
    }

    /**
     * آیتم‌های سبد خرید
     */
    #[Computed]
    public function items(): Collection
    {
        $cartId = $this->currentCartId();

        if (! $cartId) {
            return collect();
        }

        $rows = DB::table('cart_items')
            ->where('cart_id', $cartId)
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $productIds = $rows
            ->pluck('product_id')
            ->filter()
            ->unique();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->with('media')
            ->get()
            ->keyBy('id');

        return $rows->map(function ($row) use ($products) {

            $product = $products->get($row->product_id);

            $attributes = json_decode(
                $row->attributes ?? '{}',
                true
            ) ?: [];

            $variantId = $attributes['variant_id'] ?? null;

            $variant = $variantId
                ? ProductVariant::query()->find($variantId)
                : null;

            $stock = null;

            if ($variantId) {
                $stock = (int) DB::table('inventory_items')
                    ->where('product_variant_id', $variantId)
                    ->where('status', 1)
                    ->whereNull('deleted_at')
                    ->selectRaw(
                        'SUM(quantity - reserved_quantity) as stock'
                    )
                    ->value('stock');
            }

            $image = $product?->media
                ->firstWhere('collection', 'featured_image');

            $quantity = (int) $row->quantity;
            $unitPrice = (int) $row->price;

            return (object) [
                'cart_item_id' => (int) $row->id,

                'product' => $product,

                'variant' => $variant,

                'title' => $product?->title ?? 'محصول حذف‌شده',

                'image' => $image,

                'unit_price' => $unitPrice,

                'quantity' => $quantity,

                'line_total' => $unitPrice * $quantity,

                'stock' => $stock,

                'attributes' => $attributes,
            ];
        });
    }

    /**
     * تعداد کل کالاها
     */
    #[Computed]
    public function totalCount(): int
    {
        return (int) $this->items->sum('quantity');
    }
    #[Computed]
    public function finalPrice(): int
    {
        return max(
            0,
            $this->subtotal - $this->discountAmount
        );
    }
    /**
     * مجموع قیمت محصولات
     */
    #[Computed]
    public function subtotal(): int
    {
        return (int) $this->items->sum('line_total');
    }

    /**
     * تخفیف
     *
     * فعلاً صفر است.
     * بعداً سیستم Voucher/Coupon را به این قسمت وصل می‌کنیم.
     */
    #[Computed]
    public function discount(): int
    {
        return 0;
    }

    /**
     * مالیات
     *
     * فعلاً صفر است.
     * اگر مالیات محصولات را فعال کردی اینجا محاسبه می‌شود.
     */
    #[Computed]
    public function tax(): int
    {
        return 0;
    }

    /**
     * مبلغ قابل پرداخت
     */
    #[Computed]
    public function payable(): int
    {
        return max(
            0,
            $this->subtotal
            - $this->discount
            + $this->tax
        );
    }

    /**
     * افزایش تعداد
     */
    public function increase(int $cartItemId): void
    {
        $cartId = $this->currentCartId();

        if (! $cartId) {
            return;
        }

        $item = DB::table('cart_items')
            ->where('id', $cartItemId)
            ->where('cart_id', $cartId)
            ->first();

        if (! $item) {
            return;
        }

        if ($item->quantity >= self::MAX_QTY_PER_ITEM) {
            $this->dispatch(
                'alert',
                type: 'error',
                message: 'حداکثر تعداد قابل سفارش برای این کالا ۲۰ عدد است.'
            );

            return;
        }

        $attributes = json_decode(
            $item->attributes ?? '{}',
            true
        ) ?: [];

        $variantId = $attributes['variant_id'] ?? null;

        if ($variantId) {

            $stock = (int) DB::table('inventory_items')
                ->where('product_variant_id', $variantId)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->selectRaw(
                    'SUM(quantity - reserved_quantity) as stock'
                )
                ->value('stock');

            if ($item->quantity >= $stock) {
                $this->dispatch(
                    'alert',
                    type: 'error',
                    message: 'به سقف موجودی این کالا رسیده‌اید.'
                );

                return;
            }
        }

        DB::table('cart_items')
            ->where('id', $cartItemId)
            ->where('cart_id', $cartId)
            ->increment('quantity');
    }

    /**
     * کاهش تعداد
     */
    public function decrease(int $cartItemId): void
    {
        $cartId = $this->currentCartId();

        if (! $cartId) {
            return;
        }

        $item = DB::table('cart_items')
            ->where('id', $cartItemId)
            ->where('cart_id', $cartId)
            ->first();

        if (! $item) {
            return;
        }

        if ($item->quantity <= 1) {
            DB::table('cart_items')
                ->where('id', $cartItemId)
                ->where('cart_id', $cartId)
                ->delete();

            return;
        }

        DB::table('cart_items')
            ->where('id', $cartItemId)
            ->where('cart_id', $cartId)
            ->decrement('quantity');
    }

    /**
     * حذف محصول
     */
    public function remove(int $cartItemId): void
    {
        $cartId = $this->currentCartId();

        if (! $cartId) {
            return;
        }

        DB::table('cart_items')
            ->where('id', $cartItemId)
            ->where('cart_id', $cartId)
            ->delete();
    }

    /**
     * رفتن به مرحله بعد
     *
     * فعلاً فقط placeholder است.
     * در مرحله بعد این متد را به ایجاد Order + Invoice
     * و سپس زرین‌پال متصل می‌کنیم.
     */
    public function proceedToPayment()
    {
        if (!Auth::check()) {
            return $this->redirectRoute('login');
        }
        $cartId = $this->currentCartId();

        if (! $cartId) {
            $this->dispatch('alert', type: 'error', message: 'سبد خرید شما خالی است.');
            return;
        }

        try {
            $order = DB::transaction(function () use ($cartId) {

                // ---------------------------------------------------------------
                // ۱. قفل‌کردن سبد خودش (جلوی دو کلیک هم‌زمان روی همین دکمه را می‌گیرد)
                // ---------------------------------------------------------------
                $cart = DB::table('carts')->where('id', $cartId)->lockForUpdate()->first();

                if (! $cart || $cart->status !== 'active') {
                    throw new \RuntimeException('این سبد قبلاً به سفارش تبدیل شده یا معتبر نیست.');
                }

                $cartItems = DB::table('cart_items')->where('cart_id', $cartId)->lockForUpdate()->get();

                if ($cartItems->isEmpty()) {
                    throw new \RuntimeException('سبد خرید شما خالی است.');
                }

                // ---------------------------------------------------------------
                // ۲. بررسی محصول/واریانت + محاسبه مبلغ + رزرو موجودی
                // ---------------------------------------------------------------
                $subtotal = 0;
                $preparedItems = [];

                foreach ($cartItems as $cartItem) {
                    $attributes = json_decode($cartItem->attributes ?? '{}', true) ?: [];
                    $variantId  = $cartItem->variant_id ?? ($attributes['variant_id'] ?? null);

                    $variant = null;

                    if ($variantId) {
                        $variant = ProductVariant::query()->with('product')->lockForUpdate()->find($variantId);

                        if (! $variant) {
                            throw new \RuntimeException('یکی از محصولات سبد خرید دیگر موجود نیست.');
                        }

                        // موجودی قابل‌رزرو را روی ردیف(های) انبار قفل می‌کنیم
                        $inventoryRow = DB::table('inventory_items')
                            ->where('product_variant_id', $variantId)
                            ->where('status', 1)
                            ->whereNull('deleted_at')
                            ->lockForUpdate()
                            ->orderByDesc(DB::raw('quantity - reserved_quantity'))
                            ->first();

                        $available = $inventoryRow ? ((int) $inventoryRow->quantity - (int) $inventoryRow->reserved_quantity) : 0;

                        if (! $inventoryRow || $available < $cartItem->quantity) {
                            $productTitle = $variant->product?->title ?? 'محصول';
                            throw new \RuntimeException("موجودی محصول «{$productTitle}» برای تعداد درخواستی کافی نیست.");
                        }

                        // رزرو موجودی: افزایش reserved_quantity به‌جای کم‌کردن quantity،
                        // چون هنوز پرداخت قطعی نشده و ممکن است سفارش لغو/منقضی شود.
                        DB::table('inventory_items')
                            ->where('id', $inventoryRow->id)
                            ->increment('reserved_quantity', $cartItem->quantity);

                    } else {
                        $product = Product::query()->find($cartItem->product_id);

                        if (! $product) {
                            throw new \RuntimeException('یکی از محصولات سبد خرید دیگر موجود نیست.');
                        }
                    }

                    $unitPrice = (int) $cartItem->price;
                    $quantity  = (int) $cartItem->quantity;
                    $lineTotal = $unitPrice * $quantity;
                    $subtotal += $lineTotal;

                    $preparedItems[] = [
                        'cart_item'   => $cartItem,
                        'variant'     => $variant,
                        'attributes'  => $attributes,
                        'unit_price'  => $unitPrice,
                        'quantity'    => $quantity,
                        'total_price' => $lineTotal,
                    ];
                }

                // ---------------------------------------------------------------
                // ۳. تخفیف — همین‌جا نهایی می‌شود؛ صفحه‌ی بعد (Checkout) دیگر
                //    اینپوت کد تخفیف نخواهد داشت.
                // ---------------------------------------------------------------
                $discountAmount = 0;
                $couponId = null;

                if (! empty($this->appliedCoupon)) {
                    $coupon = DB::table('coupons')
                        ->where('id', $this->appliedCoupon->id)
                        ->where('status', 1)
                        ->lockForUpdate()
                        ->first();

                    if ($coupon) {
                        $discount = DB::table('discounts')
                            ->where('id', $coupon->discount_id)
                            ->where('status', 1)
                            ->whereNull('deleted_at')
                            ->first();

                        if ($discount) {
                            $now = now();
                            $validStart   = !$discount->starts_at || $now->greaterThanOrEqualTo($discount->starts_at);
                            $validEnd     = !$discount->ends_at || $now->lessThanOrEqualTo($discount->ends_at);
                            $validMinimum = !$discount->minimum_purchase || $subtotal >= $discount->minimum_purchase;

                            if ($validStart && $validEnd && $validMinimum) {
                                $discountAmount = (int) $discount->type === 1
                                    ? (int) floor($subtotal * ((int) $discount->value / 100))
                                    : (int) $discount->value;

                                if ($discount->maximum_discount !== null && $discountAmount > $discount->maximum_discount) {
                                    $discountAmount = (int) $discount->maximum_discount;
                                }

                                $discountAmount = min($discountAmount, $subtotal);
                                $couponId = $coupon->id;
                            }
                        }
                    }
                }

                $taxAmount = 0;
                $shippingAmount = 0; // در صفحه‌ی Checkout بر اساس روز انتخابی به‌روزرسانی می‌شود
                $totalAmount = max(0, $subtotal - $discountAmount + $taxAmount + $shippingAmount);

                // ---------------------------------------------------------------
                // ۴. ساخت Order (با انقضای ۳۰ دقیقه‌ای)
                // ---------------------------------------------------------------

                $order = Order::create([
                    'user_id'         => Auth::id(),
                    'order_number'    => 'ORD-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(5)),
                    'address_id'      => null,
                    'coupon_id'       => $couponId,
                    'status'          => 'pending',
                    'payment_status'  => 'unpaid',
                    'subtotal'        => $subtotal,
                    'discount_amount' => $discountAmount,
                    'tax_amount'      => $taxAmount,
                    'shipping_amount' => $shippingAmount,
                    'total_amount'    => $totalAmount,
                    'expires_at'      => now()->addMinutes(1800),
                ]);

                // ---------------------------------------------------------------
                // ۵. ساخت Order Items / Invoice / Invoice Items
                // ---------------------------------------------------------------
                foreach ($preparedItems as $item) {
                    $variant = $item['variant'];
                    $productName = $variant?->product?->title
                        ?? Product::query()->find($item['cart_item']->product_id)?->title
                        ?? 'محصول';
                    $variantName = $variant ? ($variant->name ?? $variant->title ?? null) : null;

                    $order->items()->create([
                        'variant_id'   => $variant?->id,
                        'product_name' => $productName,
                        'variant_name' => $variantName,
                        'quantity'     => $item['quantity'],
                        'price'        => $item['unit_price'],
                        'total_price'  => $item['total_price'],
                        'attributes'   => !empty($item['attributes']) ? json_encode($item['attributes'], JSON_UNESCAPED_UNICODE) : null,
                    ]);
                }

                $invoice = Invoice::create([
                    'order_id'        => $order->id,
                    'user_id'         => Auth::id(),
                    'invoice_number'  => 'INV-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(5)),
                    'status'          => 'unpaid',
                    'subtotal'        => $subtotal,
                    'discount_amount' => $discountAmount,
                    'tax_amount'      => $taxAmount,
                    'shipping_amount' => $shippingAmount,
                    'total_amount'    => $totalAmount,
                ]);

                foreach ($order->items as $item) {
                    $invoice->items()->create([
                        'variant_id'   => $item->variant_id,
                        'product_name' => $item->product_name,
                        'variant_name' => $item->variant_name,
                        'quantity'     => $item->quantity,
                        'unit_price'   => $item->price,
                        'total_price'  => $item->total_price,
                        'attributes'   => $item->attributes,
                    ]);
                }

                // ---------------------------------------------------------------
                // ۶. سبد را 'converted' می‌کنیم — نه 'completed' (چون هنوز پرداخت نشده)
                //    این کار جلوی ساخت سفارش تکراری با کلیک دوباره یا رفرش را می‌گیرد.
                // ---------------------------------------------------------------
                DB::table('carts')->where('id', $cartId)->update([
                    'status'     => 'converted',
                    'updated_at' => now(),
                ]);

                return $order;
            });
            return $this->redirectRoute('checkout', ['code' => $order->order_number]);

        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('alert', type: 'error', message: 'در ثبت سفارش مشکلی به وجود آمد. لطفاً دوباره تلاش کنید.');
            return;
        }
    }
};
?>

<div>
    <main class="space-y-12">

        {{-- CONTENT --}}
        <section class="relative py-16 transition-colors duration-700">

            {{-- STEP LINE --}}
            <div class="max-w-4xl mx-auto mb-16 px-4" dir="rtl">
                <div class="relative flex items-center justify-between">
                    <div class="absolute top-1/2 left-0 w-full h-1 bg-gray-200 dark:bg-white/5 -translate-y-1/2 rounded-full"></div>
                    <div class="absolute top-1/2 right-0 w-0 h-1 bg-blue-500 -translate-y-1/2 rounded-full transition-all duration-700"></div>

                    <div class="relative z-10 flex flex-col items-center gap-3">
                        <div class="w-14 h-14 bg-blue-500 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/40 border-4 border-white dark:border-[#0f172a]">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <span class="text-[11px] font-black text-blue-600 dark:text-blue-400 uppercase tracking-widest">سبد خرید</span>
                    </div>

                    <div class="relative z-10 flex flex-col items-center gap-3 opacity-50">
                        <div class="w-14 h-14 bg-white/40 dark:bg-white/[0.02] backdrop-blur-md text-gray-400 rounded-2xl flex items-center justify-center border-4 border-gray-100 dark:border-white/5">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </div>
                        <span class="text-[11px] font-black text-gray-400 uppercase tracking-widest">اطلاعات ارسال</span>
                    </div>

                    <div class="relative z-10 flex flex-col items-center gap-3 opacity-50">
                        <div class="w-14 h-14 bg-white/40 dark:bg-white/[0.02] backdrop-blur-md text-gray-400 rounded-2xl flex items-center justify-center border-4 border-gray-100 dark:border-white/5">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        </div>
                        <span class="text-[11px] font-black text-gray-400 uppercase tracking-widest">پرداخت نهایی</span>
                    </div>
                </div>
            </div>


            {{-- MAIN --}}
            <div class="px-4 py-12">

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 items-start">


                    {{-- ITEMS --}}
                    <div class="lg:col-span-2 space-y-6">

                        <div class="flex items-center justify-between px-6 mb-8">

                            <div class="flex items-center gap-4">

                                <div class="w-2 h-10 bg-blue-600 rounded-full shadow-[0_0_15px_rgba(37,99,235,0.5)]"></div>

                                <h1 class="text-3xl font-black text-gray-900 dark:text-white">
                                    سبد خرید شما
                                </h1>

                            </div>

                            <span class="px-5 py-2 bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-2xl text-xs font-black">

                                {{ number_format($this->totalCount) }}

                                کالا

                            </span>

                        </div>


                        {{-- EMPTY --}}
                        @if($this->items->isEmpty())

                            <div class="bg-white/40 dark:bg-white/[0.03] border border-white/50 dark:border-white/10 rounded-[2.8rem] p-16 text-center">

                                <div class="w-24 h-24 mx-auto mb-6 rounded-[2rem] bg-gray-100 dark:bg-white/5 flex items-center justify-center">

                                    <svg
                                        class="w-10 h-10 text-gray-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.5"
                                            d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
                                        />
                                    </svg>

                                </div>

                                <h3 class="text-xl font-black text-gray-900 dark:text-white">
                                    سبد خرید شما خالی است
                                </h3>

                                <p class="text-sm text-gray-500 mt-3">
                                    محصولی برای ثبت سفارش وجود ندارد.
                                </p>

                            </div>

                        @else

                            {{-- ITEMS --}}
                            <div class="space-y-6">

                                @foreach($this->items as $item)

                                    <div
                                        wire:key="checkout-cart-item-{{ $item->cart_item_id }}"
                                        class="group relative bg-white/40 dark:bg-white/[0.03] backdrop-blur-[30px] border border-white/40 dark:border-white/10 rounded-[2.8rem] p-6 md:p-8 transition-all duration-500 hover:shadow-[0_30px_60px_-15px_rgba(0,0,0,0.1)]"
                                    >

                                        <div class="flex flex-col md:flex-row items-center gap-6 md:gap-10">


                                            {{-- IMAGE --}}
                                            <div class="relative w-40 h-40 flex-shrink-0 bg-gradient-to-br from-white to-gray-100 dark:from-white/10 dark:to-transparent rounded-[2.2rem] p-4 border border-white dark:border-white/5 shadow-inner overflow-hidden flex items-center justify-center">

                                                @if($item->image)

                                                    <img
                                                        src="{{ asset('storage/' . $item->image->file_path) }}"
                                                        alt="{{ $item->title }}"
                                                        class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-700"
                                                    >

                                                @else

                                                    <svg
                                                        class="w-12 h-12 text-gray-300"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="1.5"
                                                            d="M4 16l4-4 4 4 4-5 4 5M4 19h16M5 5h14a1 1 0 011 1v12a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"
                                                        />
                                                    </svg>

                                                @endif

                                            </div>


                                            {{-- INFO --}}
                                            <div class="flex-1 space-y-4 text-center md:text-right">

                                                <div>

                                                    <h3 class="text-base md:text-lg font-black text-gray-800 dark:text-gray-100 leading-8">
                                                        {{ $item->title }}
                                                    </h3>

                                                    @if($item->variant)

                                                        <p class="text-[10px] font-bold text-gray-400 mt-1">
                                                            {{ $item->variant->title ?? '' }}
                                                        </p>

                                                    @endif

                                                </div>

                                                <div class="flex flex-wrap justify-center md:justify-start gap-2">

                                                    @if($item->stock !== null)

                                                        <span class="flex items-center gap-2 px-4 py-1.5 bg-emerald-500/5 border border-emerald-500/10 rounded-full text-[10px] font-bold text-emerald-600">

                                                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>

                                                            موجود در انبار

                                                        </span>

                                                    @endif

                                                </div>

                                            </div>


                                            {{-- PRICE / QUANTITY --}}
                                            <div class="flex flex-col items-center md:items-end gap-6 md:min-w-[200px] border-t md:border-t-0 md:border-r border-gray-200/50 dark:border-white/5 pt-6 md:pt-2 md:pr-8">

                                                {{-- CONTROLS --}}
                                                <div class="flex items-center gap-3">

                                                    {{-- REMOVE --}}
                                                    <button
                                                        type="button"
                                                        wire:click="remove({{ $item->cart_item_id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="remove({{ $item->cart_item_id }})"
                                                        class="w-10 h-10 flex items-center justify-center bg-rose-500/10 text-rose-500 rounded-xl hover:bg-rose-500 hover:text-white transition-all duration-300 active:scale-90"
                                                    >

                                                        <svg
                                                            class="w-5 h-5"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                            />
                                                        </svg>

                                                    </button>


                                                    {{-- COUNTER --}}
                                                    <div class="flex items-center bg-gray-100/50 dark:bg-white/5 p-1.5 rounded-[1.2rem] border border-gray-200/50 dark:border-white/10 shadow-inner">

                                                        {{-- PLUS --}}
                                                        <button
                                                            type="button"
                                                            wire:click="increase({{ $item->cart_item_id }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="increase({{ $item->cart_item_id }})"
                                                            class="w-9 h-9 flex items-center justify-center bg-blue-600 text-white rounded-lg shadow-lg shadow-blue-500/30 hover:scale-105 active:scale-90 transition-all disabled:opacity-50"
                                                        >

                                                            <svg
                                                                class="w-5 h-5"
                                                                fill="none"
                                                                stroke="currentColor"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <path
                                                                    stroke-linecap="round"
                                                                    stroke-linejoin="round"
                                                                    stroke-width="2.5"
                                                                    d="M12 6v12M6 12h12"
                                                                />
                                                            </svg>

                                                        </button>


                                                        {{-- VALUE --}}
                                                        <span class="w-10 text-center text-sm font-black text-gray-900 dark:text-white">

                                                            {{ $item->quantity }}

                                                        </span>


                                                        {{-- MINUS --}}
                                                        <button
                                                            type="button"
                                                            wire:click="decrease({{ $item->cart_item_id }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="decrease({{ $item->cart_item_id }})"
                                                            class="w-9 h-9 flex items-center justify-center bg-white dark:bg-white/10 text-gray-400 dark:text-gray-300 rounded-lg border border-gray-200/60 dark:border-white/5 shadow-sm hover:bg-rose-50 hover:text-rose-500 transition-all active:scale-90 disabled:opacity-50"
                                                        >

                                                            <svg
                                                                class="w-5 h-5"
                                                                fill="none"
                                                                stroke="currentColor"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <path
                                                                    stroke-linecap="round"
                                                                    stroke-linejoin="round"
                                                                    stroke-width="2.5"
                                                                    d="M18 12H6"
                                                                />
                                                            </svg>

                                                        </button>

                                                    </div>

                                                </div>


                                                {{-- PRICE --}}
                                                <div class="text-center md:text-left">

                                                    <span class="block text-2xl font-black text-blue-600 dark:text-blue-400 tracking-tighter">

                                                        {{ number_format($item->line_total) }}

                                                        <span class="text-[10px] font-bold text-gray-500">
                                                            تومان
                                                        </span>

                                                    </span>

                                                    @if($item->quantity > 1)

                                                        <span class="block text-[10px] text-gray-400 mt-1">

                                                            {{ number_format($item->unit_price) }}
                                                            تومان ×
                                                            {{ $item->quantity }}

                                                        </span>

                                                    @endif

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                @endforeach

                            </div>

                        @endif

                    </div>


                    {{-- SUMMARY --}}
                    <div class="lg:col-span-1 lg:sticky lg:top-24">

                        <div class="relative group max-w-sm mx-auto">

                            {{-- Decorative Glow --}}
                            <div
                                class="absolute -top-10 -left-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-[80px]"
                            ></div>


                            <div
                                class="relative bg-white/20 dark:bg-black/20 backdrop-blur-[60px]
                   border border-white/50 dark:border-white/5
                   rounded-[3.5rem] p-2 shadow-lg overflow-hidden"
                            >

                                {{-- ================= HEADER ================= --}}
                                <div class="p-8 pb-4 text-center">

                                    <h3 class="text-2xl font-black text-gray-900 dark:text-white">
                                        خلاصه سفارش
                                    </h3>

                                    <p class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-[4px] mt-1">
                                        Order Summary
                                    </p>

                                </div>


                                {{-- ================= SUMMARY ================= --}}
                                <div
                                    class="bg-white/40 dark:bg-white/[0.02]
                       rounded-[3rem] p-8 space-y-6
                       border border-white/60 dark:border-white/5
                       shadow-inner"
                                >

                                    <div class="space-y-4">

                                        {{-- SUBTOTAL --}}
                                        <div class="flex justify-between items-center">

                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400">
                            مجموع محصولات
                        </span>

                                            <div class="flex-1 border-b border-dashed border-gray-300 dark:border-white/10 mx-4 mb-1"></div>

                                            <span class="text-sm font-black text-gray-900 dark:text-white whitespace-nowrap">

                            {{ number_format($this->subtotal) }}

                            <span class="text-[9px] font-bold text-gray-400">
                                تومان
                            </span>

                        </span>

                                        </div>


                                        {{-- DISCOUNT --}}
                                        <div class="flex justify-between items-center">

                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400">
                            تخفیف
                        </span>

                                            <div class="flex-1 border-b border-dashed border-gray-300 dark:border-white/10 mx-4 mb-1"></div>

                                            @if($this->discount > 0)

                                                <span class="text-sm font-black text-rose-500 whitespace-nowrap">

                                {{ number_format($this->discount) }}-

                                <span class="text-[9px] font-bold">
                                    تومان
                                </span>

                            </span>

                                            @else

                                                <span class="text-sm font-black text-gray-400">
                                ۰
                            </span>

                                            @endif

                                        </div>


                                        {{-- TAX --}}
                                        <div class="flex justify-between items-center">

                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400">
                            مالیات بر ارزش افزوده
                        </span>

                                            <div class="flex-1 border-b border-dashed border-gray-300 dark:border-white/10 mx-4 mb-1"></div>

                                            @if($this->tax > 0)

                                                <span class="text-sm font-black text-gray-900 dark:text-white whitespace-nowrap">

                                {{ number_format($this->tax) }}

                                <span class="text-[9px] font-bold text-gray-400">
                                    تومان
                                </span>

                            </span>

                                            @else

                                                <span
                                                    class="text-[10px] font-black text-emerald-500
                                       bg-emerald-500/10 px-2 py-0.5 rounded-lg"
                                                >
                                رایگان
                            </span>

                                            @endif

                                        </div>

                                    </div>


                                    {{-- Divider --}}
                                    <div
                                        class="relative h-px bg-gradient-to-r
                           from-transparent via-gray-300
                           dark:via-white/10 to-transparent my-2"
                                    ></div>


                                    {{-- ================= PAYABLE ================= --}}
                                    <div class="flex flex-col items-center gap-1">

                    <span
                        class="text-[10px] font-black
                               text-blue-600 dark:text-blue-400
                               uppercase tracking-[5px]"
                    >
                        مبلغ قابل پرداخت
                    </span>

                                        <div class="flex items-baseline gap-1">

                        <span
                            class="text-4xl font-black
                                   text-gray-900 dark:text-white
                                   tracking-tighter"
                        >
                            {{ number_format($this->payable) }}
                        </span>

                                            <span class="text-[10px] font-bold text-gray-400">
                            تومان
                        </span>

                                        </div>

                                    </div>


                                    {{-- ================= COUPON ================= --}}
                                    @if($appliedCoupon)

                                        {{-- Applied Coupon --}}
                                        <div
                                            class="relative overflow-hidden
                               bg-emerald-500/10
                               border border-emerald-500/20
                               rounded-2xl p-4"
                                        >

                                            <div class="flex items-center justify-between gap-3">

                                                <div class="flex items-center gap-3 min-w-0">

                                                    {{-- Icon --}}
                                                    <div
                                                        class="w-10 h-10 flex-shrink-0
                                           bg-emerald-500/15
                                           text-emerald-600
                                           dark:text-emerald-400
                                           rounded-xl
                                           flex items-center justify-center"
                                                    >

                                                        <svg
                                                            class="w-5 h-5"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M9 12l2 2 4-4m6-2a9 9 0 11-18 0 9 9 0 0118 0z"
                                                            />
                                                        </svg>

                                                    </div>


                                                    {{-- Coupon Info --}}
                                                    <div class="min-w-0">

                                                        <div
                                                            class="text-[10px]
                                               text-emerald-600
                                               dark:text-emerald-400
                                               font-bold mb-1"
                                                        >
                                                            کد تخفیف اعمال شد
                                                        </div>

                                                        <div
                                                            class="font-black text-sm
                                               text-gray-900
                                               dark:text-white
                                               truncate"
                                                        >
                                                            {{ $appliedCoupon->code }}
                                                        </div>

                                                    </div>

                                                </div>


                                                {{-- Remove Coupon --}}
                                                <button
                                                    type="button"
                                                    wire:click="removeCoupon"
                                                    wire:loading.attr="disabled"
                                                    wire:target="removeCoupon"
                                                    class="flex-shrink-0 text-xs font-black
                                       text-rose-500
                                       hover:text-rose-600
                                       transition-colors
                                       disabled:opacity-50"
                                                >

                                <span
                                    wire:loading.remove
                                    wire:target="removeCoupon"
                                >
                                    حذف
                                </span>

                                                    <span
                                                        wire:loading
                                                        wire:target="removeCoupon"
                                                    >
                                    ...
                                </span>

                                                </button>

                                            </div>

                                        </div>

                                    @else

                                        {{-- Coupon Input --}}
                                        <div>

                                            <div class="relative group/input">

                                                <input
                                                    type="text"
                                                    wire:model.defer="couponCode"
                                                    wire:keydown.enter="applyCoupon"
                                                    autocomplete="off"
                                                    placeholder="کد تخفیف داری؟"
                                                    class="w-full
                                       bg-white/50 dark:bg-black/20
                                       border border-white dark:border-white/10
                                       rounded-2xl
                                       py-4 pr-12 pl-20
                                       text-xs font-bold
                                       focus:outline-none
                                       focus:ring-2
                                       focus:ring-blue-500/20
                                       transition-all
                                       placeholder:text-gray-400"
                                                />


                                                {{-- Ticket Icon --}}
                                                <svg
                                                    class="absolute right-4 top-1/2
                                       -translate-y-1/2
                                       w-5 h-5 text-gray-400"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"
                                                    />
                                                </svg>


                                                {{-- Apply --}}
                                                <button
                                                    type="button"
                                                    wire:click="applyCoupon"
                                                    wire:loading.attr="disabled"
                                                    wire:target="applyCoupon"
                                                    class="absolute left-2 top-1/2
                                       -translate-y-1/2
                                       bg-blue-600
                                       text-white
                                       text-[9px]
                                       font-black
                                       px-3 py-2
                                       rounded-xl
                                       hover:bg-blue-700
                                       transition-colors
                                       shadow-lg
                                       shadow-blue-500/20
                                       disabled:opacity-50"
                                                >

                                <span
                                    wire:loading.remove
                                    wire:target="applyCoupon"
                                >
                                    اعمال
                                </span>

                                                    <span
                                                        wire:loading
                                                        wire:target="applyCoupon"
                                                    >
                                    ...
                                </span>

                                                </button>

                                            </div>


                                            {{-- Coupon Error --}}
                                            @error('couponCode')

                                            <p class="text-[10px] font-bold text-rose-500 mt-2 px-2">
                                                {{ $message }}
                                            </p>

                                            @enderror

                                        </div>

                                    @endif

                                </div>


                                {{-- ================= PAYMENT ================= --}}
                                <div class="p-6">

                                    <button
                                        type="button"
                                        wire:click="proceedToPayment"
                                        wire:loading.attr="disabled"
                                        wire:target="proceedToPayment"
                                        class="group/pay relative w-full h-20
                           bg-blue-600 dark:bg-blue-500
                           rounded-[2.2rem]
                           overflow-hidden
                           transition-all duration-500
                           shadow-[0_20px_40px_-10px_rgba(37,99,235,0.5)]
                           hover:shadow-[0_25px_50px_-12px_rgba(37,99,235,0.7)]
                           hover:-translate-y-1
                           active:scale-95
                           disabled:opacity-60"
                                    >

                                        {{-- Shimmer --}}
                                        <div
                                            class="absolute inset-0
                               bg-gradient-to-r
                               from-transparent via-white/20 to-transparent
                               -translate-x-full
                               group-hover/pay:animate-[shimmer_1.5s_infinite]
                               transition-transform duration-1000"
                                        ></div>


                                        <div class="relative flex items-center justify-between px-8">

                        <span class="text-white font-black text-xl tracking-tight">

                            <span
                                wire:loading.remove
                                wire:target="proceedToPayment"
                            >
                                تأیید و پرداخت نهایی
                            </span>

                            <span
                                wire:loading
                                wire:target="proceedToPayment"
                            >
                                در حال انتقال به درگاه...
                            </span>

                        </span>


                                            {{-- Arrow --}}
                                            <div
                                                class="w-12 h-12
                                   bg-white/20
                                   rounded-2xl
                                   flex items-center justify-center
                                   backdrop-blur-md
                                   border border-white/30
                                   transition-all duration-500
                                   shadow-inner"
                                            >

                                                <svg
                                                    class="w-6 h-6 text-white rotate-180"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2.5"
                                                        d="M14 5l7 7m0 0l-7 7m7-7H3"
                                                    />
                                                </svg>

                                            </div>

                                        </div>

                                    </button>

                                </div>

                            </div>

                        </div>

                    </div>
                </div>

            </div>

        </section>


        {{-- SHOP FEATURES --}}
        <section class="relative overflow-hidden transition-colors duration-500">

            <div class="container pt-5">

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-12 lg:gap-8">

                    {{-- ارسال --}}
                    <div class="flex flex-col items-center text-center group">

                        <div class="relative w-20 h-20 mb-6 flex items-center justify-center">

                            <div class="relative z-10 w-full h-full bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm flex items-center justify-center">

                                <svg
                                    class="w-9 h-9 text-gray-700 dark:text-gray-300 group-hover:text-blue-500 transition-colors"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M13 10V3L4 14h7v7l9-11h-7z"
                                    />
                                </svg>

                            </div>

                        </div>

                        <h3 class="text-sm font-black text-gray-900 dark:text-white mb-2">
                            ارسال فوق سریع
                        </h3>

                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-6 max-w-[150px]">
                            تحویل کالا در کمتر از ۲۴ ساعت در سراسر کشور
                        </p>

                    </div>


                    {{-- بازگشت --}}
                    <div class="flex flex-col items-center text-center group">

                        <div class="relative w-20 h-20 mb-6 flex items-center justify-center">

                            <div class="relative z-10 w-full h-full bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm flex items-center justify-center">

                                <svg
                                    class="w-9 h-9 text-gray-700 dark:text-gray-300 group-hover:text-secondary-500 transition-colors"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"
                                    />
                                </svg>

                            </div>

                        </div>

                        <h3 class="text-sm font-black text-gray-900 dark:text-white mb-2">
                            ۷ روز ضمانت بازگشت
                        </h3>

                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-6 max-w-[150px]">
                            امکان بازگشت کالا در صورت عدم رضایت یا نقص
                        </p>

                    </div>


                    {{-- پرداخت امن --}}
                    <div class="flex flex-col items-center text-center group">

                        <div class="relative w-20 h-20 mb-6 flex items-center justify-center">

                            <div class="relative z-10 w-full h-full bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm flex items-center justify-center">

                                <svg
                                    class="w-9 h-9 text-gray-700 dark:text-gray-300 group-hover:text-emerald-500 transition-colors"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                    />
                                </svg>

                            </div>

                        </div>

                        <h3 class="text-sm font-black text-gray-900 dark:text-white mb-2">
                            پرداخت امن
                        </h3>

                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-6 max-w-[150px]">
                            استفاده از پروتکل‌های امن و درگاه‌های معتبر
                        </p>

                    </div>


                    {{-- اصالت --}}
                    <div class="flex flex-col items-center text-center group">

                        <div class="relative w-20 h-20 mb-6 flex items-center justify-center">

                            <div class="relative z-10 w-full h-full bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm flex items-center justify-center">

                                <svg
                                    class="w-9 h-9 text-gray-700 dark:text-gray-300 group-hover:text-indigo-500 transition-colors"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M9 12l2 2 4-2m-4 2 2 4m-4-8 4-2m-4 2 4 2"
                                    />
                                </svg>

                            </div>

                        </div>

                        <h3 class="text-sm font-black text-gray-900 dark:text-white mb-2">
                            ضمانت اصالت
                        </h3>

                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-6 max-w-[150px]">
                            تضمین ۱۰۰٪ کالاها با گارانتی معتبر
                        </p>

                    </div>

                </div>

            </div>

        </section>

    </main>
</div>
