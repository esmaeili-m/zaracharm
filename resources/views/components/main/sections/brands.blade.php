<?php

use Livewire\Component;
use App\Models\Brand;
use App\Enums\PictureMode;

new class extends Component
{
    public $data;
    public $brands;
    public string $pictureMode = 'transparent';

    public function mount($data)
    {
        $this->data = $data;
        $this->brands = collect();

        if (!$data) {
            return;
        }

        $this->pictureMode = PictureMode::forSection('brands', $data)->value;

        $limit = (int) ($data['limit'] ?? 6);

        $query = Brand::active()
            ->with('logo')
            ->withCount('activeProducts');

        $this->brands = match ($data['mode'] ?? 'popular') {

            'latest' => $query->latest()->take($limit)->get(),

            'manual' => $query->whereIn('id', $data['brand_ids'] ?? [])
                ->get()
                // حفظ ترتیب انتخاب مدیر
                ->sortBy(fn ($brand) => array_search($brand->id, array_map('intval', $data['brand_ids'] ?? [])))
                ->values(),

            'all' => $query->orderBy('sort')->orderBy('title')->get(),

            // popular: بیشترین تعداد محصول فعال
            default => $query->orderByDesc('active_products_count')->orderBy('sort')->take($limit)->get(),
        };
    }
};
?>

<div>
    <section class="relative transition-colors duration-500 overflow-hidden" dir="rtl">

        <div class="lg:container mx-auto relative z-10">

            <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-6 border-r-4 border-brown-600 pr-2 pl-2">
                <div>
                    <h2 class="text-3xl lg:text-4xl font-black text-gray-900 dark:text-white">
                        @if(filled($data['title'] ?? null))
                            {{ $data['title'] }}
                        @else
                            برندهای <span class="text-brown-600">محبوب</span>
                        @endif
                    </h2>
                    <p class="text-gray-500 dark:text-gray-400 mt-2 font-bold text-sm">معتبرترین برندها در یک نگاه</p>
                </div>

                <a href="{{ route('brands.list') }}" class="group/link relative overflow-hidden w-fit px-8 py-3.5 rounded-2xl transition-all duration-500 flex items-center gap-3 bg-white/40 backdrop-blur-md border border-gray-200 text-gray-800 hover:border-brown-500/50 hover:text-white dark:bg-white/[0.03] dark:border-white/10 dark:text-gray-300 dark:hover:text-white">
                    <span class="absolute inset-0 bg-brown-600 translate-y-full group-hover/link:translate-y-0 transition-transform duration-500 ease-out"></span>
                    <span class="relative z-10 text-[13px] font-black tracking-tight">مشاهده همه برندها</span>
                    <div class="relative z-10 w-5 h-5 flex items-center justify-center bg-brown-600/10 dark:bg-white/5 rounded-lg group-hover/link:bg-white/20 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </div>
                </a>
            </div>

            <div class="grid pb-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($brands as $brand)
                    <x-main.brands.card
                        wire:key="section-brand-{{ $brand->id }}"
                        :brand="$brand"
                        :products-count="$brand->active_products_count"
                        :picture-mode="$pictureMode"
                    />
                @endforeach
            </div>

        </div>
    </section>
</div>
