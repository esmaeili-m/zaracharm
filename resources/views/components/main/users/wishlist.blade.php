<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;

new class extends Component
{
    use WithPagination;

    public $user;

    public function mount($user)
    {
        $this->user = $user;
    }

    #[Computed]
    public function items()
    {
        // محصول حذف‌شده (soft delete) یا غیرفعال هم نمایش داده می‌شود تا کاربر بتواند آن را حذف کند
        return $this->user->wishlists()
            ->with([
                'product' => fn ($q) => $q->withTrashed()->with([
                    'featuredImage',
                    'cheapestVariant',
                ]),
            ])
            ->latest()
            ->paginate(12);
    }

    public function remove(int $productId): void
    {
        // فقط رکورد متعلق به همین کاربر حذف می‌شود
        $deleted = $this->user->wishlists()
            ->where('product_id', $productId)
            ->delete();

        if (! $deleted) {
            return;
        }

        unset($this->items);

        // اگر صفحه فعلی خالی شد به صفحه قبل برگرد
        if ($this->items->isEmpty() && $this->items->currentPage() > 1) {
            $this->previousPage();
        }

        $this->dispatch('alert', type: 'success', message: 'محصول از علاقه‌مندی‌ها حذف شد.');
    }
};
?>

<div class="space-y-6" dir="rtl">

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="w-2 h-8 bg-primary-500 rounded-full"></span>
            <h2 class="text-[16px] font-black text-gray-900 dark:text-white">علاقه‌مندی‌های من</h2>
        </div>
        <span class="px-3 py-1 rounded-xl bg-gray-100 dark:bg-white/10 text-[11px] font-black text-gray-500 dark:text-gray-300 tabular-nums">
            {{ $this->items->total() }} محصول
        </span>
    </div>

    @if($this->items->isEmpty())
        <div class="relative overflow-hidden bg-white/40 dark:bg-gray-950/60 backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-12 text-center shadow-[0_20px_50px_rgba(0,0,0,0.05)] dark:shadow-none">
            <div class="w-16 h-16 mx-auto mb-5 rounded-2xl bg-red-500/10 text-red-500 flex items-center justify-center">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
            </div>
            <p class="text-[14px] font-black text-gray-900 dark:text-white mb-2">لیست علاقه‌مندی‌های شما خالی است</p>
            <p class="text-[12px] font-bold text-gray-400">با زدن آیکون قلب در صفحه محصول، آن را به این لیست اضافه کنید.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach($this->items as $item)
                @php
                    $product = $item->product;
                    // محصول در دسترس = حذف نشده و فعال (مطابق صفحه محصول که غیرفعال‌ها را 404 می‌کند)
                    $available = $product && ! $product->trashed() && $product->status;
                    $variant = $available ? $product->cheapestVariant : null;
                    $pricing = $variant ? $variant->priceData() : [];
                    $inStock = $variant ? $variant->isInStock() : false;
                @endphp

                <div wire:key="wishlist-{{ $item->id }}"
                     class="relative overflow-hidden bg-white/40 dark:bg-gray-950/60 backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-5 shadow-[0_20px_50px_rgba(0,0,0,0.05)] dark:shadow-none group transition-all hover:border-primary-500/30 flex flex-col">

                    {{-- Remove --}}
                    <button
                        type="button"
                        wire:click="remove({{ $item->product_id }})"
                        wire:loading.attr="disabled"
                        wire:target="remove({{ $item->product_id }})"
                        title="حذف از علاقه‌مندی"
                        class="absolute top-4 left-4 z-10 w-9 h-9 rounded-xl bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md flex items-center justify-center text-red-500 shadow-sm hover:bg-red-500 hover:text-white transition-all disabled:opacity-50">
                        <svg class="w-4 h-4" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </button>

                    {{-- Image --}}
                    <div class="relative aspect-square rounded-[2rem] bg-white/60 dark:bg-black/20 overflow-hidden mb-5 {{ $available && $inStock ? '' : 'grayscale opacity-60' }}">
                        @if($product?->featured_image_url)
                            <img src="{{ $product->featured_image_url }}" alt="{{ $product->title }}" class="w-full h-full object-contain p-4 group-hover:scale-105 transition-transform duration-500">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-300 dark:text-zinc-700">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        @endif

                        @unless($available)
                            <span class="absolute bottom-3 right-3 px-3 py-1 rounded-lg bg-zinc-900/90 text-white text-[10px] font-black">غیرقابل دسترس</span>
                        @elseif(! $inStock)
                            <span class="absolute bottom-3 right-3 px-3 py-1 rounded-lg bg-zinc-900/90 text-white text-[10px] font-black">ناموجود</span>
                        @endunless
                    </div>

                    {{-- Title --}}
                    @if($available)
                        <a href="{{ route('products.show', $product->slug) }}">
                            <h3 class="text-[13px] font-black text-gray-800 dark:text-zinc-100 line-clamp-2 leading-7 h-14 hover:text-primary-500 transition-colors">
                                {{ $product->title }}
                            </h3>
                        </a>
                    @else
                        <h3 class="text-[13px] font-black text-gray-400 dark:text-zinc-600 line-clamp-2 leading-7 h-14">
                            {{ $product?->title ?? 'محصول حذف شده' }}
                        </h3>
                    @endif

                    {{-- Price --}}
                    <div class="flex items-center justify-between mt-auto pt-4 border-t border-gray-100 dark:border-white/5">
                        @if($available && $inStock && !empty($pricing))
                            <div class="flex flex-col gap-1">
                                @if($pricing['has_discount'] ?? false)
                                    <span class="text-[11px] text-gray-400 dark:text-zinc-500 line-through tabular-nums leading-none">
                                        {{ number_format($pricing['before_discount']) }}
                                    </span>
                                @endif
                                <div class="flex items-center gap-1.5">
                                    <span class="text-lg font-black text-gray-900 dark:text-white tracking-tighter tabular-nums">
                                        {{ number_format($pricing['after_discount']) }}
                                    </span>
                                    <span class="text-[10px] text-gray-400 dark:text-zinc-500 font-bold">تومان</span>
                                </div>
                            </div>
                            @if($pricing['has_discount'] ?? false)
                                <span class="px-2.5 py-1 rounded-lg bg-red-500 text-white text-[11px] font-black tabular-nums">
                                    {{ $pricing['discount_percent'] }}٪
                                </span>
                            @endif
                        @else
                            <span class="text-[12px] font-black text-gray-400">
                                {{ $available ? 'ناموجود' : 'این محصول دیگر در دسترس نیست' }}
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if($this->items->hasPages())
            <div class="pt-4">
                {{ $this->items->links() }}
            </div>
        @endif
    @endif
</div>
