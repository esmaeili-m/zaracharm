<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

new class extends Component
{
    private const MAX_QTY_PER_ITEM = 20; // یک سقفِ منطقی برای جلوگیری از افزایش بی‌رویه‌ی تعداد
    public bool $open = false;
    #[On('cart-updated')]
    public function refreshCart(): void
    {
        // این متد عمداً خالی است؛ صرفاً حضورش کافی‌ست تا وقتی از کامپوننت دیگری
        // (مثلاً addToCart در صفحه‌ی محصولات) رویداد cart-updated دیسپچ می‌شود،
        // Livewire این کامپوننت را هم دوباره رندر کند و پراپرتی‌های Computed تازه شوند.
    }

    private function currentCartId(): ?int
    {
        if (! Auth::check()) {
            return null;
        }

        return DB::table('carts')
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->value('id');
    }

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

        $productIds = $rows->pluck('product_id')->unique();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->with('media')
            ->get()
            ->keyBy('id');

        return $rows->map(function ($row) use ($products) {
            $product = $products->get($row->product_id);
            $attributes = json_decode($row->attributes ?? '{}', true) ?: [];
            $variantId = $attributes['variant_id'] ?? null;

            $variant = $variantId ? ProductVariant::query()->find($variantId) : null;

            $stock = $variantId
                ? (int) DB::table('inventory_items')
                    ->where('product_variant_id', $variantId)
                    ->where('status', 1)
                    ->whereNull('deleted_at')
                    ->selectRaw('SUM(quantity - reserved_quantity) as stock')
                    ->value('stock')
                : null;

            $image = $product?->media->firstWhere('collection', 'featured_image');

            return (object) [
                'cart_item_id' => $row->id,
                'product' => $product,
                'variant' => $variant,
                'title' => $product->title ?? 'محصول حذف‌شده',
                'image' => $image,
                'unit_price' => (int) $row->price,
                'quantity' => (int) $row->quantity,
                'line_total' => (int) $row->price * (int) $row->quantity,
                'stock' => $stock, // null یعنی محصول واریانتی/موجودی‌ای برایش ثبت نشده، محدودیتی اعمال نمی‌کنیم
            ];
        });
    }

    #[Computed]
    public function totalPrice(): int
    {
        return (int) $this->items->sum('line_total');
    }

    #[Computed]
    public function totalCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function increase(int $cartItemId): void
    {
        $cartId = $this->currentCartId();
        if (! $cartId) {
            return;
        }

        $item = DB::table('cart_items')->where('id', $cartItemId)->where('cart_id', $cartId)->first();
        if (! $item) {
            return;
        }

        $attributes = json_decode($item->attributes ?? '{}', true) ?: [];
        $variantId = $attributes['variant_id'] ?? null;

        if ($variantId) {
            $stock = (int) DB::table('inventory_items')
                ->where('product_variant_id', $variantId)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->selectRaw('SUM(quantity - reserved_quantity) as stock')
                ->value('stock');

            if ($item->quantity >= $stock) {
                $this->dispatch('notify', type: 'error', message: 'به سقف موجودی این کالا رسیده‌اید.');
                return;
            }
        }

        if ($item->quantity >= self::MAX_QTY_PER_ITEM) {
            return;
        }
        DB::table('cart_items')->where('id', $cartItemId)->increment('quantity');
    }

    public function decrease(int $cartItemId): void
    {
        $cartId = $this->currentCartId();
        if (! $cartId) {
            return;
        }

        $item = DB::table('cart_items')->where('id', $cartItemId)->where('cart_id', $cartId)->first();
        if (! $item) {
            return;
        }

        if ($item->quantity <= 1) {
            DB::table('cart_items')->where('id', $cartItemId)->delete();
        } else {
            DB::table('cart_items')->where('id', $cartItemId)->decrement('quantity');
        }
    }

    public function remove(int $cartItemId): void
    {
        $cartId = $this->currentCartId();
        if (! $cartId) {
            return;
        }

        DB::table('cart_items')->where('id', $cartItemId)->where('cart_id', $cartId)->delete();
    }
};
?>

<div
    id="cart-drawer"

    x-data="{ open: $wire.entangle('open').live }"
    x-on:open-cart.window="open = true"
    x-on:keydown.escape.window="open = false"
