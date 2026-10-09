<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

/**
 * پیش‌نمایش سریع محصول (مودال)
 * باز شدن: $dispatch('zc-quick-view', { id }) روی هر دکمه‌ای در سایت
 */
new class extends Component
{
    public ?int $productId = null;
    public ?int $variantId = null;

    #[On('quick-view')]
    public function load(int $id): void
    {
        $product = Product::active()->find($id);

        if (! $product) {
            $this->productId = null;
            $this->dispatch('alert', type: 'error', message: 'این محصول در دسترس نیست.');
            return;
        }

        $this->productId = $product->id;
        $variants = $product->variants()->with('values')->get();
        $this->variantId = ($variants->first(fn ($v) => $v->isInStock()) ?? $product->cheapestVariant ?? $variants->first())?->id;
    }

    public function close(): void
    {
        $this->productId = null;
        $this->variantId = null;
    }

    public function getProductProperty(): ?Product
    {
        return $this->productId
            ? Product::with(['variants.values', 'options', 'brand', 'media'])->find($this->productId)
            : null;
    }

    public function selectOption(int $valueId): void
    {
        $product = $this->product;
        $current = $product?->variants->firstWhere('id', $this->variantId);
        $newValue = \App\Models\OptionValue::find($valueId);

        if (! $product || ! $current || ! $newValue) {
            return;
        }

        $wanted = $current->values
            ->map(fn ($v) => $v->option_id == $newValue->option_id ? $newValue->id : $v->id)
            ->sort()->values()->all();

        $match = $product->variants->first(fn ($v) => $v->values->pluck('id')->sort()->values()->all() == $wanted)
            // ترکیب دقیق نبود => اولین واریانتی که این مقدار را دارد
            ?? $product->variants->first(fn ($v) => $v->values->contains('id', $newValue->id));

        if ($match) {
            $this->variantId = $match->id;
        }
    }

    public function addToCart(): void
    {
        if (! Auth::check()) {
            $this->dispatch('alert', type: 'error', message: 'برای خرید ابتدا وارد شوید.');
            return;
        }

        $variant = $this->product?->variants->firstWhere('id', $this->variantId);

        if (! $variant) {
            $this->dispatch('alert', type: 'error', message: 'این کالا در دسترس نیست.');
            return;
        }

        $pricing = $variant->priceData();

        $result = app(\App\Services\Cart\CartService::class)->add(
            Auth::id(),
            $variant->product_id,
            $variant->id,
            (int) ($pricing['after_discount'] ?? $variant->price ?? 0)
        );

        if (! $result['ok']) {
            $this->dispatch('alert', type: 'error', message: $result['message']);
            return;
        }

        $this->dispatch('cart-updated');
        $this->dispatch('alert', type: 'success', message: $result['message']);
    }
};
?>

