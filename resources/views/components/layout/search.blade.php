<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use App\Services\Search\GlobalSearchService;

new class extends Component
{
    // desktop: نوار جستجوی Navbar | mobile: داخل مودال جستجوی موبایل
    #[Locked]
    public string $variant = 'desktop';

    public string $q = '';

    public function mount(string $variant = 'desktop')
    {
        $this->variant = $variant === 'mobile' ? 'mobile' : 'desktop';

        // روی صفحه جستجو، عبارت فعلی در Navbar هم دیده شود
        if (request()->routeIs('search')) {
            $this->q = mb_substr((string) request('q', request('search', '')), 0, GlobalSearchService::MAX_LENGTH);
        }
    }

    #[Computed]
    public function results(): array
    {
        $search = app(GlobalSearchService::class);

        // عبارت خالی یا خیلی کوتاه => بدون کوئری
        if (! $search->isSearchable($this->q)) {
            return ['groups' => [], 'has_more' => false];
        }

        return $search->preview($this->q, $this->variant === 'mobile' ? 3 : 4);
    }
};
?>

@php($minLength = \App\Services\Search\GlobalSearchService::MIN_LENGTH)

@if($variant === 'mobile')
    <div class="flex-1 flex flex-col min-h-0">
        <div class="p-5">
            <form action="{{ route('search') }}" method="get" class="relative w-full" role="search">
                <input type="search" id="modal-search-input" name="q"
                       wire:model.live.debounce.400ms="q"
                       autocomplete="off"
                       maxlength="{{ \App\Services\Search\GlobalSearchService::MAX_LENGTH }}"
                       class="w-full bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-2xl py-4 pr-12 pl-4 text-sm font-bold dark:text-white outline-none focus:ring-2 ring-primary-500 transition-all shadow-xl"
                       placeholder="نام محصول، برند، دسته یا مقاله...">
                <div class="absolute inset-y-0 right-4 flex items-center text-primary-500 pointer-events-none">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5"/></svg>
                </div>
            </form>
        </div>

        <div class="flex-1 overflow-y-auto px-5 pb-10 custom-scrollbar">
            @include('components.layout.search-results', ['results' => $this->results, 'target' => 'q'])
        </div>
    </div>
@else
    <div id="search-wrapper" class="hidden md:flex flex-1 max-w-4xl relative group/search mx-auto">

        <form action="{{ route('search') }}" method="get" class="relative w-full z-[10000]" role="search">
            <input type="search" id="main-search-input" name="q"
                   wire:model.live.debounce.400ms="q"
                   autocomplete="off"
                   maxlength="{{ \App\Services\Search\GlobalSearchService::MAX_LENGTH }}"
                   class="w-full bg-gray-200/60 dark:bg-[var(--color-primary-950)]/60 backdrop-blur-md border border-gray-300/30 dark:border-white/5 rounded-2xl py-4 pr-12 pl-40 text-sm font-bold text-right outline-none focus:bg-white dark:focus:bg-[var(--color-primary-950)] focus:ring-4 ring-[var(--color-primary-500)]/40 transition-all placeholder:text-gray-500 shadow-sm [&::-webkit-search-cancel-button]:hidden"
                   placeholder="جستجوی سراسری در محصولات، برندها، مقالات ...">

            <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-gray-500">
                <svg wire:loading.remove wire:target="q" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5"/></svg>
                <span wire:loading wire:target="q" class="w-5 h-5 rounded-full border-2 border-primary-500/20 border-t-primary-500 animate-spin"></span>
            </div>

            <div class="absolute left-2 top-1/2 -translate-y-1/2 flex items-center h-[75%] gap-2">
                <div class="h-full w-px bg-gray-300/40 dark:bg-white/10 ml-1"></div>
                <button type="submit" class="h-full px-4 flex items-center gap-3 rounded-xl transition-all duration-300 group/archive
                           bg-white border border-gray-200 text-gray-700 shadow-sm hover:border-[var(--color-primary-500)]
                           dark:bg-[var(--color-primary-800)]/60 dark:border-white/10 dark:text-gray-200 dark:hover:bg-[var(--color-primary-500)]/10">
                    <div class="flex items-center justify-center w-6 h-6 rounded-lg bg-gray-100 dark:bg-white/10 group-hover/archive:bg-[var(--color-primary-500)] group-hover/archive:text-white transition-all duration-300">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </div>
                    <span class="text-[11px] font-black whitespace-nowrap">همه نتایج</span>
                </button>
            </div>
        </form>

        <div id="mega-search-panel"
             class="absolute top-[30px] left-[-15px] right-[-15px] pt-[65px] bg-white/90 dark:bg-[var(--color-primary-950)]/90 backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] shadow-[0_50px_100px_-20px_rgba(0,0,0,0.6)] opacity-0 invisible translate-y-4 group-focus-within/search:opacity-100 group-focus-within/search:visible group-focus-within/search:translate-y-0 transition-all duration-500 z-[9999]">
            <div class="px-6 pb-6 pt-2 max-h-[70vh] overflow-y-auto custom-scrollbar">
                @include('components.layout.search-results', ['results' => $this->results, 'target' => 'q'])
            </div>
        </div>
    </div>
@endif
