<?php

use Livewire\Component;
use App\Models\Menu;
new class extends Component
{
    public $menu,$categories,$logo;

    public function mount(): void
    {
        $this->menu = Menu::query()
            ->where('location', 'header')
            ->with([
                'items.page',
            ])
            ->first();

        $this->categories = \App\Models\Category::active()->withCount('courser')->take(6)->get();
        $this->logo = \App\Models\Setting::where('key','logo')->with('media')->first();
    }

};
?>

<div>
        <nav class="hidden lg:block bg-white/50 dark:bg-gray-950/50 border-t border-gray-100 dark:border-gray-800">
            <div class="container mx-auto px-8">
                <ul class="flex items-center gap-8 py-3">

                    @foreach($menu?->items?->whereNull('parent_id') ?? [] as $item)

                        @php
                            $dropdownItems = collect();

                            if ($item->children->count()) {
                                $dropdownItems = $item->children;
                            } elseif ($item->page?->slug === 'categories') {
                                $dropdownItems = \App\Models\Category::active()->whereNull('parent_id')->get();
                            } elseif ($item->page?->slug === 'articles') {
                                $dropdownItems = \App\Models\Article::active()->take(6)->get();
                            }
                            $itemLink = $item->link ?? '#';
                            $itemPath = trim(parse_url($itemLink, PHP_URL_PATH) ?? '', '/');
                            $isActive = $itemPath !== '' && (request()->is($itemPath) || request()->is($itemPath . '/*'));
                        @endphp

                        @if($dropdownItems->count())

                            @if($item->view_type === 'mega_tabs')
                                {{-- ========== مگامنوی تب‌دار ========== --}}
                                <li class="group/main static">
                                    <a href="{{ $itemLink }}"
                                       class="flex items-center gap-2 py-4 text-[13px] font-bold text-gray-800 dark:text-gray-200 group-hover/main:text-[var(--color-primary-500)] transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                        </svg>
                                        @if($item->badge == 'special')
                                            <span class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse group-hover:scale-110 transition-transform"></span>

                                        @endif

                                        {{ $item->title }}
                                    </a>

                                    <div class="absolute top-full right-0 left-0 w-full bg-white dark:bg-[var(--color-primary-950)] border-t border-gray-100 dark:border-[var(--color-primary-900)] shadow-lg opacity-0 invisible group-hover/main:opacity-100 group-hover/main:visible transition-all duration-200 z-50 overflow-x-auto overflow-y-auto max-h-[80vh] custom-scrollbar">
                                        <div class="container mx-auto min-w-[900px] flex h-auto">

                                            {{-- سایدبار تب‌ها --}}
                                            <div class="w-64 border-l border-gray-100 dark:border-[var(--color-primary-900)] py-2 bg-gray-50/80 dark:bg-[var(--color-primary-900)]/30 flex-shrink-0">
                                                <ul class="flex flex-col overflow-y-scroll min-h-screen">
                                                    @foreach($dropdownItems as $tab)
                                                        <li class="mega-tab-item {{ $loop->first ? 'active' : '' }} group/tab"
                                                            data-target="mega-tab-{{ $item->id }}-{{ $tab->id }}">
                                                            <a href="{{ ($itemLink.'/'.$tab->slug) ?? '#' }}"
                                                               class="flex items-center gap-3 px-6 py-4 text-[13px] font-bold text-gray-600 dark:text-gray-400 group-[.active]/tab:bg-white dark:group-[.active]/tab:bg-[var(--color-primary-950)] group-[.active]/tab:text-[var(--color-primary-600)] transition-all">
                                                                @if(!empty($tab->icon))
                                                                    <span class="w-5 h-5">{!! $tab->icon !!}</span>
                                                                @endif
                                                                {{ $tab->title }}
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>

                                            {{-- محتوای هر تب --}}
                                            <div class="flex-1 p-8 bg-white dark:bg-[var(--color-primary-950)]">
                                                @foreach($dropdownItems as $tab)
                                                    <div id="mega-tab-{{ $item->id }}-{{ $tab->id }}"
                                                         class="mega-tab-content {{ $loop->first ? '' : 'hidden' }}">

                                                        <div class="flex items-center justify-between mb-8">
                                                            <a href="{{ ($itemLink.'/'.$tab->slug) ?? '#' }}"
                                                               class="flex items-center gap-1 text-[14px] font-black text-gray-900 dark:text-white hover:text-[var(--color-primary-500)]">
                                                                مشاهده تمام محصولات {{ $tab->title }}
                                                                <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-width="3"/></svg>
                                                            </a>
                                                        </div>

                                                        <div class="grid grid-cols-4 gap-x-6 gap-y-10">
                                                            @forelse($tab->children as $column)
                                                                <div class="space-y-4">
                                                                    <a href="{{ ($itemLink.'/'.$column->slug) ?? '#' }}"
                                                                       class="flex items-center gap-2 text-[14px] font-black text-gray-900 dark:text-white border-r-2 border-[var(--color-primary-500)] pr-3">
                                                                        {{ $column->title }}
                                                                    </a>
                                                                    <ul class="space-y-3 pr-4 text-[12.5px] text-gray-500 dark:text-gray-400">
                                                                        @foreach($column->children as $link)
                                                                            <li>
                                                                                <a href="{{ ($itemLink.'/'.$link->slug) ?? '#' }}" class="hover:text-[var(--color-primary-500)]">
                                                                                    {{ $link->title }}
                                                                                </a>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                            @empty
                                                                <p class="col-span-4 text-sm text-gray-400">موردی برای نمایش وجود ندارد.</p>
                                                            @endforelse
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </li>

                            @elseif($item->view_type === 'mega_list')
                                {{-- ========== مگالیست برندها ========== --}}
                                <li class="group/megalist static">
                                    <a href="{{ $itemLink }}"
                                       class="flex items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors py-4">
                                        @if($item->badge == 'special')
                                            <span class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse group-hover:scale-110 transition-transform"></span>

                                        @endif
                                        {{ $item->title }}
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </a>

                                    <div class="absolute top-full right-0 left-0 w-full bg-white dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800 shadow-lg opacity-0 invisible group-hover/megalist:opacity-100 group-hover/megalist:visible transition-all duration-300 z-40 transform translate-y-2 group-hover/megalist:translate-y-0 overflow-x-auto overflow-y-auto max-h-[80vh]">
                                        <div class="container mx-auto px-8 py-10 min-w-[720px]">
                                            <div class="grid grid-cols-3 gap-6">
                                                @foreach($dropdownItems as $group)
                                                    <div class="space-y-4">
                                                        <h4 class="font-black text-sm mb-4 dark:text-white flex items-center gap-2">
                                                            @if(!empty($group->icon))
                                                                <span class="w-4 h-4 text-primary-500">{!! $group->icon !!}</span>
                                                            @endif
                                                            {{ $group->title }}
                                                        </h4>
                                                        <ul class="space-y-3">
                                                            @foreach($group->children as $brand)
                                                                <li>
                                                                    <a href="{{ ($itemLink.'/'.$brand->slug) ?? '#' }}"
                                                                       class="text-xs text-gray-500 hover:text-primary-500 dark:hover:text-primary-400 transition-colors flex items-center gap-2">
                                                                        <span class="w-2 h-2 bg-primary-500 rounded-full"></span>
                                                                        {{ $brand->title }}
                                                                    </a>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between">
                                                <span class="text-xs text-gray-500">{{ $item->title }}</span>
                                                <a href="{{ $itemLink }}"
                                                   class="text-sm font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition-colors flex items-center gap-1">
                                                    مشاهده همه
                                                    <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </li>

                            @else
                                {{-- ========== دراپ‌داون ساده (پیش‌فرض) ========== --}}
                                <li class="relative group/drop">
                                    <button class="flex items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors py-4 w-full lg:w-auto">
                                        {{ $item->title }}
                                        <svg class="w-4 h-4 transition-transform group-hover/drop:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                        @if($item->badge == 'special')
                                            <span class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse group-hover:scale-110 transition-transform"></span>

                                        @endif
                                    </button>

                                    <ul class="lg:absolute lg:top-full lg:right-0 w-full lg:w-64 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-lg lg:rounded-2xl py-2 opacity-0 invisible group-hover/drop:opacity-100 group-hover/drop:visible lg:transform lg:translate-y-2 lg:group-hover/drop:translate-y-0 transition-all duration-300 z-50 hidden lg:block mobile-menu-content">
                                        @foreach($dropdownItems as $sub)
                                            <li class="relative p-2 group/subdrop">
                                                <a href="{{ ($itemLink.'/'.$sub->slug) ?? '#' }}"
                                                   class="flex rounded items-center justify-between px-4 py-3 text-xs text-gray-600 dark:text-gray-300 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:text-primary-600 transition-colors">
                                <span class="flex items-center gap-3">
                                    @if(!empty($sub->icon))
                                        <span class="w-4 h-4">{!! $sub->icon !!}</span>
                                    @endif
                                    {{ $sub->title }}
                                </span>
                                                    @if(method_exists($sub, 'children') && $sub->children->count())
                                                        <svg class="w-3 h-3 lg:block hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                                                        </svg>
                                                    @endif
                                                </a>

                                                @if(method_exists($sub, 'children') && $sub->children->count())
                                                    <ul class="lg:absolute lg:right-full lg:top-0 w-full lg:w-56 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-lg lg:rounded-2xl py-2 opacity-0 invisible group-hover/subdrop:opacity-100 group-hover/subdrop:visible lg:transform lg:translate-x-2 lg:group-hover/subdrop:translate-x-0 transition-all duration-300 hidden lg:block">
                                                        @foreach($sub->children as $subsub)
                                                            <li>
                                                                <a href="{{ ($itemLink.'/'.$subsub->slug) ?? '#' }}" class="block px-4 py-2 text-[11px] hover:text-primary-500 dark:text-gray-400">
                                                                    {{ $subsub->title }}
                                                                </a>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endif
                        @else
                            <li>

                                <a href="{{ $itemLink }}"
                                   class="text-sm font-medium transition-colors
                                   {{ $isActive
                                        ? 'text-primary-600 dark:text-primary-400'
                                        : 'text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400' }}">
                                    @if($item->badge == 'special')
                                        <span class="inline-block mx-1 w-2 h-2 bg-red-500 rounded-full animate-pulse me-2"></span>
                                    @endif

                                    {{ $item->title }}

                                </a>
                            </li>

                        @endif
                    @endforeach






                    <li class="mr-auto flex items-center gap-4">
                        <div class="h-4 w-[1px] bg-gray-200 dark:bg-gray-800"></div>

                        <a href="#" class="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                            <svg class="w-4 h-4 text-secondary-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                            </svg>
                            ارسال به: <span class="text-gray-800 dark:text-gray-200">سراسر کشور</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
</div>
