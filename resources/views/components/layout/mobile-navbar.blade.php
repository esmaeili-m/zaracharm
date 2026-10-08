<?php

use Livewire\Component;
use App\Models\Menu;
use App\Models\Category;
use App\Models\Article;

new class extends Component
{
    public $menu;
    public $categories;

    public function mount(): void
    {
        $this->menu = Menu::query()
            ->where('location', 'header')
            ->with([
                'items.page',
                'items.children.children.children',
            ])
            ->first();

        /*
         * دسته‌بندی‌های اصلی
         *
         * برای موبایل استفاده می‌شوند.
         */
        $this->categories = Category::active()
            ->whereNull('parent_id')
            ->with([
                'children.children.children',
            ])
            ->get();
    }

    /*
     * آیتم‌های مربوط به هر منوی اصلی
     */
    public function getDropdownItems($item)
    {
        if ($item->children->count()) {
            return $item->children;
        }

        if ($item->page?->slug === 'categories') {
            return $this->categories;
        }

        if ($item->page?->slug === 'articles') {
            return Article::active()
                ->take(6)
                ->get();
        }

        return collect();
    }

    /*
     * لینک یک آیتم
     */
    public function itemLink($item): string
    {
        return $item->link ?? '#';
    }

    /*
     * لینک دسته‌بندی
     */
    public function categoryLink($category): string
    {
        return url('/categories/' . $category->slug);
    }
};
?>

