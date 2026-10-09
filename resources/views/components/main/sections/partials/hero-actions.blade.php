{{-- دکمه‌های هیرو؛ متغیرها از سکشن هیرو: $data، $primaryLink، $secondaryLink، $dark (روی تصویر تیره) --}}
@if(($primaryLink && filled($data['primary_text'] ?? null)) || ($secondaryLink && filled($data['secondary_text'] ?? null)))
    <div data-reveal style="--d: 420ms" class="mt-8 flex flex-wrap items-center gap-3">
        @if($primaryLink && filled($data['primary_text'] ?? null))
            <a href="{{ $primaryLink }}"
               class="zc-hero-cta group inline-flex items-center gap-2 px-6 md:px-7 py-3.5 rounded-2xl bg-brown-600 hover:bg-brown-700 text-white text-sm font-black shadow-lg shadow-brown-600/30 transition-all active:scale-95">
                {{ $data['primary_text'] }}
                <svg class="w-4 h-4 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
        @endif

        @if($secondaryLink && filled($data['secondary_text'] ?? null))
            <a href="{{ $secondaryLink }}"
               class="inline-flex items-center gap-2 px-6 md:px-7 py-3.5 rounded-2xl text-sm font-black backdrop-blur-md transition-all active:scale-95
               {{ $dark
                    ? 'bg-white/10 border border-white/30 text-white hover:bg-white hover:text-brown-700'
                    : 'bg-white/70 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-gray-800 dark:text-gray-100 hover:border-brown-600/40 hover:text-brown-600' }}">
                {{ $data['secondary_text'] }}
            </a>
        @endif
    </div>
@endif