<div x-data="{ show: false }"
     x-on:zc-quick-view.window="show = true; document.documentElement.classList.add('overflow-hidden'); $wire.load($event.detail.id)"
     x-on:keydown.escape.window="if (show) { show = false; document.documentElement.classList.remove('overflow-hidden'); $wire.close() }">

    <div x-show="show" x-cloak
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[999] flex items-end md:items-center justify-center bg-black/40 dark:bg-black/70 backdrop-blur-sm p-0 md:p-6"
         x-on:click.self="show = false; document.documentElement.classList.remove('overflow-hidden'); $wire.close()"
         dir="rtl" role="dialog" aria-modal="true" aria-label="پیش‌نمایش محصول">

        <div x-show="show"
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-8 md:translate-y-0 md:scale-95" x-transition:enter-end="opacity-100 translate-y-0 md:scale-100"
             class="relative w-full md:max-w-4xl max-h-[92vh] overflow-y-auto bg-white dark:bg-zinc-900 rounded-t-[2rem] md:rounded-[2.5rem] shadow-2xl border border-white/60 dark:border-white/10">

            <button type="button" aria-label="بستن"
                    x-on:click="show = false; document.documentElement.classList.remove('overflow-hidden'); $wire.close()"
                    class="absolute top-4 left-4 z-20 w-10 h-10 rounded-2xl bg-white/80 dark:bg-zinc-800/80 backdrop-blur-md border border-gray-100 dark:border-white/10 text-gray-500 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            {{-- در حال بارگذاری --}}
            <div wire:loading.flex wire:target="load" class="hidden min-h-[320px] items-center justify-center">
                <div class="w-10 h-10 rounded-full border-4 border-brown-600/20 border-t-brown-600 animate-spin"></div>
            </div>

            @php
                $product = $this->product;
            @endphp

            <div wire:loading.remove wire:target="load">
                @if($product)
                    @php
                        $variant = $product->variants->firstWhere('id', $variantId) ?? $product->variants->first();
                        $pricing = $variant ? $variant->priceData() : [];
                        $inStock = $variant?->isInStock() ?? false;
                        $stock = $variant?->availableStock() ?? 0;
                        $availableValueIds = $product->variants->filter(fn ($v) => $v->isInStock())->pluck('values')->flatten()->pluck('id')->unique()->all();
                        $images = $product->media
                            ->filter(fn ($m) => ! $m->external_url && $m->file_path && ($m->type === 'image' || str_starts_with((string) $m->mime_type, 'image/')))
                            ->sortBy(fn ($m) => $m->collection === 'featured_image' ? 0 : 1)
                            ->map(fn ($m) => url('/storage/' . $m->file_path))
                            ->unique()->values()->all();
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 p-5 md:p-8" wire:key="qv-{{ $product->id }}">

                        {{-- گالری --}}
                        <div x-data="{ active: 0, images: @js($images) }" class="space-y-3">
                            <div class="relative aspect-square rounded-[1.75rem] bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/5 overflow-hidden flex items-center justify-center {{ $inStock ? '' : 'grayscale' }}">
                                <template x-if="images.length">
                                    <img :src="images[active]" alt="{{ $product->title }}" class="w-full h-full object-contain p-4 transition-opacity duration-300">
                                </template>
                                <template x-if="!images.length">
                                    <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </template>

                                @if(! empty($pricing['has_discount']))
                                    <span class="absolute top-4 right-4 px-3 py-1 rounded-xl bg-red-500 text-white text-xs font-black">{{ $pricing['discount_percent'] }}٪</span>
                                @endif
                                @unless($inStock)
                                    <span class="absolute bottom-4 right-4 px-3 py-1 rounded-xl bg-zinc-900/90 text-white text-xs font-black">ناموجود</span>
                                @endunless

                                <template x-if="images.length > 1">
                                    <div>
                                        <button type="button" aria-label="تصویر قبلی" x-on:click="active = (active - 1 + images.length) % images.length"
                                                class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-xl bg-white/90 dark:bg-zinc-800/90 shadow flex items-center justify-center text-gray-700 dark:text-gray-200 hover:bg-brown-600 hover:text-white transition-all">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                        </button>
                                        <button type="button" aria-label="تصویر بعدی" x-on:click="active = (active + 1) % images.length"
                                                class="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-xl bg-white/90 dark:bg-zinc-800/90 shadow flex items-center justify-center text-gray-700 dark:text-gray-200 hover:bg-brown-600 hover:text-white transition-all">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <template x-if="images.length > 1">
                                <div class="flex gap-2 overflow-x-auto pb-1">
                                    <template x-for="(src, i) in images" :key="i">
                                        <button type="button" x-on:click="active = i"
                                                :class="active === i ? 'border-brown-600 opacity-100' : 'border-transparent opacity-50 hover:opacity-100'"
                                                class="shrink-0 w-16 h-16 rounded-xl border-2 bg-gray-50 dark:bg-white/5 overflow-hidden transition-all">
                                            <img :src="src" alt="" class="w-full h-full object-contain p-1">
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- اطلاعات --}}
                        <div class="flex flex-col min-w-0">
                            @if($product->brand)
                                <span class="text-xs font-black text-brown-600 dark:text-brown-400 mb-2">{{ $product->brand->title }}</span>
                            @endif
                            <h3 class="text-xl md:text-2xl font-black text-gray-900 dark:text-white leading-relaxed pl-12">{{ $product->title }}</h3>
                            @if($variant?->sku)
                                <span class="mt-2 text-[11px] text-gray-400">شناسه کالا: <span class="font-bold text-gray-600 dark:text-gray-300" dir="ltr">{{ $variant->sku }}</span></span>
                            @endif

                            {{-- ویژگی‌ها --}}
                            <div class="mt-6 space-y-5">
                                @foreach($product->options as $option)
                                    @php
                                        $values = $product->variants->pluck('values')->flatten()->where('option_id', $option->id)->unique('id');
                                    @endphp
                                    @if($values->isNotEmpty())
                                    <div>
                                        <p class="text-[13px] font-black text-gray-800 dark:text-gray-200 mb-3">
                                            {{ $option->title }}:
                                            <span class="text-gray-500 font-bold">{{ $variant?->values->where('option_id', $option->id)->first()?->title }}</span>
                                        </p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($values as $value)
                                                @php
                                                    $active = $variant?->values->contains('id', $value->id);
                                                    $out = ! in_array($value->id, $availableValueIds);
                                                @endphp
                                                @if($value->color_code)
                                                    <button type="button" wire:click="selectOption({{ $value->id }})" title="{{ $value->title }}"
                                                            class="w-9 h-9 rounded-xl border-2 transition-all {{ $active ? 'border-brown-600 ring-4 ring-brown-600/20' : 'border-white dark:border-zinc-700 shadow' }} {{ $out ? 'opacity-40' : '' }}"
                                                            style="background-color: {{ $value->color_code }}"></button>
                                                @else
                                                    <button type="button" wire:click="selectOption({{ $value->id }})"
                                                            class="px-4 py-2 rounded-xl text-xs font-bold border-2 transition-all {{ $active ? 'border-brown-600 text-brown-700 dark:text-brown-300 bg-brown-600/5' : 'border-gray-100 dark:border-white/10 text-gray-600 dark:text-gray-300 hover:border-brown-600/40' }} {{ $out ? 'opacity-50 line-through' : '' }}">
                                                        {{ $value->title }}
                                                    </button>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif
                                @endforeach
                            </div>

                            {{-- موجودی --}}
                            <div class="mt-6 text-xs font-bold">
                                @if(! $inStock)
                                    <span class="text-red-500">این کالا در حال حاضر موجود نیست</span>
                                @elseif($stock > 0 && $stock <= 3)
                                    <span class="text-amber-600">فقط {{ $stock }} عدد در انبار باقی مانده</span>
                                @else
                                    <span class="text-emerald-600">موجود در انبار</span>
                                @endif
                            </div>

                            {{-- قیمت و خرید --}}
                            <div class="mt-auto pt-6">
                                <div class="p-5 rounded-[1.75rem] bg-brown-50/50 dark:bg-brown-500/5 border border-brown-100 dark:border-brown-500/15">
                                    <div class="flex items-end justify-between gap-3">
                                        <div class="flex flex-col">
                                            @if(! empty($pricing['has_discount']))
                                                <span class="text-xs font-bold text-gray-400 line-through">{{ number_format($pricing['before_discount']) }}</span>
                                            @endif
                                            <span class="text-2xl md:text-3xl font-black text-brown-600 dark:text-brown-400 tabular-nums">
                                                {{ number_format($pricing['after_discount'] ?? $variant?->price ?? 0) }}
                                                <span class="text-xs font-bold text-gray-500">تومان</span>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <button type="button" wire:click="addToCart" wire:loading.attr="disabled" wire:target="addToCart"
                                                @disabled(! $inStock)
                                                class="inline-flex items-center justify-center gap-2 py-3.5 rounded-2xl bg-brown-600 hover:bg-brown-700 text-white text-sm font-black shadow-lg shadow-brown-600/20 transition-all active:scale-95 disabled:opacity-50 disabled:pointer-events-none">
                                            <svg wire:loading.remove wire:target="addToCart" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                            <span wire:loading wire:target="addToCart" class="w-4 h-4 rounded-full border-2 border-white/40 border-t-white animate-spin"></span>
                                            {{ $inStock ? 'افزودن به سبد' : 'ناموجود' }}
                                        </button>
                                        <a href="{{ route('products.show', $product->slug) }}"
                                           class="inline-flex items-center justify-center gap-2 py-3.5 rounded-2xl bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-200 text-sm font-black hover:border-brown-600/40 hover:text-brown-600 transition-all">
                                            مشاهده جزئیات
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
