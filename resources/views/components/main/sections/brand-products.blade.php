<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use App\Models\Brand;
use App\Enums\PictureMode;

new class extends Component
{
    use \App\Traits\HandlesWishlist;
    use \App\Traits\QueriesSectionProducts;

    public $data;
    public string $pictureMode = 'background';

    // برندهای تب‌های بالای سکشن
    #[Locked]
    public array $brandIds = [];

    // null = همه برندها
    public ?int $activeBrandId = null;

    public function mount($data)
    {
        $this->data = $data;

        if (!$data) {
            return;
        }

        $this->pictureMode = PictureMode::forSection('brandProducts', $data)->value;

        // برندهای انتخابی مدیر، یا همه برندهای فعالی که محصول فعال دارند
        $this->brandIds = Brand::active()
            ->when(!empty($data['brand_ids']), fn ($q) => $q->whereIn('id', $data['brand_ids']))
            ->whereHas('activeProducts')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->loadWishlistIds($this->products->pluck('id'));
    }

    #[Computed]
    public function brands()
    {
        $order = array_map('intval', $this->data['brand_ids'] ?? []);

        return Brand::whereIn('id', $this->brandIds)
            ->with('logo')
            ->orderBy('sort')
            ->orderBy('title')
            ->get()
            // اگر مدیر برندها را دستی انتخاب کرده، همان ترتیب حفظ شود
            ->when(!empty($order), fn ($c) => $c->sortBy(fn ($b) => array_search($b->id, $order))->values());
    }

    // فقط محصولات فعالِ برندهای همین سکشن (محصولات بدون برند نمایش داده نمی‌شوند)
    protected function scopeSectionProducts($query)
    {
        $ids = $this->activeBrandId ? [$this->activeBrandId] : $this->brandIds;

        return $query
            ->active()
            ->whereNotNull('products.brand_id')
            ->whereIn('products.brand_id', $ids ?: [0]);
    }

    #[Computed]
    public function products()
    {
        return $this->querySectionProducts(
            $this->data['mode'] ?? 'latest',
            (int) ($this->data['limit'] ?? 8)
        );
    }

    public function selectBrand(?int $brandId = null): void
    {
        if ($brandId !== null && !in_array($brandId, $this->brandIds, true)) {
            return;
        }

        $this->activeBrandId = $brandId;

        unset($this->products);
        $this->loadWishlistIds($this->products->pluck('id'));
    }
};
?>

