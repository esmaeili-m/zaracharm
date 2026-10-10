{{-- دکمه‌های قبلی/بعدی اسلایدر یک‌خطی محصولات (داخل x-data ریل استفاده می‌شود) --}}
<button type="button" aria-label="قبلی" x-on:click="step(-1)" x-show="!atStart" x-transition.opacity
        class="hidden md:flex absolute top-1/2 -translate-y-1/2 -right-3 lg:-right-5 z-20 w-11 h-11 rounded-2xl bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md border border-gray-100 dark:border-white/10 shadow-lg text-gray-700 dark:text-gray-200 items-center justify-center hover:bg-brown-600 hover:text-white transition-colors">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
</button>
<button type="button" aria-label="بعدی" x-on:click="step(1)" x-show="!atEnd" x-transition.opacity
        class="hidden md:flex absolute top-1/2 -translate-y-1/2 -left-3 lg:-left-5 z-20 w-11 h-11 rounded-2xl bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md border border-gray-100 dark:border-white/10 shadow-lg text-gray-700 dark:text-gray-200 items-center justify-center hover:bg-brown-600 hover:text-white transition-colors">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
</button>
