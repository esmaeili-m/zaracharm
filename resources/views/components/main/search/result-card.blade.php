{{-- کارت نتیجه‌ی جستجو برای دسته‌بندی / تگ / مقاله / صفحه (خروجی GlobalSearchService::present) --}}
@props(['item'])

@php
    $icons = [
        'categories' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
        'tags' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
        'articles' => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z',
        'pages' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    ];
@endphp

<a href="{{ $item['url'] }}"
   {{ $attributes->class('group flex items-center gap-4 p-3 bg-white/70 dark:bg-white/[0.03] backdrop-blur-md rounded-[2rem] border border-gray-200 dark:border-white/10 transition-all duration-500 hover:shadow-lg hover:shadow-brown-600/20 hover:-translate-y-1') }}>

    <span class="relative w-20 h-20 flex-shrink-0 rounded-[1.5rem] overflow-hidden bg-gradient-to-br from-gray-100 to-transparent dark:from-white/5 dark:to-transparent flex items-center justify-center">
        @if($item['image'])
            <img src="{{ $item['image'] }}" alt="" loading="lazy"
                 class="w-full h-full {{ $item['type'] === 'articles' ? 'object-cover' : 'object-contain p-2' }} transition-transform duration-700 group-hover:scale-110">
        @else
            <svg class="w-7 h-7 text-brown-600/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icons[$item['type']] ?? $icons['pages'] }}"/></svg>
        @endif
    </span>

    <span class="flex-1 min-w-0">
        <span class="inline-block mb-1.5 px-2.5 py-0.5 rounded-lg bg-brown-600/10 text-brown-600 dark:text-brown-400 text-[10px] font-black">
            {{ $item['type_label'] }}
        </span>
        <span class="block text-[14px] font-black text-gray-900 dark:text-white leading-7 line-clamp-1 group-hover:text-brown-600 dark:group-hover:text-brown-400 transition-colors">
            @if($item['type'] === 'tags')<span class="opacity-50">#</span>@endif{{ $item['title'] }}
        </span>
        @if($item['subtitle'])
            <span class="block text-[11px] font-bold text-gray-500 dark:text-gray-400 line-clamp-1 mt-0.5">
                {{ $item['subtitle'] }}
            </span>
        @endif
    </span>

    <span class="w-9 h-9 flex-shrink-0 bg-brown-500/10 text-brown-600 rounded-xl flex items-center justify-center transition-all duration-500 group-hover:bg-brown-500 group-hover:text-white group-hover:-translate-x-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="3" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    </span>
</a>