<div>
    @if($data)
        <section class="relative transition-colors duration-500 overflow-hidden" dir="rtl">

            <div class="lg:container mx-auto relative z-10">

                <div class="flex flex-col lg:flex-row lg:items-end justify-between mb-8 gap-6 border-r-4 border-brown-600 pr-2 pl-2">
                    <div class="flex-shrink-0">
                        <h2 class="text-3xl lg:text-4xl font-black text-gray-900 dark:text-white">
                            @if(filled($data['title'] ?? null))
                                {{ $data['title'] }}
                            @else
                                محصولات <span class="text-brown-600">برترین برندها</span>
                            @endif
                        </h2>
                        <p class="text-gray-500 dark:text-gray-400 mt-2 font-bold text-sm">برند موردنظر خود را انتخاب کنید</p>
                    </div>

                    {{-- Brand tabs: اسکرول افقی با دکمه‌های جابه‌جایی برای تعداد زیاد برند --}}
                    @if($this->brands->isNotEmpty())
                        <div
                            x-data="{
                                canPrev: false,
                                canNext: false,
                                update() {
                                    const el = this.$refs.strip;
                                    const max = el.scrollWidth - el.clientWidth;
                                    const pos = Math.abs(el.scrollLeft);
                                    this.canPrev = pos > 4;
                                    this.canNext = pos < max - 4;
                                },
                                scroll(dir) { this.$refs.strip.scrollBy({ left: dir * 240, behavior: 'smooth' }); }
                            }"
                            x-init="$nextTick(() => update())"
                            @resize.window.debounce.150ms="update()"
                            class="relative min-w-0 w-full lg:max-w-[65%]"
                        >
                            <div class="flex items-center gap-2 p-1.5 bg-white/60 dark:bg-white/5 backdrop-blur-md rounded-2xl border border-white dark:border-white/10 shadow-sm">

                                <button type="button" x-show="canPrev" x-cloak @click="scroll(1)"
                                        class="flex-shrink-0 w-8 h-8 rounded-xl flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-white/70 dark:hover:bg-white/10 transition-all"
                                        aria-label="برندهای قبلی">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="3" d="M9 5l7 7-7 7"/></svg>
                                </button>

                                <div x-ref="strip" @scroll.debounce.50ms="update()"
                                     class="flex items-center gap-2 overflow-x-auto no-scrollbar scroll-smooth min-w-0 flex-1">

                                    <button
                                        type="button"
                                        wire:click="selectBrand(null)"
                                        class="flex-shrink-0 px-6 py-2.5 rounded-xl font-black text-xs whitespace-nowrap transition-all duration-300 {{ is_null($activeBrandId) ? 'bg-brown-600 text-white shadow-lg shadow-brown-600/20' : 'text-gray-500 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/10' }}">
                                        همه
                                    </button>

                                    @foreach($this->brands as $brand)
                                        <button
                                            type="button"
                                            wire:key="brand-tab-{{ $brand->id }}"
                                            wire:click="selectBrand({{ $brand->id }})"
                                            class="flex-shrink-0 flex items-center gap-2 px-4 py-2 rounded-xl font-black text-xs whitespace-nowrap transition-all duration-300 {{ $activeBrandId === $brand->id ? 'bg-brown-600 text-white shadow-lg shadow-brown-600/20' : 'text-gray-500 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/10' }}">
                                            @if($brand->logo_url)
                                                <span class="w-6 h-6 rounded-lg bg-white flex items-center justify-center p-0.5 flex-shrink-0">
                                                    <img src="{{ $brand->logo_url }}" alt="" class="max-w-full max-h-full object-contain">
                                                </span>
                                            @endif
                                            {{ $brand->title }}
                                        </button>
                                    @endforeach
                                </div>

                                <button type="button" x-show="canNext" x-cloak @click="scroll(-1)"
                                        class="flex-shrink-0 w-8 h-8 rounded-xl flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-white/70 dark:hover:bg-white/10 transition-all"
                                        aria-label="برندهای بعدی">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="3" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="relative">
                    {{-- Loading --}}
                    <div wire:loading.flex wire:target="selectBrand" class="absolute inset-0 z-30 items-start justify-center pt-24">
                        <span class="w-10 h-10 rounded-full border-4 border-brown-600/20 border-t-brown-600 animate-spin"></span>
                    </div>

                    <div wire:loading.class="opacity-40 pointer-events-none" wire:target="selectBrand"
                         class="grid pb-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8 transition-opacity duration-300">
                        @forelse($this->products as $product)
                            <x-main.products.card
                                wire:key="brand-section-product-{{ $product->id }}"
                                :product="$product"
                                :picture-mode="$pictureMode"
                                :wishlisted="$this->isWishlisted($product->id)"
                            />
                        @empty
                            <div class="col-span-full text-center py-16">
                                <h3 class="text-lg font-black text-gray-700 dark:text-gray-200">محصولی پیدا نشد</h3>
                                <p class="text-xs text-gray-400 mt-2">برای این برند هنوز محصولی ثبت نشده است.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                @if($activeBrandId)
                    @php($activeBrand = $this->brands->firstWhere('id', $activeBrandId))
                    @if($activeBrand)
                        <div class="flex justify-center pt-4">
                            <a href="{{ route('brands.show', $activeBrand->slug) }}" class="px-8 py-3.5 rounded-2xl bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-gray-200 dark:border-white/10 text-[13px] font-black text-gray-800 dark:text-gray-300 hover:border-brown-500/50 hover:text-brown-600 transition-all">
                                مشاهده همه محصولات {{ $activeBrand->title }}
                            </a>
                        </div>
                    @endif
                @endif

            </div>
        </section>
    @endif
</div>
