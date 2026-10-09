@php
    /*
     * اسلایدر اصلی (صفحه‌ساز)
     * تنظیمات از data سکشن: height_mode (fixed | max)، height_mobile، height_desktop، autoplay، loop، speed
     *  - fixed: همه اسلایدها دقیقاً همین ارتفاع (تصویر با object-cover برش می‌خورد)
     *  - max:   تصویر کوتاه‌تر اندازه خودش، بلندتر تا همین ارتفاع برش می‌خورد
     * استایل ارتفاع با CSS داخلی (بدون وابستگی به build تیلویند) و Swiper مخصوص همین سکشن ساخته می‌شود
     * تا دکمه‌ها/صفحه‌بندی چند اسلایدر در یک صفحه با هم تداخل نداشته باشند.
     */
    $slides = \App\Models\SliderItem::where('slider_id', $data['slider_id'] ?? null)
        ->with('media')
        ->orderBy('id')
        ->get()
        ->filter(fn ($slide) => $slide->media->first())
        ->values();

    $mode = in_array($data['height_mode'] ?? 'fixed', ['fixed', 'max'], true) ? ($data['height_mode'] ?? 'fixed') : 'fixed';
    $mobile = max(120, min(800, (int) ($data['height_mobile'] ?? 200)));
    $desktop = max(150, min(1000, (int) ($data['height_desktop'] ?? 480)));
    $tablet = (int) round(($mobile + $desktop) / 2);

    $sliderId = 'hero-slider-' . \Illuminate\Support\Str::random(8);
    $options = [
        'loop' => (bool) ($data['loop'] ?? true) && $slides->count() > 1,
        'autoplay' => (bool) ($data['autoplay'] ?? true) && $slides->count() > 1,
        'delay' => max(2000, min(15000, (int) ($data['speed'] ?? 5000))),
    ];
    $property = $mode === 'fixed' ? 'height' : 'max-height';
@endphp

@if($slides->isNotEmpty())
    <style>
        #{{ $sliderId }} .hero-slide-img { {{ $property }}: {{ $mobile }}px; }
        @if($mode === 'fixed') #{{ $sliderId }} .swiper-slide { height: {{ $mobile }}px; } @endif
        @media (min-width: 768px) {
            #{{ $sliderId }} .hero-slide-img { {{ $property }}: {{ $tablet }}px; }
            @if($mode === 'fixed') #{{ $sliderId }} .swiper-slide { height: {{ $tablet }}px; } @endif
        }
        @media (min-width: 1024px) {
            #{{ $sliderId }} .hero-slide-img { {{ $property }}: {{ $desktop }}px; }
            @if($mode === 'fixed') #{{ $sliderId }} .swiper-slide { height: {{ $desktop }}px; } @endif
        }
        #{{ $sliderId }} .swiper-pagination-bullet { width: 8px; height: 8px; background: #fff; opacity: .5; transition: all .3s; }
        #{{ $sliderId }} .swiper-pagination-bullet-active { width: 22px; border-radius: 9999px; opacity: 1; }
        #{{ $sliderId }} .swiper-button-disabled { opacity: .35; pointer-events: none; }
    </style>

    <div id="{{ $sliderId }}" class="relative group overflow-hidden rounded-2xl md:rounded-[2.5rem]">
        <div class="swiper hero-swiper">
            <div class="swiper-wrapper">
                @foreach($slides as $slide)
                    <div class="swiper-slide relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent pointer-events-none"></div>
                        <img src="{{ asset('storage/' . $slide->media->first()->file_path) }}"
                             alt="{{ $slide->title ?? 'Banner' }}"
                             @if(!$loop->first) loading="lazy" @endif
                             class="hero-slide-img block w-full {{ $mode === 'fixed' ? 'h-full' : 'h-auto' }} object-cover object-center">
                    </div>
                @endforeach
            </div>

            @if($slides->count() > 1)
                {{-- دکمه‌ها وسط عمودی دو طرف؛ با هر ارتفاعی سر جای خودشان می‌مانند --}}
                <button type="button" aria-label="اسلاید قبلی"
                        class="hero-prev absolute right-2 md:right-5 top-1/2 -translate-y-1/2 z-10 w-8 h-8 md:w-11 md:h-11 rounded-full bg-white/25 backdrop-blur-md border border-white/40 text-white flex items-center justify-center cursor-pointer hover:bg-white hover:text-brown-600 transition-all md:opacity-0 md:group-hover:opacity-100">
                    <svg class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <button type="button" aria-label="اسلاید بعدی"
                        class="hero-next absolute left-2 md:left-5 top-1/2 -translate-y-1/2 z-10 w-8 h-8 md:w-11 md:h-11 rounded-full bg-white/25 backdrop-blur-md border border-white/40 text-white flex items-center justify-center cursor-pointer hover:bg-white hover:text-brown-600 transition-all md:opacity-0 md:group-hover:opacity-100">
                    <svg class="w-4 h-4 md:w-5 md:h-5 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>

                <div class="hero-pagination absolute bottom-3 md:bottom-5 left-1/2 -translate-x-1/2 z-10 flex items-center gap-1.5"></div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const root = document.getElementById(@js($sliderId));
                const options = @js($options);

                function init() {
                    if (!root || typeof Swiper === 'undefined') return;
                    const el = root.querySelector('.hero-swiper');
                    if (el.swiper) return;

                    new Swiper(el, {
                        rtl: true,
                        loop: options.loop,
                        speed: 800,
                        autoHeight: false,
                        autoplay: options.autoplay ? { delay: options.delay, disableOnInteraction: false, pauseOnMouseEnter: true } : false,
                        navigation: { nextEl: root.querySelector('.hero-next'), prevEl: root.querySelector('.hero-prev') },
                        pagination: { el: root.querySelector('.hero-pagination'), clickable: true },
                        threshold: 5,
                    });
                }

                document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
            })();
        </script>
    @endpush
@endif
