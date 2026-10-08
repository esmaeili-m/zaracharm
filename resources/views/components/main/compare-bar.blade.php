<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use App\Services\Catalog\CompareList;

/*
 * نوار مقایسه محصولات (پایین همه صفحات فروشگاه)
 * - با رویداد compare-updated به‌روز می‌شود
 * - قابل جمع شدن به یک دکمه کوچک تا مزاحم محتوا نباشد (وضعیت در localStorage)
 */
new class extends Component
{
    #[On('compare-updated')]
    public function refresh(): void
    {
        unset($this->products);
    }

    #[Computed]
    public function products()
    {
        return app(CompareList::class)->products(['media']);
    }

    public function remove(int $productId): void
    {
        app(CompareList::class)->remove($productId);
        unset($this->products);
        $this->dispatch('compare-updated');
    }

    public function clear(): void
    {
        app(CompareList::class)->clear();
        unset($this->products);
        $this->dispatch('compare-updated');
    }
};
?>

<div>
    {{-- موقعیت و لایه نوار مستقل از build فایل CSS (بالای منوی پایین موبایل) --}}
    <style>
        .zc-compare-bar { position: fixed; z-index: 60; left: 50%; translate: -50% 0; bottom: 6.75rem; width: 92%; max-width: 400px; }
        @media (min-width: 768px) { .zc-compare-bar { bottom: 1.5rem; width: auto; max-width: 48rem; } }
    </style>

    @if($this->products->isNotEmpty())
        <div x-data="{
                open: (() => { try { return localStorage.getItem('compare-bar') !== 'closed' } catch (e) { return true } })(),
                toggle() { this.open = !this.open; try { localStorage.setItem('compare-bar', this.open ? 'open' : 'closed') } catch (e) {} }
             }"
             class="zc-compare-bar fixed z-[60] left-1/2 -translate-x-1/2 bottom-[6.75rem] md:bottom-6 w-[92%] max-w-[400px] md:w-auto md:max-w-3xl"
             dir="rtl">

            {{-- حالت جمع‌شده --}}
            <button type="button" x-show="!open" x-cloak @click="toggle()"
                    class="mx-auto flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md border border-white/60 dark:border-white/10 shadow-lg text-[11px] font-black text-gray-700 dark:text-gray-200 hover:text-teal-600 transition-colors">
                <svg class="w-4 h-4 text-teal-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"></path><path d="M8 21H3v-5"></path><path d="M21 3l-7 7"></path><path d="M3 21l7-7"></path></svg>
                مقایسه محصولات ({{ $this->products->count() }})
            </button>

            {{-- حالت باز --}}
            <div x-show="open" x-transition.opacity.duration.200ms
                 class="flex flex-col md:flex-row md:items-center gap-3 p-3 rounded-[1.75rem] bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md border border-white/60 dark:border-white/10 shadow-lg">

                <div class="flex items-center justify-between md:justify-start gap-2 md:pl-3 md:border-l border-gray-200/70 dark:border-white/10">
                    <span class="flex items-center gap-2 text-[12px] font-black text-gray-900 dark:text-white whitespace-nowrap">
                        <svg class="w-4 h-4 text-teal-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"></path><path d="M8 21H3v-5"></path><path d="M21 3l-7 7"></path><path d="M3 21l7-7"></path></svg>
                        مقایسه محصولات ({{ $this->products->count() }}/{{ \App\Services\Catalog\CompareList::MAX }})
                    </span>
                    <button type="button" @click="toggle()" title="کوچک کردن"
                            class="md:hidden w-8 h-8 rounded-xl flex items-center justify-center text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                </div>

                {{-- محصولات انتخاب‌شده --}}
                <div class="flex items-center gap-2 overflow-x-auto scrollbar-hide">
                    @foreach($this->products as $product)
                        <div wire:key="compare-bar-{{ $product->id }}"
                             class="group/item relative flex items-center gap-2 shrink-0 pl-2 pr-1 py-1 rounded-2xl bg-gray-100/80 dark:bg-white/5 border border-transparent hover:border-teal-500/30 transition-colors">
                            <a href="{{ route('products.show', $product->slug) }}" class="flex items-center gap-2">
                                <span class="w-9 h-9 rounded-xl overflow-hidden bg-white dark:bg-black/30 flex items-center justify-center">
                                    @if($product->featured_image_url)
                                        <img src="{{ $product->featured_image_url }}" alt="{{ $product->title }}" class="w-full h-full object-contain">
                                    @else
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    @endif
                                </span>
                                <span class="max-w-[90px] md:max-w-[120px] truncate text-[11px] font-bold text-gray-700 dark:text-gray-200">{{ $product->title }}</span>
                            </a>
                            <button type="button" wire:click="remove({{ $product->id }})" title="حذف از مقایسه" aria-label="حذف {{ $product->title }} از مقایسه"
                                    class="w-6 h-6 rounded-lg flex items-center justify-center text-gray-400 hover:bg-red-500 hover:text-white transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center gap-2 md:pr-1 shrink-0">
                    <a href="{{ route('compare.index') }}"
                       class="flex-1 md:flex-none text-center px-5 py-2.5 rounded-xl bg-brown-600 text-white text-[11px] font-black shadow-lg shadow-brown-500/20 hover:bg-brown-700 transition-all active:scale-95 whitespace-nowrap {{ $this->products->count() < 2 ? 'opacity-60' : '' }}"
                       @if($this->products->count() < 2) title="برای مقایسه حداقل دو محصول انتخاب کنید" @endif>
                        مشاهده مقایسه
                    </a>
                    <button type="button" wire:click="clear" wire:confirm="همه محصولات از لیست مقایسه حذف شوند؟"
                            class="px-3 py-2.5 rounded-xl text-[11px] font-black text-gray-500 dark:text-gray-400 hover:text-red-500 hover:bg-red-500/10 transition-colors whitespace-nowrap">
                        حذف همه
                    </button>
                    <button type="button" @click="toggle()" title="کوچک کردن"
                            class="hidden md:flex w-9 h-9 rounded-xl items-center justify-center text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
