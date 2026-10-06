{{--
    نتایج زنده‌ی جستجوی Navbar (مشترک بین نسخه دسکتاپ و مودال موبایل)
    متغیرها: $q (عبارت)، $results (خروجی GlobalSearchService::preview)، $minLength، $target (wire:target)
--}}
@php
    $groupIcons = [
        'products' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
        'brands' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
        'categories' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
        'tags' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
        'articles' => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z',
        'pages' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    ];
    $term = trim($q);
@endphp

<div class="relative" dir="rtl">

    {{-- Loading --}}
    <div wire:loading.flex wire:target="{{ $target }}" class="items-center gap-3 px-2 py-6 text-[12px] font-bold text-gray-500 dark:text-gray-400">
        <span class="w-5 h-5 rounded-full border-2 border-primary-500/20 border-t-primary-500 animate-spin"></span>
        در حال جستجو...
    </div>

    <div wire:loading.remove wire:target="{{ $target }}">

        @if(mb_strlen($term) < $minLength)
            {{-- Hint --}}
            <div class="flex items-center gap-3 px-2 py-6 text-[12px] font-bold text-gray-500 dark:text-gray-400">
                <span class="p-2 bg-primary-500/10 rounded-xl text-primary-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5"/></svg>
                </span>
                برای جستجو در محصولات، برندها، دسته‌بندی‌ها، مقالات و صفحات حداقل {{ $minLength }} حرف تایپ کنید.
            </div>

        @elseif(empty($results['groups']))
            {{-- Empty state --}}
            <div class="flex flex-col items-center text-center px-4 py-10">
                <span class="w-14 h-14 mb-4 rounded-2xl bg-gray-100 dark:bg-white/5 text-gray-400 flex items-center justify-center">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <p class="text-[13px] font-black text-gray-800 dark:text-gray-100 mb-1">
                    نتیجه‌ای برای «{{ $term }}» پیدا نشد
                </p>
                <p class="text-[11px] font-bold text-gray-400">
                    املای عبارت را بررسی کنید یا عبارت کوتاه‌تر و کلی‌تری را امتحان کنید.
                </p>
            </div>

        @else
            <p class="px-2 mb-4 text-[11px] font-bold text-gray-400">
                جستجو برای: <span class="text-gray-800 dark:text-gray-100 font-black">{{ $term }}</span>
            </p>

            <div class="space-y-6">
                @foreach($results['groups'] as $group)
                    <div wire:key="search-group-{{ $group['type'] }}">

                        <div class="flex items-center justify-between gap-3 px-2 mb-3 pb-2 border-b border-gray-100 dark:border-white/5">
                            <div class="flex items-center gap-2">
                                <span class="p-1.5 bg-primary-500/10 rounded-lg text-primary-500">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $groupIcons[$group['type']] }}"/></svg>
                                </span>
                                <span class="text-[12px] font-black text-gray-800 dark:text-gray-100">{{ $group['label'] }}</span>
                            </div>

                            @if($group['has_more'])
                                <a href="{{ route('search', ['q' => $term, 'type' => $group['type']]) }}" class="text-[10px] font-black text-primary-500 hover:underline">
                                    بیشتر
                                </a>
                            @endif
                        </div>

                        @if(in_array($group['type'], ['tags', 'pages'], true))
                            {{-- تگ‌ها و صفحات: چیپ ساده --}}
                            <div class="flex flex-wrap gap-2 px-2">
                                @foreach($group['items'] as $item)
                                    <a href="{{ $item['url'] }}" wire:key="search-item-{{ $item['id'] }}"
                                       class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 dark:bg-white/5 text-[11px] font-bold text-gray-600 dark:text-gray-300 rounded-full border border-transparent hover:border-primary-500 hover:text-primary-500 transition-all">
                                        @if($group['type'] === 'tags')<span class="opacity-60">#</span>@endif
                                        {{ $item['title'] }}
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="grid grid-cols-1 {{ $group['type'] === 'products' ? 'lg:grid-cols-2' : 'sm:grid-cols-2' }} gap-3">
                                @foreach($group['items'] as $item)
                                    <a href="{{ $item['url'] }}" wire:key="search-item-{{ $item['id'] }}"
                                       class="group/card flex items-center gap-3 p-2 bg-white/40 dark:bg-white/[0.03] border border-gray-200/50 dark:border-white/5 rounded-[1.5rem] hover:bg-white dark:hover:bg-[var(--color-primary-900)] transition-all duration-300 shadow-sm">

                                        <span class="relative w-14 h-14 flex-shrink-0 rounded-[1.1rem] bg-gray-100 dark:bg-[var(--color-primary-800)] overflow-hidden flex items-center justify-center {{ $group['type'] === 'brands' ? 'p-2' : 'p-1' }}">
                                            @if($item['image'])
                                                <img src="{{ $item['image'] }}" alt="" loading="lazy"
                                                     class="w-full h-full {{ $group['type'] === 'articles' ? 'object-cover rounded-[0.9rem]' : 'object-contain' }} group-hover/card:scale-110 transition-transform duration-500">
                                            @else
                                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $groupIcons[$group['type']] }}"/></svg>
                                            @endif
                                        </span>

                                        <span class="flex-1 min-w-0">
                                            <span class="block text-[12px] font-bold text-gray-800 dark:text-gray-100 group-hover/card:text-primary-500 transition-colors line-clamp-1">
                                                {{ $item['title'] }}
                                            </span>

                                            <span class="flex items-center justify-between gap-2 mt-1.5">
                                                <span class="text-[10px] font-bold text-gray-400 line-clamp-1">
                                                    {{ $item['type_label'] }}@if($item['subtitle']) · {{ $item['subtitle'] }}@endif
                                                </span>

                                                @if($item['price'])
                                                    <span class="flex-shrink-0 px-2.5 py-0.5 bg-gray-100 dark:bg-white/5 rounded-lg text-[12px] font-black text-gray-900 dark:text-white tabular-nums">
                                                        {{ number_format($item['price']) }} <span class="text-[9px] text-gray-400 font-bold">تومان</span>
                                                    </span>
                                                @endif
                                            </span>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($results['has_more'])
                <a href="{{ route('search', ['q' => $term]) }}"
                   class="mt-6 flex items-center justify-center gap-2 w-full py-3.5 rounded-2xl bg-primary-500 text-white text-[12px] font-black shadow-lg shadow-primary-500/20 hover:bg-primary-600 transition-all active:scale-[0.98]">
                    مشاهده همه نتایج
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @endif
        @endif
    </div>
</div>
