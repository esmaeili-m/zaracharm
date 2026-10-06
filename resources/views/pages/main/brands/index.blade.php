<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use App\Models\Brand;

new class extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    #[Computed]
    public function brands()
    {
        return Brand::active()
            ->with('logo')
            ->withCount('activeProducts')
            ->orderBy('sort')
            ->orderBy('title')
            ->paginate(24);
    }
};
?>

<div>
    <section class="relative py-16 transition-colors duration-700" dir="rtl">

        <div class="container">

            <div class="mb-12">

                {{-- Breadcrumb --}}
                <nav class="flex items-center gap-2 text-[10px] font-black text-gray-400 mb-6 bg-white/30 dark:bg-white/[0.02] w-fit px-4 py-2 rounded-full border border-white/40 dark:border-white/5 backdrop-blur-md">
                    <a href="{{ route('home') }}" class="hover:text-brown-500 transition-colors">
                        خانه
                    </a>

                    <svg class="w-3 h-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M15 19l-7-7 7-7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>

                    <span class="text-brown-600 dark:text-brown-400">
                        برندها
                    </span>
                </nav>

                {{-- Title --}}
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 border-r-4 border-brown-600 pr-2 pl-2">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-black text-gray-900 dark:text-white">
                            همه <span class="text-brown-600">برندها</span>
                        </h1>
                        <p class="text-gray-500 dark:text-gray-400 mt-2 font-bold text-sm">
                            محصولات برند موردعلاقه خود را پیدا کنید
                        </p>
                    </div>

                    <span class="w-fit px-4 py-2 rounded-xl bg-white/60 dark:bg-white/5 border border-white dark:border-white/10 text-[11px] font-black text-gray-500 dark:text-gray-300 tabular-nums">
                        {{ number_format($this->brands->total()) }} برند
                    </span>
                </div>
            </div>

            {{-- Brands grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                @forelse($this->brands as $brand)
                    <x-main.brands.card
                        wire:key="brand-{{ $brand->id }}"
                        :brand="$brand"
                        :products-count="$brand->active_products_count"
                    />
                @empty
                    <div class="col-span-full text-center py-20">
                        <h3 class="text-lg font-black text-gray-700 dark:text-gray-200">
                            برندی پیدا نشد
                        </h3>
                        <p class="text-xs text-gray-400 mt-2">
                            هنوز برندی برای نمایش ثبت نشده است.
                        </p>
                    </div>
                @endforelse
            </div>

            <div class="mt-16 flex items-center justify-center">
                {{ $this->brands->onEachSide(1)->links() }}
            </div>

        </div>
    </section>
</div>