>

    {{-- Overlay --}}
    <div
        x-show="open"
        x-transition:enter="transition-opacity duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:click="open = false"
        class="fixed inset-0 z-[1099] bg-black/60 dark:bg-black/80 backdrop-blur-sm"
        style="display: none;"
    ></div>


    {{-- Cart Drawer --}}
    <div
        x-show="open"
        x-transition:enter="transition-transform duration-500"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition-transform duration-500"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed left-0 top-0 z-[1100] h-full w-full max-w-[420px] bg-white/95 dark:bg-gray-950/95 backdrop-blur-md shadow-2xl flex flex-col"
        style="display: none;"
        dir="rtl"
    >

        {{-- Header --}}
        <div class="p-6 border-b border-gray-200/50 dark:border-white/5 flex justify-between items-center bg-white/50 dark:bg-white/5">

            <h3 class="font-black text-lg dark:text-white flex items-center gap-2">

                <svg
                    class="w-6 h-6 text-brown-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
                    />
                </svg>

                سبد خرید

                <span class="text-xs font-normal text-gray-400 dark:text-gray-500">
                    ({{ $this->totalCount }} کالا)
                </span>

            </h3>


            <button
                type="button"
                x-on:click="open = false"
                class="p-2 hover:bg-red-500/10 hover:text-red-500 rounded-xl transition-all dark:text-white"
            >
                <svg
                    class="w-6 h-6"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M6 18L18 6M6 6l12 12"
                    />
                </svg>
            </button>

        </div>


        {{-- Cart Items --}}
        <div
            id="cart-items-container"
            class="flex-1 overflow-y-auto p-5"
        >

            @guest

                <div class="h-full flex flex-col items-center justify-center text-center">

                    <div class="w-24 h-24 bg-gray-100/50 dark:bg-white/5 rounded-full flex items-center justify-center mb-6 border border-dashed border-gray-300 dark:border-white/10 shadow-inner">

                        <svg
                            class="w-10 h-10 text-gray-400 opacity-50"
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

                    <h4 class="text-gray-800 dark:text-gray-200 font-black text-lg mb-2">
                        برای مشاهده سبد خرید وارد شوید
                    </h4>

                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-[250px] leading-6 mb-8">
                        سبد خرید فقط برای کاربران واردشده نگه‌داری می‌شود.
                    </p>

                    <a
                        href="{{ route('login') }}"
                        class="px-6 py-2.5 rounded-xl border border-brown-500/30 text-brown-600 dark:text-brown-400 font-bold text-sm hover:bg-brown-500 hover:text-white transition-all"
                    >
                        ورود به حساب کاربری
                    </a>

                </div>


            @elseif($this->items->isEmpty())

                <div class="h-full flex flex-col items-center justify-center text-center">

                    <div class="w-24 h-24 bg-gray-100/50 dark:bg-white/5 rounded-full flex items-center justify-center mb-6 border border-dashed border-gray-300 dark:border-white/10 shadow-inner">

                        <svg
                            class="w-10 h-10 text-gray-400 opacity-50"
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

                    <h4 class="text-gray-800 dark:text-gray-200 font-black text-lg mb-2">
                        سبد خرید شما خالی است!
                    </h4>

                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-[250px] leading-6 mb-8">
                        به‌نظر می‌رسد هنوز هیچ محصولی را به سبد خرید خود اضافه نکرده‌اید.
                    </p>

                    <button
                        type="button"
                        x-on:click="open = false"
                        class="px-6 py-2.5 rounded-xl border border-brown-500/30 text-brown-600 dark:text-brown-400 font-bold text-sm hover:bg-brown-500 hover:text-white transition-all"
                    >
                        شروع خرید از فروشگاه
                    </button>

                </div>


            @else

                <div
                    id="actual-items-list"
                    class="space-y-4"
                >

                    @foreach($this->items as $item)

                        <div
                            wire:key="cart-item-{{ $item->cart_item_id }}"
                            class="product-row group relative flex gap-4 p-3 rounded-[1.8rem] bg-white/50 dark:bg-white/[0.03] border border-white/60 dark:border-white/5 shadow-sm transition-all duration-500 hover:bg-white dark:hover:bg-white/[0.08]"
                        >

                            {{-- Product Image --}}
                            <div class="relative w-24 h-24 bg-white dark:bg-gray-800 rounded-[1.4rem] flex-shrink-0 p-3 shadow-inner border border-gray-100 dark:border-white/5">

                                @if($item->image)

                                    <img
                                        src="{{ asset('storage/' . $item->image->file_path) }}"
                                        class="w-full h-full object-contain"
                                        alt="{{ $item->title }}"
                                    >

                                @else

                                    <div class="w-full h-full flex items-center justify-center text-gray-300 dark:text-zinc-700">

                                        <svg
                                            class="w-8 h-8"
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

                                    </div>

                                @endif

                            </div>


                            {{-- Product Information --}}
                            <div class="flex flex-col justify-between flex-1 py-1">

                                <div class="flex justify-between items-start">

                                    <h4 class="text-[13px] font-black text-gray-800 dark:text-gray-100 line-clamp-2">
                                        {{ $item->title }}
                                    </h4>


                                    {{-- Remove --}}
                                    <button
                                        type="button"
                                        wire:click="remove({{ $item->cart_item_id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="remove({{ $item->cart_item_id }})"
                                        class="remove-item-btn text-gray-400 hover:text-red-500 transition-colors p-1"
                                    >

                                        <svg
                                            class="w-4 h-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                stroke-width="2"
                                            />
                                        </svg>

                                    </button>

                                </div>


                                <div class="flex justify-between items-end mt-2">

                                    {{-- Price --}}
                                    <span class="unit-price text-brown-600 dark:text-brown-400 font-black text-sm">

                                        {{ number_format($item->unit_price) }}

                                        <span class="text-[10px]">
                                            تومان
                                        </span>

                                    </span>


                                    {{-- Quantity --}}
                                    <div class="flex items-center gap-2 bg-gray-100/80 dark:bg-[#0a0a0a]/40 backdrop-blur-md rounded-xl p-1 border border-gray-200/50 dark:border-white/5">

                                        {{-- Increase --}}
                                        <button
                                            type="button"
                                            wire:click="increase({{ $item->cart_item_id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="increase({{ $item->cart_item_id }})"
                                            class="cart-counter-btn w-7 h-7 flex items-center justify-center bg-white dark:bg-gray-800 rounded-lg shadow-sm disabled:opacity-40"
                                        >
                                            +
                                        </button>


                                        {{-- Quantity --}}
                                        <span class="item-count w-6 text-center text-xs font-black dark:text-white">
                                            {{ $item->quantity }}
                                        </span>


                                        {{-- Decrease --}}
                                        <button
                                            type="button"
                                            wire:click="decrease({{ $item->cart_item_id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="decrease({{ $item->cart_item_id }})"
                                            class="cart-counter-btn w-7 h-7 flex items-center justify-center bg-white dark:bg-gray-800 rounded-lg shadow-sm disabled:opacity-40"
                                        >
                                            -
                                        </button>

                                    </div>

                                </div>

                            </div>

                        </div>

                    @endforeach

                </div>

            @endif

        </div>


        {{-- Footer --}}
        @auth

            @if($this->items->isNotEmpty())

                <div class="p-8 border-t border-gray-200/50 dark:border-white/5 space-y-5 bg-white/40 dark:bg-gray-950/40 backdrop-blur-md">

                    <div class="flex justify-between items-center text-sm">

                        <span class="text-gray-500 dark:text-gray-400 font-bold">
                            مجموع سبد خرید:
                        </span>

                        <span class="font-black text-xl dark:text-white text-brown-600 dark:text-brown-400">

                            {{ number_format($this->totalPrice) }}

                            <span class="text-[10px]">
                                تومان
                            </span>

                        </span>

                    </div>


                    <a
                        href="{{ route('cartItem') }}"
                        class="w-full bg-brown-600 hover:bg-brown-700 text-white font-black py-5 rounded-[2rem] shadow-lg shadow-brown-500/30 transition-all flex items-center justify-center gap-3 group relative overflow-hidden"
                    >

                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:animate-[shimmer_1.5s_infinite]"></div>

                        ثبت سفارش نهایی

                        <svg
                            class="w-5 h-5 group-hover:translate-x-[4px] transition-transform"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2.5"
                                d="M11 7l-5 5m0 0l5 5m-5-5h12"
                            />
                        </svg>

                    </a>

                </div>

            @endif

        @endauth

    </div>

</div>
