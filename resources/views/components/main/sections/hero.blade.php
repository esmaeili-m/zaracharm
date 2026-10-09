<?php

use Livewire\Component;

/**
 * سکشن هیرو (صفحه‌ساز)
 *
 * data: style (split | overlay)، media_position (start | end)، eyebrow، heading (عنوان؛ title توسط صفحه‌ساز بازنویسی می‌شود)، highlight، description،
 *       primary_text/link، secondary_text/link، video_url، video_on_mobile، overlay_opacity،
 *       height_mobile، height_desktop، animate، badge_title، badge_text، stat{1..3}_value / _label
 * media (collection): hero_media (تصویر یا ویدیو)، hero_poster (پوستر ویدیو / تصویر جایگزین موبایل)
 */
new class extends Component
{
    public array $data = [];
    public ?string $mediaUrl = null;
    public bool $isVideo = false;
    public ?string $posterUrl = null;
    public string $uid;

    public function mount($data = [], $media = null)
    {
        $this->data = is_array($data) ? $data : [];
        $this->uid = 'zc-hero-' . \Illuminate\Support\Str::random(6);

        $media = collect($media ?? []);
        $main = $media->firstWhere('collection', 'hero_media');
        $poster = $media->firstWhere('collection', 'hero_poster');

        $this->posterUrl = $poster ? url('/storage/' . $poster->file_path) : null;

        $videoUrl = trim((string) ($this->data['video_url'] ?? ''));

        if ($videoUrl !== '' && preg_match('#^https?://#i', $videoUrl)) {
            // لینک ویدیوی خارجی (CDN) بر فایل آپلودی مقدم است
            $this->mediaUrl = $videoUrl;
            $this->isVideo = true;
        } elseif ($main) {
            $this->mediaUrl = $main->external_url ?: url('/storage/' . $main->file_path);
            $this->isVideo = $main->type === 'video'
                || str_starts_with((string) $main->mime_type, 'video/')
                || in_array(strtolower((string) $main->extension), ['mp4', 'webm', 'mov'], true);
        }
    }

    /** لینک امن: فقط http(s)، مسیر داخلی یا لنگر */
    public function safeLink(?string $link): ?string
    {
        $link = trim((string) $link);

        if ($link === '') {
            return null;
        }

        return preg_match('#^(https?://|/|\#)#i', $link) ? $link : '/' . ltrim($link, '/');
    }

    public function stats(): array
    {
        return collect([1, 2, 3])
            ->map(fn ($i) => [
                'value' => trim((string) ($this->data["stat{$i}_value"] ?? '')),
                'label' => trim((string) ($this->data["stat{$i}_label"] ?? '')),
            ])
            ->filter(fn ($s) => $s['value'] !== '')
            ->values()
            ->all();
    }

    /** عنوان با بخش برجسته (گرادیان متحرک)؛ ورودی escape می‌شود */
    public function titleHtml(): string
    {
        $title = trim((string) ($this->data['heading'] ?? ''));
        $highlight = trim((string) ($this->data['highlight'] ?? ''));

        $wrap = fn ($text) => '<span class="zc-hero-gradient">' . e($text) . '</span>';

        if ($title === '') {
            return $highlight !== '' ? $wrap($highlight) : '';
        }

        if ($highlight !== '' && mb_strpos($title, $highlight) !== false) {
            $pos = mb_strpos($title, $highlight);

            return e(mb_substr($title, 0, $pos)) . $wrap($highlight) . e(mb_substr($title, $pos + mb_strlen($highlight)));
        }

        return e($title) . ($highlight !== '' ? '<br>' . $wrap($highlight) : '');
    }
};
?>

