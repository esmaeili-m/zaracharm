{{--
    لودر صفحه فروشگاه (زاراچرم)
    - استایل و اسکریپت درون‌خطی: قبل از بارگذاری CSS/JS اصلی نمایش داده می‌شود
    - با کامل شدن بارگذاری صفحه محو می‌شود (حداقل نمایش کوتاه برای جلوگیری از چشمک، حداکثر ۸ ثانیه)
    - هنگام رفتن به صفحه دیگر سایت (لینک داخلی / wire:navigate) دوباره نمایش داده می‌شود
    - حالت تاریک و prefers-reduced-motion پشتیبانی می‌شود
--}}
@props(['name' => 'زاراچرم', 'latin' => 'ZARACHARM'])

<style>
    #zc-loader {
        --zc-bg: #faf6f1;
        --zc-ink: #3b2a1e;
        --zc-brown: #8b6a4f;
        --zc-gold: #c9a27a;
        --zc-soft: rgba(139, 106, 79, .14);
        position: fixed;
        inset: 0;
        z-index: 2147483000;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            radial-gradient(60% 50% at 50% 42%, rgba(201, 162, 122, .18), transparent 70%),
            var(--zc-bg);
        transition: opacity .55s ease, visibility .55s ease;
        direction: rtl;
    }

    html.dark #zc-loader {
        --zc-bg: #0b0907;
        --zc-ink: #f3e9df;
        --zc-brown: #b08a68;
        --zc-gold: #d9b48c;
        --zc-soft: rgba(217, 180, 140, .12);
    }

    #zc-loader.zc-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    #zc-loader .zc-box {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 22px;
        transform: translateY(-4%);
    }

    #zc-loader .zc-mark {
        position: relative;
        width: 112px;
        height: 112px;
    }

    #zc-loader .zc-mark svg {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        overflow: visible;
    }

    /* حلقه چرخان */
    #zc-loader .zc-ring {
        animation: zc-spin 2.4s cubic-bezier(.6, .1, .4, .9) infinite;
        transform-origin: 56px 56px;
    }

    /* آویز (charm) که با خط کشیده می‌شود */
    #zc-loader .zc-charm path,
    #zc-loader .zc-charm circle {
        stroke-dasharray: 260;
        stroke-dashoffset: 260;
        animation: zc-draw 2.4s ease-in-out infinite;
    }

    #zc-loader .zc-charm .zc-gem {
        animation: zc-gem 2.4s ease-in-out infinite;
        transform-origin: 56px 66px;
    }

    #zc-loader .zc-name {
        font-family: payda, Vazirmatn, Tahoma, sans-serif;
        font-size: 34px;
        font-weight: 900;
        line-height: 1.2;
        letter-spacing: 0;
        background: linear-gradient(100deg, var(--zc-brown) 0%, var(--zc-brown) 35%, var(--zc-gold) 50%, var(--zc-brown) 65%, var(--zc-brown) 100%);
        background-size: 250% 100%;
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        animation: zc-shine 2.4s linear infinite;
    }

    #zc-loader .zc-latin {
        margin-top: -14px;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 11px;
        letter-spacing: .55em;
        color: var(--zc-ink);
        opacity: .45;
        direction: ltr;
        padding-left: .55em;
    }

    #zc-loader .zc-bar {
        width: 140px;
        height: 2px;
        border-radius: 2px;
        background: var(--zc-soft);
        overflow: hidden;
    }

    #zc-loader .zc-bar span {
        display: block;
        width: 40%;
        height: 100%;
        border-radius: 2px;
        background: linear-gradient(90deg, transparent, var(--zc-gold), var(--zc-brown));
        animation: zc-bar 1.3s ease-in-out infinite;
    }

    @keyframes zc-spin { to { transform: rotate(360deg); } }
    @keyframes zc-draw {
        0% { stroke-dashoffset: 260; opacity: .3; }
        45%, 70% { stroke-dashoffset: 0; opacity: 1; }
        100% { stroke-dashoffset: -260; opacity: .3; }
    }
    @keyframes zc-gem {
        0%, 30% { opacity: 0; transform: scale(.6); }
        50%, 75% { opacity: 1; transform: scale(1); }
        100% { opacity: 0; transform: scale(.85); }
    }
    @keyframes zc-shine { from { background-position: 150% 0; } to { background-position: -100% 0; } }
    @keyframes zc-bar { 0% { transform: translateX(260%); } 100% { transform: translateX(-160%); } }

    @media (prefers-reduced-motion: reduce) {
        #zc-loader *, #zc-loader { animation: none !important; transition: opacity .2s ease, visibility .2s ease !important; }
        #zc-loader .zc-charm path, #zc-loader .zc-charm circle { stroke-dashoffset: 0; opacity: 1; }
        #zc-loader .zc-charm .zc-gem { opacity: 1; }
    }
</style>

<div id="zc-loader" role="status" aria-live="polite" aria-label="در حال بارگذاری {{ $name }}">
    <div class="zc-box">
        <div class="zc-mark" aria-hidden="true">
            <svg viewBox="0 0 112 112">
                <defs>
                    <linearGradient id="zc-grad" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="var(--zc-gold)"/>
                        <stop offset="100%" stop-color="var(--zc-brown)"/>
                    </linearGradient>
                </defs>
                {{-- حلقه پس‌زمینه و قوس چرخان --}}
                <circle cx="56" cy="56" r="52" fill="none" stroke="var(--zc-soft)" stroke-width="2"/>
                <g class="zc-ring">
                    <circle cx="56" cy="56" r="52" fill="none" stroke="url(#zc-grad)" stroke-width="2.5"
                            stroke-linecap="round" stroke-dasharray="70 257"/>
                    <circle cx="56" cy="4" r="3" fill="var(--zc-gold)"/>
                </g>
            </svg>
            <svg viewBox="0 0 112 112" class="zc-charm">
                {{-- گیره و آویز --}}
                <circle cx="56" cy="30" r="6" fill="none" stroke="url(#zc-grad)" stroke-width="2.4"/>
                <path d="M56 36 L56 42" fill="none" stroke="url(#zc-grad)" stroke-width="2.4" stroke-linecap="round"/>
                <path d="M56 42 L76 58 L56 86 L36 58 Z" fill="none" stroke="url(#zc-grad)" stroke-width="2.4" stroke-linejoin="round"/>
                <path d="M36 58 L76 58 M46 50 L56 58 L66 50 M56 58 L56 86" fill="none" stroke="url(#zc-grad)" stroke-width="1.4" stroke-linejoin="round" opacity=".75"/>
                <path class="zc-gem" d="M56 61 L63 66 L56 75 L49 66 Z" fill="url(#zc-grad)" stroke="none"/>
            </svg>
        </div>

        <div class="zc-name">{{ $name }}</div>
        <div class="zc-latin">{{ $latin }}</div>
        <div class="zc-bar"><span></span></div>
    </div>
</div>

<script>
    (function () {
        var root = document.documentElement;

        // تم قبل از نمایش لودر (همان منطق app.js) تا در حالت تاریک چشمک سفید نزند
        try {
            var saved = localStorage.getItem('theme');
            if (saved === 'dark' || (!saved && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                root.classList.add('dark');
            }
        } catch (e) {}

        var loader = document.getElementById('zc-loader');
        if (!loader) return;

        var shownAt = Date.now();
        var MIN = 450, MAX = 8000, hideTimer = null;

        function hide() {
            clearTimeout(hideTimer);
            hideTimer = setTimeout(function () {
                loader.classList.add('zc-hidden');
                document.body && document.body.classList.remove('overflow-hidden');
            }, Math.max(0, MIN - (Date.now() - shownAt)));
        }

        function show() {
            clearTimeout(hideTimer);
            shownAt = Date.now();
            loader.classList.remove('zc-hidden');
            // اگر ناوبری لغو شد (مثلاً دانلود فایل)، لودر نماند
            hideTimer = setTimeout(hide, MAX);
        }

        if (document.readyState === 'complete') {
            hide();
        } else {
            window.addEventListener('load', hide, { once: true });
            setTimeout(hide, MAX);
        }

        // بازگشت با دکمه Back از کش مرورگر
        window.addEventListener('pageshow', function (e) { if (e.persisted) hide(); });

        // لینک‌های داخلی سایت (بارگذاری کامل صفحه)
        document.addEventListener('click', function (e) {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

            var a = e.target.closest && e.target.closest('a[href]');
            if (!a || a.target && a.target !== '_self' || a.hasAttribute('download') || a.hasAttribute('wire:navigate')) return;
            if (a.hasAttribute('wire:click') || a.hasAttribute('x-on:click') || a.hasAttribute('@click') || a.dataset.noLoader !== undefined) return;

            var href = a.getAttribute('href') || '';
            if (!href || href.charAt(0) === '#' || /^(javascript|mailto|tel|sms|whatsapp|tg):/i.test(href)) return;

            var url;
            try { url = new URL(a.href, location.href); } catch (err) { return; }
            if (url.origin !== location.origin) return;
            if (url.pathname === location.pathname && url.search === location.search && url.hash) return;

            show();
        });

        // ناوبری Livewire (wire:navigate)
        document.addEventListener('livewire:navigate', show);
        document.addEventListener('livewire:navigated', hide);
    })();
</script>