<div>

    {{-- ========================================================= --}}
    {{-- ======================= DESKTOP ========================= --}}
    {{-- ========================================================= --}}

    <nav class="hidden lg:block bg-white/50 dark:bg-gray-950/50 border-t border-gray-100 dark:border-gray-800">
        <div class="container mx-auto px-8">
            <ul class="flex items-center gap-8 py-3">

                @foreach($menu?->items?->whereNull('parent_id') ?? [] as $item)

                    @php
                        $dropdownItems = $this->getDropdownItems($item);

                        $itemLink = $this->itemLink($item);

                        $itemPath = trim(
                            parse_url($itemLink, PHP_URL_PATH) ?? '',
                            '/'
                        );

                        $isActive = $itemPath !== ''
                            && (
                                request()->is($itemPath)
                                || request()->is($itemPath . '/*')
                            );
                    @endphp

                    @if($dropdownItems->count())

                        {{-- ================================================= --}}
                        {{-- =================== MEGA TABS =================== --}}
                        {{-- ================================================= --}}

                        @if($item->view_type === 'mega_tabs')

                            <li class="group/main static">

                                <a href="{{ $itemLink }}"
                                   class="flex items-center gap-2 py-4 text-[13px] font-bold text-gray-800 dark:text-gray-200 group-hover/main:text-[var(--color-primary-500)] transition-colors">

                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         fill="none"
                                         viewBox="0 0 24 24"
                                         stroke-width="1.5"
                                         stroke="currentColor"
                                         class="size-5">
                                        <path stroke-linecap="round"
                                              stroke-linejoin="round"
                                              d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
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

                                                        <a href="{{ $itemLink . '/' . $tab->slug }}"
                                                           class="flex items-center gap-3 px-6 py-4 text-[13px] font-bold text-gray-600 dark:text-gray-400 group-[.active]/tab:bg-white dark:group-[.active]/tab:bg-[var(--color-primary-950)] group-[.active]/tab:text-[var(--color-primary-600)] transition-all">

                                                            @if(!empty($tab->icon))
                                                                <span class="w-5 h-5">
                                                                    {!! $tab->icon !!}
                                                                </span>
                                                            @endif

                                                            {{ $tab->title }}

                                                        </a>

                                                    </li>

                                                @endforeach

                                            </ul>

                                        </div>

                                        {{-- محتوای تب --}}
                                        <div class="flex-1 p-8 bg-white dark:bg-[var(--color-primary-950)]">

                                            @foreach($dropdownItems as $tab)

                                                <div id="mega-tab-{{ $item->id }}-{{ $tab->id }}"
                                                     class="mega-tab-content {{ $loop->first ? '' : 'hidden' }}">

                                                    <div class="flex items-center justify-between mb-8">

                                                        <a href="{{ $itemLink . '/' . $tab->slug }}"
                                                           class="flex items-center gap-1 text-[14px] font-black text-gray-900 dark:text-white hover:text-[var(--color-primary-500)]">

                                                            مشاهده تمام محصولات {{ $tab->title }}

                                                            <svg class="w-4 h-4 rotate-180"
                                                                 fill="none"
                                                                 stroke="currentColor"
                                                                 viewBox="0 0 24 24">

                                                                <path d="M9 5l7 7-7 7"
                                                                      stroke-width="3"/>

                                                            </svg>

                                                        </a>

                                                    </div>

                                                    <div class="grid grid-cols-4 gap-x-6 gap-y-10">

                                                        @forelse($tab->children as $column)

                                                            <div class="space-y-4">

                                                                <a href="{{ $itemLink . '/' . $column->slug }}"
                                                                   class="flex items-center gap-2 text-[14px] font-black text-gray-900 dark:text-white border-r-2 border-[var(--color-primary-500)] pr-3">

                                                                    {{ $column->title }}

                                                                </a>

                                                                <ul class="space-y-3 pr-4 text-[12.5px] text-gray-500 dark:text-gray-400">

                                                                    @foreach($column->children as $link)

                                                                        <li>

                                                                            <a href="{{ $itemLink . '/' . $link->slug }}"
                                                                               class="hover:text-[var(--color-primary-500)]">

                                                                                {{ $link->title }}

                                                                            </a>

                                                                        </li>

                                                                    @endforeach

                                                                </ul>

                                                            </div>

                                                        @empty

                                                            <p class="col-span-4 text-sm text-gray-400">
                                                                موردی برای نمایش وجود ندارد.
                                                            </p>

                                                        @endforelse

                                                    </div>

                                                </div>

                                            @endforeach

                                        </div>

                                    </div>

                                </div>

                            </li>


                            {{-- ================================================= --}}
                            {{-- =================== MEGA LIST =================== --}}
                            {{-- ================================================= --}}

                        @elseif($item->view_type === 'mega_list')

                            <li class="group/megalist static">

                                <a href="{{ $itemLink }}"
                                   class="flex items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors py-4">

                                    @if($item->badge == 'special')
                                        <span class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse group-hover:scale-110 transition-transform"></span>
                                    @endif

                                    {{ $item->title }}

                                    <svg class="w-4 h-4"
                                         fill="none"
                                         stroke="currentColor"
                                         viewBox="0 0 24 24">

                                        <path stroke-linecap="round"
                                              stroke-linejoin="round"
                                              stroke-width="2"
                                              d="M19 9l-7 7-7-7"/>

                                    </svg>

                                </a>

                                <div class="absolute top-full right-0 left-0 w-full bg-white dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800 shadow-lg opacity-0 invisible group-hover/megalist:opacity-100 group-hover/megalist:visible transition-all duration-300 z-40 transform translate-y-2 group-hover/megalist:translate-y-0 overflow-x-auto overflow-y-auto max-h-[80vh]">

                                    <div class="container mx-auto px-8 py-10 min-w-[720px]">

                                        <div class="grid grid-cols-3 gap-6">

                                            @foreach($dropdownItems as $group)

                                                <div class="space-y-4">

                                                    <h4 class="font-black text-sm mb-4 dark:text-white flex items-center gap-2">

                                                        @if(!empty($group->icon))
                                                            <span class="w-4 h-4 text-primary-500">
                                                                {!! $group->icon !!}
                                                            </span>
                                                        @endif

                                                        {{ $group->title }}

                                                    </h4>

                                                    <ul class="space-y-3">

                                                        @foreach($group->children as $brand)

                                                            <li>

                                                                <a href="{{ $itemLink . '/' . $brand->slug }}"
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

                                            <span class="text-xs text-gray-500">
                                                {{ $item->title }}
                                            </span>

                                            <a href="{{ $itemLink }}"
                                               class="text-sm font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition-colors flex items-center gap-1">

                                                مشاهده همه

                                                <svg class="w-4 h-4 rotate-180"
                                                     fill="none"
                                                     stroke="currentColor"
                                                     viewBox="0 0 24 24">

                                                    <path stroke-linecap="round"
                                                          stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M10 19l-7-7m0 0l7-7m-7 7h18"/>

                                                </svg>

                                            </a>

                                        </div>

                                    </div>

                                </div>

                            </li>


                            {{-- ================================================= --}}
                            {{-- ================= SIMPLE DROPDOWN ================ --}}
                            {{-- ================================================= --}}

                        @else

                            <li class="relative group/drop">

                                <button class="flex items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors py-4 w-full lg:w-auto">

                                    {{ $item->title }}

                                    <svg class="w-4 h-4 transition-transform group-hover/drop:rotate-180"
                                         fill="none"
                                         stroke="currentColor"
                                         viewBox="0 0 24 24">

                                        <path stroke-linecap="round"
                                              stroke-linejoin="round"
                                              stroke-width="2"
                                              d="M19 9l-7 7-7-7"/>

                                    </svg>

                                    @if($item->badge == 'special')
                                        <span class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse group-hover:scale-110 transition-transform"></span>
                                    @endif

                                </button>

                                <ul class="lg:absolute lg:top-full lg:right-0 w-full lg:w-64 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-lg lg:rounded-2xl py-2 opacity-0 invisible group-hover/drop:opacity-100 group-hover/drop:visible lg:transform lg:translate-y-2 lg:group-hover/drop:translate-y-0 transition-all duration-300 z-50 hidden lg:block mobile-menu-content">

                                    @foreach($dropdownItems as $sub)

                                        <li class="relative p-2 group/subdrop">

                                            <a href="{{ $itemLink . '/' . $sub->slug }}"
                                               class="flex rounded items-center justify-between px-4 py-3 text-xs text-gray-600 dark:text-gray-300 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:text-primary-600 transition-colors">

                                                <span class="flex items-center gap-3">

                                                    @if(!empty($sub->icon))
                                                        <span class="w-4 h-4">
                                                            {!! $sub->icon !!}
                                                        </span>
                                                    @endif

                                                    {{ $sub->title }}

                                                </span>

                                                @if($sub->children->count())

                                                    <svg class="w-3 h-3 lg:block hidden"
                                                         xmlns="http://www.w3.org/2000/svg"
                                                         fill="none"
                                                         viewBox="0 0 24 24"
                                                         stroke-width="1.5"
                                                         stroke="currentColor">

                                                        <path stroke-linecap="round"
                                                              stroke-linejoin="round"
                                                              d="M15.75 19.5 8.25 12l7.5-7.5" />

                                                    </svg>

                                                @endif

                                            </a>

                                            @if($sub->children->count())

                                                <ul class="lg:absolute lg:right-full lg:top-0 w-full lg:w-56 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-lg lg:rounded-2xl py-2 opacity-0 invisible group-hover/subdrop:opacity-100 group-hover/subdrop:visible lg:transform lg:translate-x-2 lg:group-hover/subdrop:translate-x-0 transition-all duration-300 hidden lg:block">

                                                    @foreach($sub->children as $subsub)

                                                        <li>

                                                            <a href="{{ $itemLink . '/' . $subsub->slug }}"
                                                               class="block px-4 py-2 text-[11px] hover:text-primary-500 dark:text-gray-400">

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


                {{-- ارسال --}}
                <li class="mr-auto flex items-center gap-4">

                    <div class="h-4 w-[1px] bg-gray-200 dark:bg-gray-800"></div>

                    <a href="#"
                       class="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">

                        <svg class="w-4 h-4 text-secondary-500"
                             fill="currentColor"
                             viewBox="0 0 20 20">

                            <path fill-rule="evenodd"
                                  d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"
                                  clip-rule="evenodd">
                            </path>

                        </svg>

                        ارسال به:
                        <span class="text-gray-800 dark:text-gray-200">
                            سراسر کشور
                        </span>

                    </a>

                </li>

            </ul>
        </div>
    </nav>


    {{-- ========================================================= --}}
    {{-- ======================== MOBILE ========================== --}}
    {{-- ========================================================= --}}

    <nav class="lg:hidden px-3 pb-20">

        {{-- Product classification --}}
        <div class="flex items-center gap-2 px-3 mb-3">

            <span class="w-1 h-4 bg-brown-600 rounded-full"></span>

            <span class="text-[11px] font-black text-gray-400 uppercase tracking-widest">
                دسته‌بندی کالاها
            </span>

        </div>


        <ul class="space-y-3">

            {{-- ================================================= --}}
            {{-- ================== MOBILE MENU ================== --}}
            {{-- ================================================= --}}

            @foreach($menu?->items?->whereNull('parent_id') ?? [] as $index => $item)

                @php
                    $mobileItems = $this->getDropdownItems($item);

                    /*
                     * رنگ‌ها و آیکون‌های UI فعلی موبایل
                     * فقط برای حفظ ظاهر فعلی هستند.
                     */
                    $mobileColors = [
                        0 => 'brown',
                        1 => 'green',
                        2 => 'pink',
                        3 => 'purple',
                        4 => 'red',
                        5 => 'yellow',
                    ];

                    $mobileColor = $mobileColors[$index] ?? 'brown';

                    $mobileHasChildren = $mobileItems->count() > 0;
                @endphp


                @if($mobileHasChildren)

                    {{-- ================= MAIN CATEGORY ================= --}}

                    <li class="menu-item">

                        <button class="layer-btn w-full flex items-center justify-between p-4 rounded-2xl bg-white/40 dark:bg-white/5 border border-white/60 dark:border-white/10 shadow-sm transition-all hover:bg-white/60">

                            <div class="flex items-center gap-3 text-gray-800 dark:text-gray-200">

                                @if($index === 0)

                                    {{-- Digital --}}
                                    <svg class="w-5 h-5 text-brown-500"
                                         fill="none"
                                         stroke="currentColor"
                                         viewBox="0 0 24 24">

                                        <path d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                              stroke-width="1.5"/>

                                    </svg>

                                @elseif($index === 1)

                                    {{-- Home --}}
                                    <svg class="w-5 h-5 text-green-500"
                                         fill="none"
                                         stroke="currentColor"
                                         viewBox="0 0 24 24">

                                        <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                                              stroke-width="1.5"/>

                                    </svg>

                                @elseif($index === 2)

                                    {{-- Fashion --}}
                                    <svg class="w-5 h-5 text-pink-500"
                                         fill="none"
                                         stroke="currentColor"
                                         viewBox="0 0 24 24">

                                        <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                              stroke-width="1.5"/>

                                    </svg>

                                @else

                                    {{-- Default --}}
                                    <svg class="w-5 h-5 text-brown-500"
                                         fill="none"
                                         stroke="currentColor"
                                         viewBox="0 0 24 24">

                                        <path d="M4 6h16M4 12h16M4 18h16"
                                              stroke-width="1.5"/>

                                    </svg>

                                @endif


                                <span class="font-black text-sm">
                                    {{ $item->title }}
                                </span>

                            </div>


                            <svg class="w-4 h-4 text-gray-400 arrow-icon transition-transform duration-300"
                                 fill="none"
                                 stroke="currentColor"
                                 viewBox="0 0 24 24">

                                <path d="M19 9l-7 7-7-7"
                                      stroke-width="3"/>

                            </svg>

                        </button>


                        {{-- ================= LEVEL 1 ================= --}}

                        <ul class="hidden submenu mt-2 mr-2 space-y-2 border-r-2 border-{{ $mobileColor }}-500/20 pr-2 overflow-hidden transition-all duration-300">

                            @foreach($mobileItems as $child)

                                @php
                                    $childHasChildren = $child->children->count() > 0;
                                @endphp


                                @if($childHasChildren)

                                    <li>

                                        <button class="layer-btn w-full flex items-center justify-between p-3 rounded-xl bg-white/30 dark:bg-white/5 border border-white/40 hover:bg-{{ $mobileColor }}-50/50">

                                            <span class="font-bold text-xs text-gray-700 dark:text-gray-300">
                                                {{ $child->title }}
                                            </span>

                                            <svg class="w-3 h-3 text-gray-400 arrow-icon transition-transform"
                                                 fill="none"
                                                 stroke="currentColor"
                                                 viewBox="0 0 24 24">

                                                <path d="M19 9l-7 7-7-7"
                                                      stroke-width="3"/>

                                            </svg>

                                        </button>


                                        {{-- ================= LEVEL 2 ================= --}}

                                        <ul class="hidden submenu mt-2 mr-2 space-y-2 border-r-2 border-gray-400/20 pr-2">

                                            @foreach($child->children as $child2)

                                                @php
                                                    $child2HasChildren = $child2->children->count() > 0;
                                                @endphp


                                                @if($child2HasChildren)

                                                    <li>

                                                        <button class="layer-btn w-full flex items-center justify-between p-2 rounded-lg bg-white/20 dark:bg-white/5 text-gray-600 dark:text-gray-400">

                                                            <span class="font-bold text-[11px]">
                                                                {{ $child2->title }}
                                                            </span>

                                                            <svg class="w-3 h-3 text-gray-400 arrow-icon"
                                                                 fill="none"
                                                                 stroke="currentColor"
                                                                 viewBox="0 0 24 24">

                                                                <path d="M19 9l-7 7-7-7"
                                                                      stroke-width="3"/>

                                                            </svg>

                                                        </button>


                                                        {{-- ================= LEVEL 3 ================= --}}

                                                        <ul class="hidden submenu mt-1 mr-2 space-y-1 pr-4 bg-gray-50/50 dark:bg-black/20 rounded-lg">

                                                            @foreach($child2->children as $child3)

                                                                <li>

                                                                    <a href="{{ $itemLink . '/' . $child->slug . '/' . $child2->slug . '/' . $child3->slug }}"
                                                                       class="block p-3 text-[10px] text-gray-500 dark:text-gray-400 hover:text-{{ $mobileColor }}-600 transition-colors">

                                                                        {{ $child3->title }}

                                                                    </a>

                                                                </li>

                                                            @endforeach

                                                        </ul>

                                                    </li>

                                                @else

                                                    <li>

                                                        <a href="{{ $itemLink . '/' . $child->slug . '/' . $child2->slug }}"
                                                           class="block p-2 pl-4 text-[10px] text-gray-500 dark:text-gray-400 hover:text-{{ $mobileColor }}-600 transition-colors">

                                                            {{ $child2->title }}

                                                        </a>

                                                    </li>

                                                @endif

                                            @endforeach

                                        </ul>

                                    </li>

                                @else

                                    <li>

                                        <a href="{{ $itemLink . '/' . $child->slug }}"
                                           class="flex items-center justify-between p-3 rounded-xl bg-white/30 dark:bg-white/5 border border-white/40 hover:bg-{{ $mobileColor }}-50/50">

                                            <span class="font-bold text-xs text-gray-700 dark:text-gray-300">
                                                {{ $child->title }}
                                            </span>

                                        </a>

                                    </li>

                                @endif

                            @endforeach

                        </ul>

                    </li>


                @else

                    {{-- ================= SIMPLE MOBILE LINK ================= --}}

                    <li>

                        <a href="{{ $itemLink }}"
                           class="flex items-center gap-3 p-4 rounded-2xl bg-white/40 dark:bg-white/5 border border-white/60 dark:border-white/10 shadow-sm text-gray-800 dark:text-gray-200 font-black text-sm">

                            @if($index === 3)

                                <svg class="w-5 h-5 text-purple-500"
                                     fill="none"
                                     stroke="currentColor"
                                     viewBox="0 0 24 24">

                                    <path d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                          stroke-width="1.5"/>

                                </svg>

                            @elseif($index === 4)

                                <svg class="w-5 h-5 text-red-500"
                                     fill="none"
                                     stroke="currentColor"
                                     viewBox="0 0 24 24">

                                    <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                                          stroke-width="1.5"/>

                                </svg>

                            @elseif($index === 5)

                                <svg class="w-5 h-5 text-yellow-500"
                                     fill="none"
                                     stroke="currentColor"
                                     viewBox="0 0 24 24">

                                    <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                                          stroke-width="1.5"/>

                                </svg>

                            @else

                                <svg class="w-5 h-5 text-brown-500"
                                     fill="none"
                                     stroke="currentColor"
                                     viewBox="0 0 24 24">

                                    <path d="M13 10V3L4 14h7v7l9-11h-7z"
                                          stroke-width="1.5"/>

                                </svg>

                            @endif

                            {{ $item->title }}

                        </a>

                    </li>

                @endif

            @endforeach

        </ul>


        {{-- ================================================= --}}
        {{-- ================= CUSTOMER SERVICE ============== --}}
        {{-- ================================================= --}}

        @php
            $servicePhone = \App\Models\Setting::option('phone');
            $serviceMobile = \App\Models\Setting::option('mobile');
            $serviceEmail = \App\Models\Setting::option('email');
            $serviceHours = \App\Models\Setting::option('work_hours');
            $serviceLinks = [
                ['title' => 'پشتیبانی و تیکت', 'url' => route('user.dashboard', ['tab' => 'tickets']), 'color' => 'text-brown-500',
                 'icon' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
                ['title' => 'پیگیری سفارش', 'url' => route('user.dashboard', ['tab' => 'orders']), 'color' => 'text-purple-500',
                 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
                ['title' => 'سوالات متداول', 'url' => route('page.show', 'faq'), 'color' => 'text-green-500',
                 'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['title' => 'شرایط مرجوعی', 'url' => route('page.show', \App\Support\Sections\ReturnPolicy::PAGE_SLUG), 'color' => 'text-red-500',
                 'icon' => 'M3 10h11M3 14h7m10-8v8a2 2 0 01-2 2h-4.586l-1.707 1.707a1 1 0 01-1.414 0L7.586 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2z'],
            ];
        @endphp

        <div class="flex items-center gap-2 px-3 mt-8 mb-3">
            <span class="w-1 h-4 bg-brown-600 rounded-full"></span>
            <span class="text-[11px] font-black text-gray-400 uppercase tracking-widest">
                خدمات مشتریان
            </span>
        </div>

        <ul class="grid grid-cols-2 gap-2 mb-6">
            @foreach($serviceLinks as $link)
                <li>
                    <a href="{{ $link['url'] }}"
                       class="flex items-center gap-2 p-3 rounded-xl bg-white/40 dark:bg-white/5 border border-white/60 dark:border-white/10 text-gray-700 dark:text-gray-300 text-[11px] font-bold">
                        <svg class="w-4 h-4 shrink-0 {{ $link['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="{{ $link['icon'] }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="truncate">{{ $link['title'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>


        {{-- ================================================= --}}
        {{-- ================= CONTACT INFORMATION ============ --}}
        {{-- ================================================= --}}

        <div class="mt-8 pt-6 border-t border-white/40 dark:border-gray-800 space-y-6">

            @if($servicePhone || $serviceMobile || $serviceEmail || $serviceHours)
                <div class="flex flex-col gap-3 px-3">
                    @foreach(array_filter(['تلفن' => $servicePhone, 'موبایل' => $serviceMobile]) as $label => $number)
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $number) }}"
                           class="flex items-center gap-3 text-xs font-bold text-gray-500 dark:text-gray-400">
                            <svg class="w-5 h-5 p-1 bg-white dark:bg-gray-800 rounded-lg shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" stroke-width="1.5"/>
                            </svg>
                            {{ $label }}: <span dir="ltr">{{ $number }}</span>
                        </a>
                    @endforeach

                    @if($serviceEmail)
                        <a href="mailto:{{ $serviceEmail }}"
                           class="flex items-center gap-3 text-xs font-bold text-gray-500 dark:text-gray-400">
                            <svg class="w-5 h-5 p-1 bg-white dark:bg-gray-800 rounded-lg shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke-width="1.5"/>
                            </svg>
                            ایمیل: <span dir="ltr" class="truncate">{{ $serviceEmail }}</span>
                        </a>
                    @endif

                    @if($serviceHours)
                        <p class="flex items-center gap-3 text-xs font-bold text-gray-500 dark:text-gray-400">
                            <svg class="w-5 h-5 p-1 bg-white dark:bg-gray-800 rounded-lg shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="1.5"/>
                            </svg>
                            {{ $serviceHours }}
                        </p>
                    @endif
                </div>
            @endif

            {{-- شبکه‌های اجتماعی از دیتابیس (پنل > شبکه‌های اجتماعی) --}}
            <x-main.site-socials mode="chips" />

        </div>

    </nav>

</div>