@php
    $style = in_array($data['style'] ?? 'split', ['split', 'overlay'], true) ? ($data['style'] ?? 'split') : 'split';
    $mediaStart = ($data['media_position'] ?? 'end') === 'start';
    $animate = (bool) ($data['animate'] ?? true);
    $videoOnMobile = (bool) ($data['video_on_mobile'] ?? true);
    $overlay = max(0, min(90, (int) ($data['overlay_opacity'] ?? 45))) / 100;
    // ارتفاع: در overlay ارتفاع کل سکشن، در split ارتفاع قاب تصویر/ویدیو
    $hMobile = max(240, min(1000, (int) ($data['height_mobile'] ?? 0) ?: ($style === 'overlay' ? 560 : 320)));
    $hDesktop = max(320, min(1200, (int) ($data['height_desktop'] ?? 0) ?: ($style === 'overlay' ? 680 : 520)));
    $statCols = [1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-3'];
    $stats = $this->stats();
    $primaryLink = $this->safeLink($data['primary_link'] ?? null);
    $secondaryLink = $this->safeLink($data['secondary_link'] ?? null);
    $titleHtml = $this->titleHtml();
    // در موبایل بدون ویدیو: پوستر (اگر هست) جای ویدیو
    $mobilePosterOnly = $isVideo && ! $videoOnMobile && $posterUrl;
@endphp

<div>
    <style>
        #{{ $uid }} { --zc-hero-h: {{ $hMobile }}px; }
        @media (min-width: 1024px) { #{{ $uid }} { --zc-hero-h: {{ $hDesktop }}px; } }

        /* ---------- ورود مرحله‌ای ---------- */
        #{{ $uid }} [data-reveal] {
            opacity: 0;
            transform: translate3d(0, 28px, 0);
            filter: blur(6px);
            transition: opacity .9s cubic-bezier(.2,.7,.2,1), transform .9s cubic-bezier(.2,.7,.2,1), filter .9s ease;
            transition-delay: var(--d, 0ms);
        }
        #{{ $uid }}.is-in [data-reveal] { opacity: 1; transform: none; filter: none; }

        /* ---------- قاب رسانه: باز شدن با clip-path + زوم آرام ---------- */
        #{{ $uid }} .zc-hero-frame { clip-path: inset(12% 12% 12% 12% round 2.5rem); transition: clip-path 1.4s cubic-bezier(.7,0,.2,1) .15s; }
        #{{ $uid }}.is-in .zc-hero-frame { clip-path: inset(0 0 0 0 round 2.5rem); }
        #{{ $uid }} .zc-hero-media { transform: scale(1.18); transition: transform 1.8s cubic-bezier(.2,.7,.2,1) .15s; }
        #{{ $uid }}.is-in .zc-hero-media { transform: scale(1.04); animation: zcKenBurns 22s ease-in-out 2s infinite alternate; }
        @keyframes zcKenBurns { from { transform: scale(1.04) translate3d(0,0,0); } to { transform: scale(1.14) translate3d(-1.5%, -1%, 0); } }

        /* ---------- متن گرادیان متحرک ---------- */
        #{{ $uid }} .zc-hero-gradient {
            background: linear-gradient(110deg, var(--color-brown-600, #8b5a2b) 0%, var(--color-brown-400, #c08a5b) 35%, #d4a464 55%, var(--color-brown-600, #8b5a2b) 100%);
            background-size: 220% 100%;
            -webkit-background-clip: text; background-clip: text; color: transparent;
            animation: zcShine 6s linear infinite;
        }
        @keyframes zcShine { to { background-position: -220% 0; } }

        /* ---------- لکه‌های رنگی شناور ---------- */
        #{{ $uid }} .zc-hero-blob { position: absolute; border-radius: 9999px; filter: blur(60px); opacity: .45; pointer-events: none; animation: zcFloat 14s ease-in-out infinite; }
        #{{ $uid }} .zc-hero-blob.b2 { animation-duration: 18s; animation-delay: -6s; }
        #{{ $uid }} .zc-hero-blob.b3 { animation-duration: 22s; animation-delay: -11s; }
        @keyframes zcFloat {
            0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
            33% { transform: translate3d(30px, -25px, 0) scale(1.08); }
            66% { transform: translate3d(-20px, 20px, 0) scale(.95); }
        }

        /* ---------- کارت‌های شناور ---------- */
        #{{ $uid }} .zc-hero-bob { animation: zcBob 5.5s ease-in-out infinite; }
        #{{ $uid }} .zc-hero-bob.alt { animation-duration: 6.5s; animation-delay: -2s; }
        @keyframes zcBob { 0%, 100% { transform: translate3d(0, 0, 0); } 50% { transform: translate3d(0, -10px, 0); } }

        /* ---------- پارالاکس موس (دسکتاپ) ---------- */
        #{{ $uid }} .zc-hero-parallax { transform: translate3d(calc(var(--px, 0) * var(--depth, 10px)), calc(var(--py, 0) * var(--depth, 10px)), 0); transition: transform .4s ease-out; }

        /* ---------- دکمه اصلی: درخشش ---------- */
        #{{ $uid }} .zc-hero-cta { position: relative; overflow: hidden; }
        #{{ $uid }} .zc-hero-cta::after { content: ''; position: absolute; inset: 0; background: linear-gradient(120deg, transparent 30%, rgba(255,255,255,.35) 50%, transparent 70%); transform: translateX(-120%); animation: zcSweep 3.8s ease-in-out 1.6s infinite; }
        @keyframes zcSweep { 0% { transform: translateX(-120%); } 35%, 100% { transform: translateX(120%); } }

        /* ---------- نشانگر اسکرول ---------- */
        #{{ $uid }} .zc-hero-scroll span { animation: zcScroll 1.8s ease-in-out infinite; }
        @keyframes zcScroll { 0% { transform: translateY(0); opacity: 1; } 70% { transform: translateY(12px); opacity: 0; } 100% { opacity: 0; } }

        #{{ $uid }}.zc-hero-overlay-h { min-height: var(--zc-hero-h); }
        #{{ $uid }} .zc-hero-split-media { height: min(var(--zc-hero-h), 78vh); }

        /* ---------- بدون انیمیشن (تنظیمات سکشن یا prefers-reduced-motion) ---------- */
        #{{ $uid }}.no-anim [data-reveal], #{{ $uid }}.no-anim .zc-hero-frame, #{{ $uid }}.no-anim .zc-hero-media { opacity: 1; transform: none; filter: none; clip-path: inset(0 0 0 0 round 2.5rem); transition: none; animation: none; }
        #{{ $uid }}.no-anim .zc-hero-blob, #{{ $uid }}.no-anim .zc-hero-bob, #{{ $uid }}.no-anim .zc-hero-gradient, #{{ $uid }}.no-anim .zc-hero-cta::after, #{{ $uid }}.no-anim .zc-hero-scroll span { animation: none; }
        @media (prefers-reduced-motion: reduce) {
            #{{ $uid }} [data-reveal], #{{ $uid }} .zc-hero-frame, #{{ $uid }} .zc-hero-media { opacity: 1 !important; transform: none !important; filter: none !important; clip-path: inset(0 0 0 0 round 2.5rem) !important; transition: none !important; animation: none !important; }
            #{{ $uid }} .zc-hero-blob, #{{ $uid }} .zc-hero-bob, #{{ $uid }} .zc-hero-gradient, #{{ $uid }} .zc-hero-cta::after, #{{ $uid }} .zc-hero-scroll span, #{{ $uid }} .zc-hero-parallax { animation: none !important; transform: none !important; }
        }
        @media (max-width: 1023px) { #{{ $uid }} .zc-hero-parallax { transform: none; } }
    </style>

    {{-- ================= رسانه (مشترک دو حالت) ================= --}}
    @php
        $mediaMarkup = function (string $classes) use ($isVideo, $mediaUrl, $posterUrl, $mobilePosterOnly, $data) {
            if (! $mediaUrl) {
                return '';
            }

            $alt = e($data['heading'] ?? 'hero');

            if (! $isVideo) {
                return '<img src="' . e($mediaUrl) . '" alt="' . $alt . '" class="zc-hero-media ' . $classes . '" fetchpriority="high" decoding="async">';
            }

            $poster = $posterUrl ? ' poster="' . e($posterUrl) . '"' : '';
            $video = '<video class="zc-hero-media zc-hero-video ' . $classes . ($mobilePosterOnly ? ' hidden lg:block' : '') . '" data-src="' . e($mediaUrl) . '"' . $poster
                . ' muted loop playsinline preload="none" aria-hidden="true"></video>';

            if ($mobilePosterOnly) {
                $video .= '<img src="' . e($posterUrl) . '" alt="' . $alt . '" class="zc-hero-media lg:hidden ' . $classes . '">';
            }

            return $video;
        };
    @endphp

    @if($style === 'overlay')
        {{-- ======================================================= --}}
        {{-- ================= حالت تمام‌صفحه (overlay) ============== --}}
        {{-- ======================================================= --}}
        <section id="{{ $uid }}" dir="rtl" data-zc-hero data-animate="{{ $animate ? 1 : 0 }}"
                 class="relative isolate overflow-hidden rounded-[1.75rem] md:rounded-[2.5rem] zc-hero-overlay-h flex items-end md:items-center bg-gray-900 {{ $animate ? '' : 'no-anim' }}">

            <div class="zc-hero-frame absolute inset-0 -z-10 overflow-hidden">
                {!! $mediaMarkup('absolute inset-0 w-full h-full object-cover') !!}
                @unless($mediaUrl)
                    <div class="absolute inset-0 bg-gradient-to-br from-brown-900 via-brown-700 to-gray-900"></div>
                @endunless
            </div>

            {{-- لایه تیره برای خوانایی متن --}}
            <div class="absolute inset-0 -z-10" style="background: linear-gradient(to top, rgba(0,0,0,{{ min(0.95, $overlay + 0.25) }}) 0%, rgba(0,0,0,{{ $overlay }}) 45%, rgba(0,0,0,{{ max(0, $overlay - 0.2) }}) 100%);"></div>
            <div class="zc-hero-blob b1 -z-10 w-72 h-72 bg-brown-500 -top-16 -right-10"></div>
            <div class="zc-hero-blob b2 -z-10 w-80 h-80 bg-amber-500 bottom-0 -left-20 opacity-30"></div>

            <div class="relative w-full px-5 md:px-12 lg:px-20 py-12 md:py-20">
                <div class="max-w-2xl text-white">
                    @if(filled($data['eyebrow'] ?? null))
                        <div data-reveal style="--d: 80ms" class="inline-flex items-center gap-2 px-4 py-1.5 mb-5 rounded-full bg-white/10 border border-white/20 backdrop-blur-md text-[11px] md:text-xs font-black">
                            <span class="relative flex w-2 h-2"><span class="absolute inline-flex w-full h-full rounded-full bg-amber-300 opacity-75 animate-ping"></span><span class="relative inline-flex w-2 h-2 rounded-full bg-amber-300"></span></span>
                            {{ $data['eyebrow'] }}
                        </div>
                    @endif

                    @if($titleHtml)
                        <h1 data-reveal style="--d: 180ms" class="text-3xl md:text-5xl lg:text-6xl font-black leading-[1.25] md:leading-[1.2] tracking-tight drop-shadow-sm">{!! $titleHtml !!}</h1>
                    @endif

                    @if(filled($data['description'] ?? null))
                        <p data-reveal style="--d: 300ms" class="mt-5 text-sm md:text-base leading-8 text-white/80 font-medium max-w-xl">{{ $data['description'] }}</p>
                    @endif

                    @include('components.main.sections.partials.hero-actions', ['dark' => true])

                    @if($stats)
                        <div data-reveal style="--d: 560ms" class="mt-10 inline-grid {{ $statCols[count($stats)] ?? 'grid-cols-3' }} gap-px rounded-2xl overflow-hidden bg-white/15 border border-white/15 backdrop-blur-md">
                            @foreach($stats as $stat)
                                <div class="px-5 md:px-7 py-4 bg-black/10">
                                    <div class="text-xl md:text-2xl font-black tabular-nums" data-count="{{ $stat['value'] }}">{{ $stat['value'] }}</div>
                                    @if($stat['label'])<div class="text-[10px] md:text-[11px] font-bold text-white/70 mt-1">{{ $stat['label'] }}</div>@endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if($isVideo)
                @include('components.main.sections.partials.hero-video-toggle', ['position' => 'top-4 left-4 md:top-6 md:left-6'])
            @endif

            <div class="zc-hero-scroll hidden md:flex absolute bottom-6 left-1/2 -translate-x-1/2 w-6 h-10 rounded-full border-2 border-white/40 justify-center pt-2" aria-hidden="true">
                <span class="block w-1 h-2 rounded-full bg-white/80"></span>
            </div>
        </section>
    @else
        {{-- ======================================================= --}}
        {{-- ================= حالت دوستونه (split) ================== --}}
        {{-- ======================================================= --}}
        <section id="{{ $uid }}" dir="rtl" data-zc-hero data-animate="{{ $animate ? 1 : 0 }}"
                 class="relative isolate overflow-hidden rounded-[1.75rem] md:rounded-[2.5rem] bg-white/60 dark:bg-white/[0.03] border border-white/70 dark:border-white/10 backdrop-blur-md {{ $animate ? '' : 'no-anim' }}">

            <div class="zc-hero-blob b1 -z-10 w-72 h-72 bg-brown-400/60 -top-24 -right-16"></div>
            <div class="zc-hero-blob b2 -z-10 w-96 h-96 bg-amber-300/40 top-1/3 -left-32"></div>
            <div class="zc-hero-blob b3 -z-10 w-64 h-64 bg-brown-300/40 -bottom-24 right-1/3"></div>
            {{-- بافت نقطه‌ای ظریف --}}
            <div class="absolute inset-0 -z-10 opacity-[0.07] dark:opacity-[0.12]" style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 22px 22px;"></div>

            <div class="grid grid-cols-1 lg:grid-cols-2 items-center gap-8 lg:gap-12 p-5 md:p-10 lg:p-14">

                {{-- متن --}}
                <div class="order-2 {{ $mediaStart ? 'lg:order-2' : 'lg:order-1' }}">
                    @if(filled($data['eyebrow'] ?? null))
                        <div data-reveal style="--d: 80ms" class="inline-flex items-center gap-2 px-4 py-1.5 mb-5 rounded-full bg-brown-600/10 border border-brown-600/15 text-brown-700 dark:text-brown-300 text-[11px] md:text-xs font-black">
                            <span class="relative flex w-2 h-2"><span class="absolute inline-flex w-full h-full rounded-full bg-brown-500 opacity-75 animate-ping"></span><span class="relative inline-flex w-2 h-2 rounded-full bg-brown-600"></span></span>
                            {{ $data['eyebrow'] }}
                        </div>
                    @endif

                    @if($titleHtml)
                        <h1 data-reveal style="--d: 180ms" class="text-3xl md:text-5xl xl:text-6xl font-black leading-[1.3] md:leading-[1.2] tracking-tight text-gray-900 dark:text-white">{!! $titleHtml !!}</h1>
                    @endif

                    @if(filled($data['description'] ?? null))
                        <p data-reveal style="--d: 300ms" class="mt-5 text-sm md:text-base leading-8 text-gray-600 dark:text-gray-300 font-medium max-w-xl">{{ $data['description'] }}</p>
                    @endif

                    @include('components.main.sections.partials.hero-actions', ['dark' => false])

                    @if($stats)
                        <div data-reveal style="--d: 560ms" class="mt-10 flex flex-wrap gap-x-8 gap-y-4">
                            @foreach($stats as $stat)
                                <div class="relative pr-4 border-r-2 border-brown-600/30">
                                    <div class="text-2xl md:text-3xl font-black text-gray-900 dark:text-white tabular-nums" data-count="{{ $stat['value'] }}">{{ $stat['value'] }}</div>
                                    @if($stat['label'])<div class="text-[11px] font-bold text-gray-500 dark:text-gray-400 mt-1">{{ $stat['label'] }}</div>@endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- رسانه --}}
                <div class="relative order-1 {{ $mediaStart ? 'lg:order-1' : 'lg:order-2' }}">
                    <div class="zc-hero-parallax relative" style="--depth: 14px">
                        <div class="zc-hero-frame zc-hero-split-media relative overflow-hidden rounded-[2.5rem] shadow-2xl shadow-brown-900/20 bg-gradient-to-br from-brown-100 to-brown-200 dark:from-brown-900/40 dark:to-gray-900">
                            {!! $mediaMarkup('absolute inset-0 w-full h-full object-cover') !!}
                            @unless($mediaUrl)
                                <div class="absolute inset-0 flex items-center justify-center text-brown-400">
                                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            @endunless
                            <div class="absolute inset-0 bg-gradient-to-t from-black/25 via-transparent to-transparent pointer-events-none"></div>

                            @if($isVideo)
                                @include('components.main.sections.partials.hero-video-toggle', ['position' => 'bottom-4 left-4'])
                            @endif
                        </div>
                    </div>

                    {{-- کارت شناور: نشان --}}
                    @if(filled($data['badge_title'] ?? null))
                        <div class="zc-hero-parallax absolute -bottom-5 right-4 md:right-8 lg:-right-6" style="--depth: -22px">
                            <div data-reveal style="--d: 900ms"><div class="zc-hero-bob flex items-center gap-3 px-4 py-3 rounded-2xl bg-white/85 dark:bg-gray-900/85 backdrop-blur-xl border border-white dark:border-white/10 shadow-xl">
                                <span class="w-10 h-10 rounded-xl bg-brown-600 text-white flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                </span>
                                <span>
                                    <span class="block text-xs md:text-sm font-black text-gray-900 dark:text-white">{{ $data['badge_title'] }}</span>
                                    @if(filled($data['badge_text'] ?? null))
                                        <span class="block text-[10px] md:text-[11px] font-bold text-gray-500 dark:text-gray-400 mt-0.5">{{ $data['badge_text'] }}</span>
                                    @endif
                                </span>
                            </div></div>
                        </div>
                    @endif

                    {{-- کارت شناور: اولین آمار --}}
                    @if(!empty($stats[0]))
                        <div class="zc-hero-parallax absolute top-6 left-4 md:left-8 lg:-left-6 hidden sm:block" style="--depth: 18px">
                            <div data-reveal style="--d: 1050ms"><div class="zc-hero-bob alt px-4 py-3 rounded-2xl bg-white/85 dark:bg-gray-900/85 backdrop-blur-xl border border-white dark:border-white/10 shadow-xl text-center">
                                <div class="text-lg font-black text-brown-600 dark:text-brown-400 tabular-nums" data-count="{{ $stats[0]['value'] }}">{{ $stats[0]['value'] }}</div>
                                @if($stats[0]['label'])<div class="text-[10px] font-bold text-gray-500 dark:text-gray-400">{{ $stats[0]['label'] }}</div>@endif
                            </div></div>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <script>
        (function () {
            const root = document.getElementById(@js($uid));
            if (!root || root.dataset.zcInit) return;
            root.dataset.zcInit = '1';

            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const animate = root.dataset.animate === '1' && !reduce;
            const fa = '۰۱۲۳۴۵۶۷۸۹';
            const toFa = (s) => String(s).replace(/\d/g, (d) => fa[d]);
            const toEn = (s) => String(s).replace(/[۰-۹]/g, (d) => fa.indexOf(d)).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));

            // شمارش آمار: «+۱,۲۰۰ مشتری» => فقط بخش عددی شمرده می‌شود
            function countUp(el) {
                const original = el.dataset.count || el.textContent;
                const m = toEn(original).match(/^(\D*)([\d,]+(?:\.\d+)?)(.*)$/);
                if (!m) return;
                const target = parseFloat(m[2].replace(/,/g, ''));
                const decimals = (m[2].split('.')[1] || '').length;
                const grouped = m[2].includes(',');
                const start = performance.now(), dur = 1600;
                const fmt = (v) => {
                    let s = v.toFixed(decimals);
                    if (grouped) s = Number(s).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
                    return toFa(m[1] + s + m[3]);
                };
                (function step(now) {
                    const p = Math.min(1, (now - start) / dur);
                    el.textContent = fmt(target * (1 - Math.pow(1 - p, 3)));
                    if (p < 1) requestAnimationFrame(step); else el.textContent = toFa(original);
                })(start);
            }

            // ویدیو: فقط وقتی در دید است بارگذاری و پخش می‌شود
            const videos = [...root.querySelectorAll('video.zc-hero-video')];
            const toggle = root.querySelector('[data-zc-video-toggle]');
            let userPaused = false;
            const visibleVideos = () => videos.filter((v) => v.offsetParent !== null);
            const playAll = () => visibleVideos().forEach((v) => {
                if (!v.src) { v.src = v.dataset.src; }
                v.play().catch(() => {});
            });
            const pauseAll = () => videos.forEach((v) => v.pause());
            const syncToggle = () => toggle && toggle.setAttribute('data-playing', userPaused ? '0' : '1');

            toggle?.addEventListener('click', () => {
                userPaused = !userPaused;
                userPaused ? pauseAll() : playAll();
                syncToggle();
            });

            const io = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        if (!root.classList.contains('is-in')) {
                            root.classList.add('is-in');
                            if (animate) root.querySelectorAll('[data-count]').forEach(countUp);
                        }
                        if (!userPaused) playAll();
                    } else {
                        pauseAll();
                    }
                });
            }, { threshold: 0.2 });
            io.observe(root);

            // پارالاکس نرم با موس (فقط دسکتاپ و در صورت فعال بودن انیمیشن)
            if (animate && window.matchMedia('(pointer: fine) and (min-width: 1024px)').matches) {
                let raf = null;
                root.addEventListener('pointermove', (e) => {
                    if (raf) return;
                    raf = requestAnimationFrame(() => {
                        const r = root.getBoundingClientRect();
                        root.style.setProperty('--px', (((e.clientX - r.left) / r.width) - 0.5).toFixed(3));
                        root.style.setProperty('--py', (((e.clientY - r.top) / r.height) - 0.5).toFixed(3));
                        raf = null;
                    });
                });
                root.addEventListener('pointerleave', () => { root.style.setProperty('--px', 0); root.style.setProperty('--py', 0); });
            }
        })();
    </script>
</div>
