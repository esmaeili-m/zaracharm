{{--
    شبکه‌های اجتماعی سایت (از جدول social_links، مدیریت در پنل > شبکه‌های اجتماعی)
    mode: chips = آیکون + نام (موبایل) | icons = فقط آیکون | responsive = موبایل چیپ، از md به بالا آیکون
--}}
@props([
    'links' => null,
    'mode' => 'responsive',
    'title' => 'ما را در شبکه‌های اجتماعی دنبال کنید',
])

@php
    $links ??= \App\Models\SocialLink::visible()->get();
@endphp

@if($links->isNotEmpty())
    <div {{ $attributes->class('space-y-4') }}>
        @if($title)
            <p class="text-xs font-black text-gray-500 dark:text-gray-400">{{ $title }}</p>
        @endif

        @if(in_array($mode, ['chips', 'responsive'], true))
            {{-- آیکون + نام، قابل شکستن به چند خط، ناحیه لمس کافی --}}
            <div class="flex flex-wrap gap-2 {{ $mode === 'responsive' ? 'md:hidden' : '' }}">
                @foreach($links as $social)
                    <a href="{{ $social->url }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       aria-label="{{ $social->name }}"
                       class="inline-flex items-center gap-2 min-h-11 ps-2 pe-4 py-1.5 rounded-2xl bg-black/5 dark:bg-white/5 border border-black/5 dark:border-white/10 text-gray-700 dark:text-gray-300 hover:border-brown-600 hover:text-brown-600 active:scale-95 active:bg-brown-600 active:text-white transition-all duration-300">
                        <span class="w-8 h-8 rounded-xl bg-white dark:bg-white/10 flex items-center justify-center shrink-0 overflow-hidden">
                            @if($social->icon_url)
                                <img src="{{ $social->icon_url }}" alt="" loading="lazy" class="w-5 h-5 object-contain">
                            @else
                                <span class="text-[10px] font-black">{{ mb_substr($social->name, 0, 2) }}</span>
                            @endif
                        </span>
                        <span class="text-xs font-bold whitespace-nowrap">{{ $social->name }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        @if(in_array($mode, ['icons', 'responsive'], true))
            {{-- فقط آیکون --}}
            <div class="{{ $mode === 'responsive' ? 'hidden md:flex' : 'flex' }} flex-wrap gap-4">
                @foreach($links as $social)
                    <a href="{{ $social->url }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       title="{{ $social->name }}"
                       aria-label="{{ $social->name }}"
                       class="w-12 h-12 rounded-2xl bg-black/5 dark:bg-white/5 border border-black/5 dark:border-white/10 flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-brown-600 hover:text-white hover:scale-110 transition-all duration-500">
                        @if($social->icon_url)
                            <img src="{{ $social->icon_url }}" alt="{{ $social->name }}" loading="lazy" class="w-5 h-5 object-contain">
                        @else
                            <span class="text-xs font-bold">{{ mb_substr($social->name, 0, 2) }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif
