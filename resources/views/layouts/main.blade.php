<!doctype html>
<html lang="en" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>کیف و کفش زاراچرم</title>
    <meta name="description"
          content="قالب فرشگاهی دیارا، بهترین قالب برای فروشگاه‌های اینترنتی با طراحی مدرن و واکنش‌گرا.">
    <meta name="keywords" content="قالب فروشگاهی, قالب مانا, فروشگاه اینترنتی, طراحی واکنش‌گرا">
    <meta name="robots" content="index, follow">
    <meta name="author" content="امیر رضایی">
    <meta name="copyright" content="All rights belong to mana.">
    <link rel="apple-touch-icon" sizes="180x180" href="{{asset('main/images/favicon_io/apple-touch-icon.png')}}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{asset('main/images/favicon_io/favicon-32x32.png')}}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{asset('main/images/favicon_io/favicon-16x16.png')}}">
    <link rel="stylesheet" href="{{asset('main/js/plugin/story-player/styles.css')}}">
    <link rel="stylesheet" href="{{asset('main/js/plugin/swiper/swiper-bundle.min.css')}}">
    <link rel="stylesheet" href="{{asset('main/css/app.css')}}">
    <style>
        @media (max-width: 767px) {
            .mainHeroSwiper .swiper-pagination {
                display: none !important;
            }
        }
    </style>
    @stack('styles')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-gray-100 dark:bg-[#050505] min-h-screen transition-colors duration-700 selection:bg-brown-500/30 selection:text-brown-600 overflow-x-hidden">

<!-- HEADER -->
<header class="sticky top-0 z-50 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950">
    <div class="bg-primary-900 text-white py-1.5 text-center text-[11px] font-medium tracking-wide hidden md:block">
        <p>🎉 جشنواره زمستانی: تا ۵۰٪ تخفیف روی تمام محصولات  | کد تخفیف: <span class="text-secondary-400">WINTER2025</span></p>
    </div>

    <div class="container">
        <div class="flex items-center justify-between h-20 gap-8">

            <div class="flex items-center gap-4">
                <button id="resMenu" class="lg:hidden p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full transition-colors">
                    <svg class="w-6 h-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16m-7 6h7"/></svg>
                </button>
                <button id="mobile-search-toggle" class="md:hidden p-2.5 rounded-xl border border-gray-200/50 dark:border-white/10 bg-white/40 dark:bg-white/5 text-gray-600 dark:text-gray-400 transition-all shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </button>
                <a href="index.html" class="flex items-center gap-2 group">
                    <img src="assets/images/logo.png" class="w-30" alt="">
                </a>
            </div>

            <!-- Search -->
            <div id="search-wrapper" class="hidden md:flex flex-1 max-w-4xl relative group/search mx-auto">

                <div class="relative w-full z-[10000]">
                    <input type="text" id="main-search-input"
                           class="w-full bg-gray-200/60 dark:bg-[var(--color-primary-950)]/60 backdrop-blur-md border border-gray-300/30 dark:border-white/5 rounded-2xl py-4 pr-12 pl-40 text-sm font-bold text-right outline-none focus:bg-white dark:focus:bg-[var(--color-primary-950)] focus:ring-4 ring-[var(--color-primary-500)]/40 transition-all placeholder:text-gray-500 shadow-sm"
                           placeholder="جستجوی سراسری در محصولات ...">

                    <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-gray-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5"/></svg>
                    </div>

                    <div class="absolute left-2 top-1/2 -translate-y-1/2 flex items-center h-[75%] gap-2">
                        <div class="h-full w-px bg-gray-300/40 dark:bg-white/10 ml-1"></div>
                        <button class="h-full px-4 flex items-center gap-3 rounded-xl transition-all duration-300 group/archive
                           bg-white border border-gray-200 text-gray-700 shadow-sm hover:border-[var(--color-primary-500)]
                           dark:bg-[var(--color-primary-800)]/60 dark:border-white/10 dark:text-gray-200 dark:hover:bg-[var(--color-primary-500)]/10">
                            <div class="flex items-center justify-center w-6 h-6 rounded-lg bg-gray-100 dark:bg-white/10 group-hover/archive:bg-[var(--color-primary-500)] group-hover/archive:text-white transition-all duration-300">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </div>
                            <span class="text-[11px] font-black whitespace-nowrap">آرشیو محصولات</span>
                        </button>
                    </div>
                </div>

                <div id="mega-search-panel"
                     class="absolute top-[30px] left-[-15px] right-[-15px] pt-[65px] bg-white/90 dark:bg-[var(--color-primary-950)]/90 backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] shadow-[0_50px_100px_-20px_rgba(0,0,0,0.6)] opacity-0 invisible translate-y-4 group-focus-within/search:opacity-100 group-focus-within/search:visible group-focus-within/search:translate-y-0 transition-all duration-500 z-[9999]">

                    <div class="p-8">
                        <div class="flex items-center justify-start gap-3 mb-6">
                            <div class="p-1.5 bg-[var(--color-primary-500)]/10 rounded-lg text-[var(--color-primary-500)]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-width="2"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke-width="2"/></svg>
                            </div>
                            <span class="text-[13px] font-black text-gray-800 dark:text-gray-100 uppercase tracking-tighter">محصولات پربازدید هفته</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-10">
                            <div class="group/card relative flex items-center p-2 bg-white/40 dark:bg-white/[0.03] border border-gray-200/50 dark:border-white/5 rounded-[1.8rem] hover:bg-white dark:hover:bg-[var(--color-primary-900)] transition-all duration-500 cursor-pointer shadow-sm">
                                <div class="relative w-20 h-20 bg-gray-100 dark:bg-[var(--color-primary-800)] rounded-[1.5rem] p-2 flex-shrink-0">
                                    <img src="assets/images/product/mobile-3.png" class="w-full h-full object-contain group-hover/card:scale-110 transition-transform duration-500">
                                </div>
                                <div class="flex-1 pr-4">
                                    <h4 class="text-[12px] font-bold text-gray-800 dark:text-gray-100 mb-2 group-hover/card:text-[var(--color-primary-500)] transition-colors line-clamp-1">
                                        ساعت هوشمند مدل Watch Ultra 2 بند تیتانیوم
                                    </h4>
                                    <div class="flex items-center justify-between">
                                        <div class="px-3 py-1 bg-gray-100 dark:bg-white/5 rounded-xl text-[14px] font-black text-gray-900 dark:text-white">۳,۳۲۰,۰۰۰ <span class="text-[9px] text-gray-400 mr-1 font-bold">تومان</span></div>
                                        <svg class="w-4 h-4 ml-2 text-[var(--color-primary-500)] opacity-0 -translate-x-2 group-hover/card:opacity-100 group-hover/card:translate-x-0 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7" stroke-width="3"/></svg>
                                    </div>
                                </div>
                            </div>
                            <div class="group/card relative flex items-center p-2 bg-white/40 dark:bg-white/[0.03] border border-gray-200/50 dark:border-white/5 rounded-[1.8rem] hover:bg-white dark:hover:bg-[var(--color-primary-900)] transition-all duration-500 cursor-pointer shadow-sm">
                                <div class="relative w-20 h-20 bg-gray-100 dark:bg-[var(--color-primary-800)] rounded-[1.5rem] p-2 flex-shrink-0">
                                    <img src="assets/images/product/mobile-4.png" class="w-full h-full object-contain group-hover/card:scale-110 transition-transform duration-500">
                                </div>
                                <div class="flex-1 pr-4">
                                    <h4 class="text-[12px] font-bold text-gray-800 dark:text-gray-100 mb-2 group-hover/card:text-[var(--color-primary-500)] transition-colors line-clamp-1">
                                        ساعت هوشمند مدل Watch Ultra 2 بند تیتانیوم
                                    </h4>
                                    <div class="flex items-center justify-between">
                                        <div class="px-3 py-1 bg-gray-100 dark:bg-white/5 rounded-xl text-[14px] font-black text-gray-900 dark:text-white">۳,۳۲۰,۰۰۰ <span class="text-[9px] text-gray-400 mr-1 font-bold">تومان</span></div>
                                        <svg class="w-4 h-4 ml-2 text-[var(--color-primary-500)] opacity-0 -translate-x-2 group-hover/card:opacity-100 group-hover/card:translate-x-0 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7" stroke-width="3"/></svg>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="border-t border-dashed border-gray-200 dark:border-white/10 pt-8 grid grid-cols-1 md:grid-cols-2 gap-10">
                            <div class="space-y-4">
                                <div class="flex items-center gap-2 text-secondary-500">
                                    <span class="animate-pulse">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 128 128"><path fill="#ed6c30" d="M98.59 51.16c-4.23.92-7.88 3.28-9.59 7.35c-1.03 2.47-2.47 8.85-6.42 7.2c-1.89-.78-1.86-3.49-1.64-5.18c.47-3.47 2.03-6.64 3.1-9.94c1.1-3.42 2.05-6.86 2.73-10.4c2.28-11.72 1.65-25.22-6.64-34.59C78.73 4 75.2.3 72.87.22c-1.44-.04-.02 1.66.38 2.23c.81 1.17 1.49 2.44 2.01 3.77c6.13 15.64-8.98 27.55-18.91 36.82c-4.76 4.45-8.56 9.17-11.98 14.68c-.34.53-1.09 2.31-2.06 1.94c-1.15-.44-1.27-3.07-1.63-4.05c-.68-1.88-1.73-3.93-3.08-5.4c-2.61-2.86-6.26-4.79-10.21-4.53c-.15.01-.58.08-1.11.2c-.83.18-3.05.47-2.45 1.81c.31.69 1.22.63 1.87.82c8.34 2.56 8.15 11.3 6.8 18.32c-2.44 12.78-9.2 24.86-4.4 38c5.66 15.49 23.38 25.16 39.46 22.5c4.39-.72 9.45-2.14 13.39-4.26c4.19-2.26 8.78-5.35 12.05-8.83c4.21-4.47 6.89-10.2 7.68-16.27c.93-7.02-1.31-13.64-3.35-20.27c-2.46-8-5.29-21.06 4.93-24.97c.5-.2 1.5-.35 1.85-.88c1.3-1.94-4.94-.81-5.52-.69"/><path fill="#fcc21b" d="M68.13 106.07c2.12 1.78 5.09.91 7.09-.61c1.07-.81 1.99-1.85 2.59-3.06c.25-.52.54-1.18.54-1.77c0-.79-.47-1.57-.27-2.38c1.68-.33 3.76 4.5 3.97 5.62c1.68 8.83-6.64 16.11-14.67 17.52c-13.55 2.37-21.34-9.5-19.78-20.04c.97-6.56 5.37-11.07 9.85-15.57c3.71-3.73 7.15-6.93 8.35-11.78c.21-.86.16-2.18-.09-3.03c-.21-.73-.61-1.4-.63-2.19c-.06-1.66 1.55.51 1.92.93c4.46 5.03 5.73 12.46 4.54 18.96c-.77 4.2-3.77 7.2-4.82 11.22c-.61 2.29-.55 4.52 1.41 6.18"/></svg>
                                    </span>
                                    <span class="text-[13px] font-black text-gray-800 dark:text-gray-200 uppercase tracking-tighter">جستجوهای ترند</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <a href="#" class="px-4 py-2 bg-gray-100 dark:bg-white/5 text-[11px] font-bold text-gray-500 dark:text-gray-400 rounded-full hover:border-[var(--color-primary-500)] hover:text-[var(--color-primary-500)] border border-transparent transition-all">گوشی iphone 17</a>
                                    <a href="#" class="px-4 py-2 bg-gray-100 dark:bg-white/5 text-[11px] font-bold text-gray-500 dark:text-gray-400 rounded-full hover:border-[var(--color-primary-500)] hover:text-[var(--color-primary-500)] border border-transparent transition-all">گوشی iphone 16</a>
                                    <a href="#" class="px-4 py-2 bg-gray-100 dark:bg-white/5 text-[11px] font-bold text-gray-500 dark:text-gray-400 rounded-full hover:border-[var(--color-primary-500)] hover:text-[var(--color-primary-500)] border border-transparent transition-all">گوشی S25</a>

                                    <a href="#" class="px-4 py-2 bg-gray-100 dark:bg-white/5 text-[11px] font-bold text-gray-500 dark:text-gray-400 rounded-full hover:border-[var(--color-primary-500)] hover:text-[var(--color-primary-500)] border border-transparent transition-all">گوشی iphone 17</a>
                                    <a href="#" class="px-4 py-2 bg-gray-100 dark:bg-white/5 text-[11px] font-bold text-gray-500 dark:text-gray-400 rounded-full hover:border-[var(--color-primary-500)] hover:text-[var(--color-primary-500)] border border-transparent transition-all">گوشی iphone 16</a>
                                    <a href="#" class="px-4 py-2 bg-gray-100 dark:bg-white/5 text-[11px] font-bold text-gray-500 dark:text-gray-400 rounded-full hover:border-[var(--color-primary-500)] hover:text-[var(--color-primary-500)] border border-transparent transition-all">گوشی S25</a>
                                </div>
                            </div>
                            <div class="space-y-4 border-r border-gray-100 dark:border-white/5 pr-8">
                                <div class="flex items-center gap-2 text-gray-800 dark:text-gray-200">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2.5"/></svg>
                                    <span class="text-[12px] font-black">جستجوهای اخیر شما</span>
                                </div>
                                <ul class="space-y-2">
                                    <li class="flex items-center justify-between group/h cursor-pointer text-[12px] font-bold text-gray-600 dark:text-gray-400 hover:text-[var(--color-primary-500)]">
                                        <span>مک بوک</span>
                                        <button class="opacity-0 group-hover/h:opacity-100 text-red-400">×</button>
                                    </li>
                                    <li class="flex items-center justify-between group/h cursor-pointer text-[12px] font-bold text-gray-600 dark:text-gray-400 hover:text-[var(--color-primary-500)]">
                                        <span>گوشی شیائومی</span>
                                        <button class="opacity-0 group-hover/h:opacity-100 text-red-400">×</button>
                                    </li>
                                    <li class="flex items-center justify-between group/h cursor-pointer text-[12px] font-bold text-gray-600 dark:text-gray-400 hover:text-[var(--color-primary-500)]">
                                        <span>گوشی سامسونگ</span>
                                        <button class="opacity-0 group-hover/h:opacity-100 text-red-400">×</button>
                                    </li>

                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Register and action button-->
            <div class="flex items-center gap-3">

                <button id="dark-mode-toggle"
                        class="p-2.5 rounded-xl border border-gray-200/50 dark:border-white/10 bg-white/40 dark:bg-white/5 backdrop-blur-md hover:border-primary-500/50 hover:bg-white/80 dark:hover:bg-white/10 text-gray-600 dark:text-gray-400 hover:text-primary-500 transition-all duration-300 shadow-sm">
                    <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/></svg>
                    <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                </button>

                <div class="relative group">
                    <a
{{--                        id="login-btn" --}}
                       href="{{ auth()->check() ? route('user.dashboard') : route('login')}}"
                            class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200/50 dark:border-white/10 bg-white/40 dark:bg-white/5 backdrop-blur-md hover:border-primary-500/50 hover:bg-primary-50/50 dark:hover:bg-primary-500/10 transition-all duration-300 group shadow-sm">
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400 group-hover:text-primary-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span class="text-xs font-black text-gray-700 dark:text-gray-200 hidden lg:block uppercase tracking-tighter">{{auth()->check() ? (auth()->user()->fullname == "" ? auth()->user()->fullname : auth()->user()->mobile) : 'ورود یا ثبت ‌نام'}}</span>
                    </a>
                </div>
                @auth
                    <livewire:main.cart />
                @endauth
            </div>

        </div>
    </div>

    <livewire:layout.navbar />

</header>
<!-- END HEADER -->

<main class="space-y-12">
    {{$slot}}
</main>

<!-- FOOTER -->
<footer class="relative amazing-glass-footer pt-24 pb-12 overflow-hidden transition-colors duration-500">

    <div class="container relative z-10">

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-24">
            <div class="contact-tile">
                <div class="icon-box bg-brown-500/10 text-brown-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" stroke-width="2"/></svg></div>
                <div><h4 class="text-xs font-bold text-gray-400 mb-1">شماره تماس</h4><p class="text-sm font-black dark:text-white">۰۲۱-۹۱۰۰XXXX</p></div>
            </div>
            <div class="contact-tile">
                <div class="icon-box bg-indigo-500/10 text-indigo-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/></svg></div>
                <div><h4 class="text-xs font-bold text-gray-400 mb-1">ساعات پاسخگویی</h4><p class="text-sm font-black dark:text-white">شنبه تا پنجشنبه، ۹ تا ۱۸</p></div>
            </div>
            <div class="contact-tile">
                <div class="icon-box bg-purple-500/10 text-purple-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" stroke-width="2"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" stroke-width="2"/></svg></div>
                <div><h4 class="text-xs font-bold text-gray-400 mb-1">دفتر مرکزی</h4><p class="text-sm font-black dark:text-white">تهران، سعادت‌آباد، برج راستچین</p></div>
            </div>
            <div class="contact-tile">
                <div class="icon-box bg-emerald-500/10 text-emerald-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke-width="2"/></svg></div>
                <div><h4 class="text-xs font-bold text-gray-400 mb-1">پشتیبانی ایمیلی</h4><p class="text-sm font-black dark:text-white">Support@Rtltheme.com</p></div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-12 mb-24">

            <div class="xl:col-span-2 space-y-8">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-brown-600 rounded-2xl flex items-center justify-center shadow-lg shadow-brown-600/30">
                        <span class="text-white text-2xl font-black">Z</span>
                    </div>
                    <span class="text-2xl font-black text-gray-900 dark:text-white uppercase">ZARA<span class="text-brown-600">CHARM</span></span>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 leading-8 text-justify font-medium max-w-md">
                    کیف و کفش زارا چرم؛ ترکیبی از اصالت، کیفیت و استایل. ما با ارائه محصولات چرمی باکیفیت و طراحی‌های به‌روز، انتخابی مطمئن برای کسانی هستیم که به جزئیات و ماندگاری اهمیت می‌دهند. اصالت، کیفیت و رضایت شما، سه اصل اصلی ماست.                </p>
                <div class="flex gap-4">
                    <a href="#" class=" w-12 h-12 rounded-2xl bg-black/5 dark:bg-white/5 border border-black/5 dark:border-white/10 flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-brown-600 hover:text-white hover:scale-110 transition-all duration-500"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"></path></svg></a>
                    <a href="#" class=" w-12 h-12 rounded-2xl bg-black/5 dark:bg-white/5 border border-black/5 dark:border-white/10 flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-brown-600 hover:text-white hover:scale-110 transition-all duration-500"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"></path></svg></a>
                    <a href="#" class=" w-12 h-12 rounded-2xl bg-black/5 dark:bg-white/5 border border-black/5 dark:border-white/10 flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-brown-600 hover:text-white hover:scale-110 transition-all duration-500"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 22.954 24 17.99 24 12z"/></svg></a>
                </div>
            </div>

            <div class="space-y-8">
                <h3 class="footer-title">دسترسی سریع</h3>
                <ul class="space-y-5">
                    <li><a href="#" class="footer-link">گوشی‌های هوشمند</a></li>
                    <li><a href="#" class="footer-link">لپ‌تاپ و تبلت</a></li>
                    <li><a href="#" class="footer-link">گجت‌های پوشیدنی</a></li>
                    <li><a href="#" class="footer-link">لوازم جانبی</a></li>
                </ul>
            </div>

            <div class="space-y-8">
                <h3 class="footer-title">راهنمای خرید</h3>
                <ul class="space-y-5">
                    <li><a href="#" class="footer-link">پیگیری سفارش</a></li>
                    <li><a href="#" class="footer-link">شرایط مرجوعی</a></li>
                    <li><a href="#" class="footer-link">سوالات متداول</a></li>
                    <li><a href="#" class="footer-link">تماس با پشتیبانی</a></li>
                </ul>
            </div>

            <div class="flex flex-col gap-6 items-center lg:items-end">
                <h3 class="footer-title">مجوزهای قانونی</h3>
                <div class="flex gap-4">
                    <div class="w-28 h-36 bg-white/30 dark:bg-white/[0.02] backdrop-blur-md rounded-2xl border border-gray-200 dark:border-white/10 flex flex-col items-center justify-center p-4 transition-all duration-500 hover:shadow-lg hover:border-brown-500/30 group">
                        <div class="w-14 h-14 bg-gray-100 dark:bg-white/5 rounded-lg mb-4 flex items-center justify-center transition-all duration-500">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" stroke-width="2"/></svg>
                        </div>
                        <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.2em]">Enamad</span>
                    </div>
                    <div class="w-28 h-36 bg-white/30 dark:bg-white/[0.02] backdrop-blur-md rounded-2xl border border-gray-200 dark:border-white/10 flex flex-col items-center justify-center p-4 transition-all duration-500 hover:shadow-lg hover:border-brown-500/30 group">
                        <div class="w-14 h-14 bg-gray-100 dark:bg-white/5 rounded-lg mb-4 flex items-center justify-center transition-all duration-500">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" stroke-width="2"/></svg>
                        </div>
                        <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.2em]">Samandehi</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-10 border-t border-black/5 dark:border-white/5 flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="flex items-center gap-4">
                <p class="text-[11px] text-gray-500 font-bold">© ۲۰۲۵ طراحی و توسعه توسط <span class="text-gray-900 dark:text-white font-black underline decoration-brown-500/30 decoration-4">ManaTeam</span>.</p>
            </div>
            <div class="flex flex-wrap justify-center gap-8">
                <a href="#" class="legal-link">شرایط خدمات</a>
                <a href="#" class="legal-link">سیاست حفظ حریم خصوصی</a>
                <a href="#" class="legal-link">کوکی‌ها</a>
                <a href="#" class="legal-link">امنیت</a>
            </div>
        </div>
    </div>
</footer>
<!-- END FOOTER -->

<!-- NAV MOBILE -->
<div class="fixed bottom-4 left-1/2 -translate-x-1/2 w-[92%] max-w-[400px] z-50 md:hidden">

    <div class="relative bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-3xl px-2 py-3">

        <ul class="flex items-center justify-around">

            <!-- Home -->
            <li>
                <a href="#" class="flex flex-col items-center gap-1 px-4 py-1 text-brown-600 dark:text-brown-400">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                    </svg>

                    <span class="text-[10px] font-bold">
                        خانه
                    </span>
                </a>
            </li>

            <!-- Categories -->
            <li>
                <a href="#" class="flex flex-col items-center gap-1 px-4 py-1 text-gray-500 dark:text-gray-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16m-7 6h7"/>
                    </svg>

                    <span class="text-[10px] font-bold">
                        دسته‌ها
                    </span>
                </a>
            </li>

            <!-- Cart -->
            <li class="-mt-8">
                <a href="#" class="relative flex items-center justify-center w-14 h-14 bg-brown-600 rounded-2xl text-white">

                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>

                    <span class="absolute -top-1 -right-1 bg-red-500 text-[9px] font-bold w-5 h-5 rounded-full flex items-center justify-center">
                        ۳
                    </span>

                </a>
            </li>

            <!-- Wishlist -->
            <li>
                <a href="#" class="flex flex-col items-center gap-1 px-4 py-1 text-gray-500 dark:text-gray-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>

                    <span class="text-[10px] font-bold">
                        علاقه‌مندی
                    </span>
                </a>
            </li>

            <!-- Profile -->
            <li>
                <a href="#" class="flex flex-col items-center gap-1 px-4 py-1 text-gray-500 dark:text-gray-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>

                    <span class="text-[10px] font-bold">
                        پروفایل
                    </span>
                </a>
            </li>

        </ul>

    </div>
</div>
<!-- END NAV MOBILE -->

<!-- SEARCH MODAL -->
<div id="search-modal" class="fixed inset-0 z-[2000] invisible opacity-0 transition-all duration-300 md:hidden flex flex-col">
    <div class="absolute inset-0 bg-gray-900/60 dark:bg-black/90 backdrop-blur-2xl"></div>

    <div class="relative flex-1 flex flex-col bg-white/10 dark:bg-black/20 overflow-hidden">

        <div class="flex items-center justify-between p-5 border-b border-white/10">
            <span class="text-xl font-black text-gray-800 dark:text-white">جستجو در <span class="text-primary-500">مانا</span></span>
            <button id="close-search-modal" class="p-2 bg-white/10 rounded-full text-gray-500 dark:text-gray-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="p-5">
            <div class="relative w-full">
                <input type="text" id="modal-search-input"
                       class="w-full bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-2xl py-4 pr-12 pl-4 text-sm font-bold dark:text-white outline-none focus:ring-2 ring-primary-500 transition-all shadow-xl"
                       placeholder="نام محصول، برند یا دسته...">
                <div class="absolute inset-y-0 right-4 flex items-center text-primary-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5"/></svg>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-5 pb-10 custom-scrollbar">

            <div class="mb-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-1.5 bg-primary-500/10 rounded-lg text-primary-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-width="2"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke-width="2"/></svg>
                    </div>
                    <span class="text-[13px] font-black text-gray-800 dark:text-gray-100 uppercase tracking-tighter">محصولات پربازدید هفته</span>
                </div>

                <div class="grid grid-cols-1 gap-4">
                    <a href="">
                        <div class="flex items-center p-2 bg-white/40 dark:bg-white/[0.03] border border-gray-200/50 dark:border-white/5 rounded-[1.8rem] shadow-sm">
                            <div class="w-16 h-16 bg-gray-100 dark:bg-primary-800/20 rounded-[1.2rem] p-2 flex-shrink-0">
                                <img src="assets/images/product/mobile-3.png" class="w-full h-full object-contain">
                            </div>
                            <div class="flex-1 pr-3">
                                <h4 class="text-[11px] font-bold text-gray-800 dark:text-gray-100 line-clamp-1">ساعت هوشمند مدل Watch Ultra 2</h4>
                                <div class="text-[13px] font-black text-primary-500 mt-1">۳,۳۲۰,۰۰۰ <span class="text-[9px] text-gray-400">تومان</span></div>
                            </div>
                        </div>
                    </a>
                    <a href="">
                        <div class="flex items-center p-2 bg-white/40 dark:bg-white/[0.03] border border-gray-200/50 dark:border-white/5 rounded-[1.8rem] shadow-sm">
                            <div class="w-16 h-16 bg-gray-100 dark:bg-primary-800/20 rounded-[1.2rem] p-2 flex-shrink-0">
                                <img src="assets/images/product/mobile-4.png" class="w-full h-full object-contain">
                            </div>
                            <div class="flex-1 pr-3">
                                <h4 class="text-[11px] font-bold text-gray-800 dark:text-gray-100 line-clamp-1">هدفون بی سیم مدل Pro 2</h4>
                                <div class="text-[13px] font-black text-primary-500 mt-1">۱,۸۵۰,۰۰۰ <span class="text-[9px] text-gray-400">تومان</span></div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="mb-8 p-5 bg-primary-500/5 rounded-3xl border border-primary-500/10">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span class="text-[13px] font-black uppercase">جستجوهای ترند</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="#" class="px-4 py-2 bg-white dark:bg-white/5 text-[10px] font-bold text-gray-500 dark:text-gray-400 rounded-full border border-gray-100 dark:border-white/5">گوشی iphone 17</a>
                    <a href="#" class="px-4 py-2 bg-white dark:bg-white/5 text-[10px] font-bold text-gray-500 dark:text-gray-400 rounded-full border border-gray-100 dark:border-white/5">سامسونگ S25</a>
                    <a href="#" class="px-4 py-2 bg-white dark:bg-white/5 text-[10px] font-bold text-gray-500 dark:text-gray-400 rounded-full border border-gray-100 dark:border-white/5">مک بوک M3</a>
                </div>
            </div>

            <div class="mb-5">
                <div class="flex items-center gap-2 text-gray-800 dark:text-gray-200 mb-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2.5"/></svg>
                    <span class="text-[12px] font-black">جستجوهای اخیر</span>
                </div>
                <ul class="space-y-3">
                    <li class="flex items-center justify-between text-[12px] font-bold text-gray-600 dark:text-gray-400">
                        <span>مک بوک پرو ۲۰۲۴</span>
                        <button class="text-red-400 text-lg">×</button>
                    </li>
                    <li class="flex items-center justify-between text-[12px] font-bold text-gray-600 dark:text-gray-400">
                        <span>گوشی شیائومی</span>
                        <button class="text-red-400 text-lg">×</button>
                    </li>
                </ul>
            </div>

            <button class="w-full py-4 mt-4 bg-primary-500 text-white rounded-2xl font-black text-sm shadow-lg shadow-primary-500/30 flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                مشاهده آرشیو کامل محصولات
            </button>

        </div>
    </div>
</div>
<!-- END SEARCH MODAL -->

<!--QUICK MODAL-->
<div id="quick-view-modal" class="fixed inset-0 bg-brown-900/20 dark:bg-black/70 backdrop-blur-md flex items-center justify-center z-[999] opacity-0 pointer-events-none transition-all duration-500">

    <div class="bg-white/80 dark:bg-gray-900/85 backdrop-blur-md rounded-[3.5rem] p-6 md:p-10 w-11/12 lg:w-3/4 max-w-5xl max-h-[92vh] overflow-y-auto transform scale-95 opacity-0 transition-all duration-500 relative border border-white dark:border-gray-700/50 shadow-2xl" dir="rtl">

        <button class="close-modal-btn absolute top-6 left-6 p-3 bg-white/50 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 rounded-2xl hover:bg-red-500 hover:text-white transition-all z-10">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">

            <div class="flex flex-col gap-6">

                <!-- Gallery -->

                <div class="relative group">
                    <div class="swiper mainGallerySwiper rounded-[2.5rem] overflow-hidden border border-gray-100 dark:border-gray-800 shadow-inner bg-gray-50 dark:bg-gray-800/20">
                        <div class="swiper-wrapper">
                            <div class="swiper-slide">
                                <div class="swiper-zoom-container">
                                    <img src="assets/images/product/mobile-1.png" class="w-full object-cover"  alt=""/>
                                </div>
                            </div>
                            <div class="swiper-slide">
                                <div class="swiper-zoom-container">
                                    <img src="assets/images/product/mobile-2.png" class="w-full object-cover"  alt=""/>
                                </div>
                            </div>
                            <div class="swiper-slide">
                                <div class="swiper-zoom-container">
                                    <img src="assets/images/product/mobile-3.png" class="w-full object-cover"  alt=""/>
                                </div>
                            </div>
                            <div class="swiper-slide">
                                <div class="swiper-zoom-container">
                                    <img src="assets/images/product/mobile-4.png" class="w-full object-cover"  alt=""/>
                                </div>
                            </div>
                            <div class="swiper-slide">
                                <div class="swiper-zoom-container">
                                    <img src="assets/images/product/mobile-5.png" class="w-full object-cover"  alt=""/>
                                </div>
                            </div>
                        </div>
                        <div class="custom-next absolute top-1/2 -translate-y-1/2 left-4 z-20 w-12 h-12 flex items-center justify-center bg-white/40 dark:bg-gray-900/40 backdrop-blur-md border border-white/50 dark:border-white/10 rounded-2xl text-gray-800 dark:text-white cursor-pointer opacity-0 group-hover:opacity-100 -translate-x-4 group-hover:translate-x-0 transition-all duration-500 hover:bg-primary-600 hover:text-white hover:shadow-lg hover:shadow-primary-500/30">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                        </div>

                        <div class="custom-prev absolute top-1/2 -translate-y-1/2 right-4 z-20 w-12 h-12 flex items-center justify-center bg-white/40 dark:bg-gray-900/40 backdrop-blur-md border border-white/50 dark:border-white/10 rounded-2xl text-gray-800 dark:text-white cursor-pointer opacity-0 group-hover:opacity-100 translate-x-4 group-hover:translate-x-0 transition-all duration-500 hover:bg-primary-600 hover:text-white hover:shadow-lg hover:shadow-primary-500/30">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </div>

                    <div class="swiper thumbSwiper mt-4 px-1">
                        <div class="swiper-wrapper">
                            <div class="swiper-slide cursor-pointer opacity-40 transition-all duration-300">
                                <img src="assets/images/product/mobile-1.png" class="rounded-2xl border-2 border-transparent object-cover w-full aspect-square"  alt=""/>
                            </div>
                            <div class="swiper-slide cursor-pointer opacity-40 transition-all duration-300">
                                <img src="assets/images/product/mobile-2.png" class="rounded-2xl border-2 border-transparent object-cover w-full aspect-square"  alt=""/>
                            </div>
                            <div class="swiper-slide cursor-pointer opacity-40 transition-all duration-300">
                                <img src="assets/images/product/mobile-3.png" class="rounded-2xl border-2 border-transparent object-cover w-full aspect-square"  alt=""/>
                            </div>
                            <div class="swiper-slide cursor-pointer opacity-40 transition-all duration-300">
                                <img src="assets/images/product/mobile-4.png" class="rounded-2xl border-2 border-transparent object-cover w-full aspect-square"  alt=""/>
                            </div>
                            <div class="swiper-slide cursor-pointer opacity-40 transition-all duration-300">
                                <img src="assets/images/product/mobile-5.png" class="rounded-2xl border-2 border-transparent object-cover w-full aspect-square"  alt=""/>
                            </div>
                        </div>
                    </div>

                </div>


                <div class="grid grid-cols-3 gap-3 mt-2">
                    <div class="bg-brown-50/50 dark:bg-brown-900/10 p-4 rounded-[1.5rem] text-center border border-brown-100/50 dark:border-brown-500/10">
                        <span class="block text-[10px] text-brown-500 dark:text-brown-400 font-black mb-1">پردازنده</span>
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200 uppercase">Core i9</span>
                    </div>
                    <div class="bg-brown-50/50 dark:bg-brown-900/10 p-4 rounded-[1.5rem] text-center border border-brown-100/50 dark:border-brown-500/10">
                        <span class="block text-[10px] text-brown-500 dark:text-brown-400 font-black mb-1">رم</span>
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200 uppercase">32GB DDR5</span>
                    </div>
                    <div class="bg-brown-50/50 dark:bg-brown-900/10 p-4 rounded-[1.5rem] text-center border border-brown-100/50 dark:border-brown-500/10">
                        <span class="block text-[10px] text-brown-500 dark:text-brown-400 font-black mb-1">حافظه</span>
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200 uppercase">1TB SSD</span>
                    </div>

                </div>
            </div>

            <div class="flex flex-col">
                <div class="mb-6">
                    <h3 class="text-3xl font-black text-gray-900 dark:text-white mb-3 leading-tight tracking-tight">لپ‌تاپ ۱۶ اینچی ایسوس مدل ROG Zephyrus M16</h3>
                    <div class="flex items-center gap-4 text-xs">
                        <span class="text-gray-400">شناسه کالا: <span class="text-brown-600 font-bold">DKP-88231</span></span>
                        <div class="flex items-center gap-1 text-secondary-500 bg-secondary-500/10 px-3 py-1 rounded-full font-black">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <span>۴.۸</span>
                        </div>
                    </div>
                </div>

                <div class="mb-6 p-1 rounded-3xl bg-gray-50/50 dark:bg-gray-800/40 border border-white/50 dark:border-gray-700/50">
                    <select class="w-full bg-transparent border-none rounded-2xl text-sm p-4 outline-none text-gray-700 dark:text-gray-200 font-bold cursor-pointer">
                        <option>۱۸ ماه گارانتی اصلی یکتا سرویس (رایگان)</option>
                        <option>۲۴ ماه گارانتی طلایی پارس (۱,۲۰۰,۰۰۰ تومان)</option>
                    </select>
                </div>

                <div class="mb-8">
                    <span class="text-sm font-black text-gray-800 dark:text-gray-200 block mb-4">انتخاب رنگ:</span>
                    <div class="flex gap-3">
                        <button class="color-option active w-10 h-10 rounded-2xl flex items-center justify-center border-2 border-transparent transition-all bg-black shadow-lg ring-4 ring-brown-500/20 group" data-color="مشکی">
                            <svg class="w-5 h-5 text-white opacity-0 group-[.active]:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </button>
                        <button class="color-option w-10 h-10 rounded-2xl flex items-center justify-center border-2 border-transparent transition-all bg-gray-400 group opacity-50" data-color="طوسی">
                            <svg class="w-5 h-5 text-white opacity-0 group-[.active]:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="mb-8">
                    <div class="flex justify-between text-[10px] mb-2 font-bold">
                        <span class="text-red-500">فقط ۳ عدد در انبار باقیست!</span>
                        <span class="text-gray-400 tracking-tighter">۸۰٪ فروخته شده</span>
                    </div>
                    <div class="w-full h-2 bg-gray-100 dark:bg-gray-800/60 rounded-full overflow-hidden border border-white dark:border-gray-700/30">
                        <div class="w-[80%] h-full bg-gradient-to-l from-red-500 to-rose-400 rounded-full"></div>
                    </div>
                </div>

                <div class="mt-auto p-6 lg:p-8 rounded-[2.5rem] bg-brown-50/30 dark:bg-brown-500/5 border border-brown-200/50 dark:border-brown-500/20 relative overflow-hidden backdrop-blur-sm">

                    <div class="flex justify-between items-center relative z-10">
                        <div class="flex flex-col">
                            <span class="text-gray-400 dark:text-gray-500 line-through text-xs font-bold mb-1">۴۵,۰۰۰,۰۰۰</span>
                            <div class="flex items-center gap-2">
                                <span class="text-4xl font-black text-brown-600 dark:text-brown-400 tracking-tight">۳۸,۵۰۰,۰۰۰</span>
                                <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400">تومان</span>
                            </div>
                        </div>

                        <div class="flex items-center bg-white dark:bg-gray-800/80 rounded-2xl p-1 shadow-sm border border-gray-100 dark:border-gray-700">
                            <button onclick="changeQty(1)" class="w-9 h-9 flex items-center justify-center text-brown-600 dark:text-brown-400 hover:bg-brown-50 dark:hover:bg-brown-900/30 rounded-xl transition-all font-black text-xl">+</button>
                            <input type="number" id="modalQty" value="1" readonly class="w-10 bg-transparent text-center font-black text-gray-800 dark:text-white outline-none border-none text-sm" />
                            <button onclick="changeQty(-1)" class="w-9 h-9 flex items-center justify-center text-gray-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-all font-black text-xl">-</button>
                        </div>
                    </div>

                    <button class="w-full mt-6 bg-brown-600 hover:bg-brown-700 text-white font-black py-5 rounded-[1.8rem] shadow-lg shadow-brown-500/20 transition-all flex items-center justify-center gap-3 active:scale-95 group">
                        <svg class="w-6 h-6 group-hover:animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        افزودن به سبد خرید
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<!--END QUICK MODAL-->

<!--LOGIN MODAL-->
<div id="login-modal" class="fixed inset-0 bg-gray-900/60 dark:bg-black/90 flex items-center justify-center z-[1000] opacity-0 pointer-events-none transition-all duration-500 backdrop-blur-md p-4" dir="rtl">

    <div class="relative w-[92%] max-w-[430px] transform scale-95 translate-y-10 transition-all duration-500 my-auto h-fit">

        <div class="absolute -inset-4 bg-brown-500/20 blur-[50px] rounded-[4rem] -z-10"></div>

        <div class="relative bg-white/80 dark:bg-gray-950/85 backdrop-blur-md rounded-[3.5rem] border border-white dark:border-white/10 shadow-[0_32px_64px_-15px_rgba(0,0,0,0.2)] overflow-y-auto max-h-[92vh] scroll-smooth scrollbar-hide">

            <button onclick="closeLoginModal()" class="close-login absolute top-6 left-6 w-11 h-11 flex items-center justify-center rounded-2xl bg-white/50 dark:bg-white/5 text-gray-400 hover:text-brown-600 hover:scale-110 transition-all border border-white/80 dark:border-white/5 shadow-sm z-50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <div class="p-8 lg:p-12">
                <div id="auth-steps">

                    <div id="step-1" class="auth-step transition-all duration-300">
                        <div class="text-center mb-10">
                            <div class="relative w-20 h-20 lg:w-24 lg:h-24 mx-auto mb-8">
                                <div class="absolute inset-0 bg-brown-500/20 rounded-[2.5rem] rotate-12 animate-pulse"></div>
                                <div class="relative w-full h-full bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 rounded-[2.5rem] flex items-center justify-center text-brown-600 shadow-lg border border-white dark:border-gray-700">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                            </div>
                            <h3 class="text-2xl lg:text-3xl font-black text-gray-900 dark:text-white tracking-tight">خوش آمدید</h3>
                            <div class="flex items-center justify-center gap-2 mt-3">
                                <span class="w-2 h-2 bg-brown-500 rounded-full animate-ping"></span>
                                <p class="text-sm text-gray-500 dark:text-gray-400 font-bold">ورود یا ثبت‌نام در سایت</p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="space-y-2 text-right">
                                <label class="text-[12px] font-black text-gray-400 dark:text-gray-500 mr-2 uppercase">شماره موبایل</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 right-5 flex items-center pointer-events-none text-gray-400 group-focus-within:text-brown-500 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    </div>
                                    <input type="tel" id="phoneInput" dir="ltr" maxlength="11" placeholder="09123456789"
                                           class="w-full bg-gray-100/50 dark:bg-white/5 border-2 border-transparent focus:border-brown-500/30 focus:bg-white dark:focus:bg-gray-900 rounded-[2rem] p-5 pr-14 outline-none font-black text-xl sm:text-2xl tracking-[0.1em] text-gray-900 dark:text-white transition-all shadow-inner">
                                </div>
                            </div>

                            <button onclick="goToStep(2)" class="group relative w-full bg-brown-600 hover:bg-brown-700 text-white font-black py-5 rounded-[2rem] transition-all active:scale-[0.97] overflow-hidden shadow-lg shadow-brown-500/25">
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:animate-[shimmer_1.5s_infinite]"></div>
                                <span class="relative flex items-center justify-center gap-3 text-lg">تایید و دریافت کد</span>
                            </button>

                            <div class="relative flex items-center justify-center py-2">
                                <div class="flex-grow border-t border-gray-200 dark:border-white/10"></div>
                                <span class="relative z-10 px-4 text-[11px] font-black text-gray-400 bg-transparent uppercase">یا از طریق</span>
                                <div class="flex-grow border-t border-gray-200 dark:border-white/10"></div>
                            </div>

                            <button class="w-full bg-white dark:bg-white/5 border border-gray-100 dark:border-white/10 text-gray-700 dark:text-gray-200 py-4 rounded-3xl flex items-center justify-center gap-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-all font-bold shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 16 16"><g fill="none" fill-rule="evenodd" clip-rule="evenodd"><path fill="#F44336" d="M7.209 1.061c.725-.081 1.154-.081 1.933 0a6.57 6.57 0 0 1 3.65 1.82a100 100 0 0 0-1.986 1.93q-1.876-1.59-4.188-.734q-1.696.78-2.362 2.528a78 78 0 0 1-2.148-1.658a.26.26 0 0 0-.16-.027q1.683-3.245 5.26-3.86" opacity=".987"></path><path fill="#FFC107" d="M1.946 4.92q.085-.013.161.027a78 78 0 0 0 2.148 1.658A7.6 7.6 0 0 0 4.04 7.99q.037.678.215 1.331L2 11.116Q.527 8.038 1.946 4.92" opacity=".997"></path><path fill="#448AFF" d="M12.685 13.29a26 26 0 0 0-2.202-1.74q1.15-.812 1.396-2.228H8.122V6.713q3.25-.027 6.497.055q.616 3.345-1.423 6.032a7 7 0 0 1-.51.49" opacity=".999"></path><path fill="#43A047" d="M4.255 9.322q1.23 3.057 4.51 2.854a3.94 3.94 0 0 0 1.718-.626q1.148.812 2.202 1.74a6.62 6.62 0 0 1-4.027 1.684a6.4 6.4 0 0 1-1.02 0Q3.82 14.524 2 11.116z" opacity=".993"></path></g></svg>
                                <span>ادامه با حساب گوگل</span>
                            </button>
                        </div>
                    </div>

                    <div id="step-2" class="auth-step hidden opacity-0 scale-95 transition-all duration-300">
                        <div class="text-center mb-10">
                            <div class="relative w-16 h-16 lg:w-20 lg:h-20 mx-auto mb-6">
                                <div class="absolute inset-0 bg-secondary-500/20 rounded-[2rem] rotate-12 animate-pulse"></div>
                                <div class="relative w-full h-full bg-white dark:bg-gray-800 rounded-[2rem] flex items-center justify-center text-secondary-500 border border-secondary-100 dark:border-gray-700 shadow-xl">
                                    <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                </div>
                            </div>
                            <h3 class="text-2xl font-black text-gray-900 dark:text-white">تایید شماره</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-3 font-bold">کد ۵ رقمی به شماره <span class="text-brown-600 dark:text-brown-400" dir="ltr" id="displayPhone">0912***45</span> ارسال شد</p>
                        </div>

                        <div class="space-y-8">
                            <div id="otp-container" class="flex flex-row justify-center gap-2 lg:gap-3" dir="ltr">
                                <input type="text" inputmode="numeric" maxlength="1" class="otp-field w-11 lg:w-12 h-16 bg-gray-100/50 dark:bg-white/5 border-2 border-transparent focus:border-brown-500/40 focus:bg-white dark:focus:bg-gray-900 rounded-2xl text-center font-black text-2xl outline-none text-gray-900 dark:text-white transition-all shadow-inner">
                                <input type="text" inputmode="numeric" maxlength="1" class="otp-field w-11 lg:w-12 h-16 bg-gray-100/50 dark:bg-white/5 border-2 border-transparent focus:border-brown-500/40 focus:bg-white dark:focus:bg-gray-900 rounded-2xl text-center font-black text-2xl outline-none text-gray-900 dark:text-white transition-all shadow-inner">
                                <input type="text" inputmode="numeric" maxlength="1" class="otp-field w-11 lg:w-12 h-16 bg-gray-100/50 dark:bg-white/5 border-2 border-transparent focus:border-brown-500/40 focus:bg-white dark:focus:bg-gray-900 rounded-2xl text-center font-black text-2xl outline-none text-gray-900 dark:text-white transition-all shadow-inner">
                                <input type="text" inputmode="numeric" maxlength="1" class="otp-field w-11 lg:w-12 h-16 bg-gray-100/50 dark:bg-white/5 border-2 border-transparent focus:border-brown-500/40 focus:bg-white dark:focus:bg-gray-900 rounded-2xl text-center font-black text-2xl outline-none text-gray-900 dark:text-white transition-all shadow-inner">
                                <input type="text" inputmode="numeric" maxlength="1" class="otp-field w-11 lg:w-12 h-16 bg-gray-100/50 dark:bg-white/5 border-2 border-transparent focus:border-brown-500/40 focus:bg-white dark:focus:bg-gray-900 rounded-2xl text-center font-black text-2xl outline-none text-gray-900 dark:text-white transition-all shadow-inner">
                            </div>

                            <div class="space-y-4">
                                <button class="group relative w-full bg-brown-600 hover:bg-brown-700 text-white font-black py-5 rounded-[2rem] transition-all active:scale-[0.97] overflow-hidden shadow-lg shadow-brown-500/25">
                                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:animate-[shimmer_1.5s_infinite]"></div>
                                    <span class="relative text-lg">ورود به حساب کاربری</span>
                                </button>
                                <div class="flex flex-col items-center gap-4 pt-2">
                                    <button id="resend-btn" disabled class="text-xs font-black text-gray-400 transition-colors flex items-center gap-2">
                                        <span id="timer-text">ارسال مجدد کد (۵۹ ثانیه)</span>
                                    </button>
                                    <button onclick="goToStep(1)" class="text-xs font-black text-gray-400 hover:text-red-500 transition-colors">اصلاح شماره موبایل</button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="mt-10 text-center">
                    <p class="text-[11px] text-gray-400 font-bold">با ورود به سایت، تمامی <a href="#" class="text-brown-500 font-black border-b border-brown-500/20 pb-0.5">شرایط و قوانین</a> مانا را می‌پذیرم.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<!--END LOGIN MODAL-->

<!--CART DRAWER-->
<livewire:layout.cart />
<!--END CART DRAWER-->

<!--Mobile Menu-->
<div id="mobile-menu" class="fixed inset-0 z-[1100] pointer-events-none">
    <div class="absolute inset-0 bg-black/40 dark:bg-black/70 opacity-0 transition-opacity duration-500 backdrop-blur-sm menu-overlay cursor-pointer"></div>

    <div class="absolute right-0 top-0 h-full w-[320px] bg-white/70 dark:bg-gray-950/80 backdrop-blur-md shadow-[-15px_0_50px_rgba(0,0,0,0.2)] transform translate-x-full transition-transform duration-500 pointer-events-auto flex flex-col border-l border-white/40 dark:border-white/10">

        <div class="p-5 border-b border-white/40 dark:border-gray-800 flex justify-between items-center bg-white/30 dark:bg-gray-900/30">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-brown-600 rounded-lg flex items-center justify-center shadow-lg shadow-brown-500/40">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M4 6h16M4 12h16m-7 6h7" stroke-width="2.5" stroke-linecap="round"/>
                    </svg>
                </div>
                <span class="font-black text-xl dark:text-white">منوی اصلی</span>
            </div>
            <button class="close-menu p-2 hover:bg-red-50 dark:hover:bg-red-900/20 text-gray-400 hover:text-red-500 rounded-xl transition-all border border-transparent hover:border-red-100">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M6 18L18 6M6 6l12 12" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar pt-2">

            <div class="px-4 mb-6">
                <div onclick="openLoginModal()" class="p-4 rounded-[2rem] bg-gradient-to-br from-brown-600 to-indigo-700 text-white shadow-lg shadow-brown-500/20 flex items-center justify-between cursor-pointer group transition-all active:scale-95">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center border border-white/30">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-width="1.5"/>
                            </svg>
                        </div>
                        <div>
                            <span class="block font-black text-sm">ورود یا ثبت‌نام</span>
                            <span class="block text-[10px] opacity-70 mt-0.5">مشاهده پنل کاربری</span>
                        </div>
                    </div>
                    <svg class="w-5 h-5 opacity-50 group-hover:translate-x-[-5px] transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M15 19l-7-7 7-7" stroke-width="3"/>
                    </svg>
                </div>
            </div>

            <nav class="px-3 pb-20">
                <!--Product classification-->
                <div class="flex items-center gap-2 px-3 mb-3">
                    <span class="w-1 h-4 bg-brown-600 rounded-full"></span>
                    <span class="text-[11px] font-black text-gray-400 uppercase tracking-widest">دسته‌بندی کالاها</span>
                </div>

                <ul class="space-y-3">
                    <!--Digital goods-->
                    <li class="menu-item">
                        <button class="layer-btn w-full flex items-center justify-between p-4 rounded-2xl bg-white/40 dark:bg-white/5 border border-white/60 dark:border-white/10 shadow-sm transition-all hover:bg-white/60">
                            <div class="flex items-center gap-3 text-gray-800 dark:text-gray-200">
                                <svg class="w-5 h-5 text-brown-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke-width="1.5"/>
                                </svg>
                                <span class="font-black text-sm">کالای دیجیتال</span>
                            </div>
                            <svg class="w-4 h-4 text-gray-400 arrow-icon transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" stroke-width="3"/>
                            </svg>
                        </button>

                        <ul class="hidden submenu mt-2 mr-2 space-y-2 border-r-2 border-brown-500/20 pr-2 overflow-hidden transition-all duration-300">
                            <li>
                                <button class="layer-btn w-full flex items-center justify-between p-3 rounded-xl bg-white/30 dark:bg-white/5 border border-white/40 hover:bg-brown-50/50">
                                    <span class="font-bold text-xs text-gray-700 dark:text-gray-300">گوشی موبایل</span>
                                    <svg class="w-3 h-3 text-gray-400 arrow-icon transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 9l-7 7-7-7" stroke-width="3"/>
                                    </svg>
                                </button>

                                <ul class="hidden submenu mt-2 mr-2 space-y-2 border-r-2 border-gray-400/20 pr-2">
                                    <li>
                                        <button class="layer-btn w-full flex items-center justify-between p-2 rounded-lg bg-white/20 dark:bg-white/5 text-gray-600 dark:text-gray-400">
                                            <span class="font-bold text-[11px]">گوشی اپل (iPhone)</span>
                                            <svg class="w-3 h-3 text-gray-400 arrow-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="M19 9l-7 7-7-7" stroke-width="3"/>
                                            </svg>
                                        </button>

                                        <ul class="hidden submenu mt-1 mr-2 space-y-1 pr-4 bg-gray-50/50 dark:bg-black/20 rounded-lg">
                                            <li><a href="#" class="block p-3 text-[10px] text-gray-500 dark:text-gray-400 hover:text-brown-600 transition-colors">سری iPhone 15</a></li>
                                            <li><a href="#" class="block p-3 text-[10px] text-gray-500 dark:text-gray-400 hover:text-brown-600 transition-colors">سری iPhone 14</a></li>
                                            <li><a href="#" class="block p-3 text-[10px] text-gray-500 dark:text-gray-400 hover:text-brown-600 transition-colors">سری iPhone 13</a></li>
                                            <li><a href="#" class="block p-3 text-[10px] text-gray-500 dark:text-gray-400 hover:text-brown-600 transition-colors">سری iPhone 12</a></li>
                                            <li><a href="#" class="block p-3 text-[10px] text-gray-500 dark:text-gray-400 hover:text-brown-600 transition-colors">سری iPhone SE</a></li>
                                        </ul>
                                    </li>
                                    <li>
                                        <button class="layer-btn w-full flex items-center justify-between p-2 rounded-lg bg-white/20 dark:bg-white/5 text-gray-600 dark:text-gray-400">
                                            <span class="font-bold text-[11px]">گوشی سامسونگ</span>
                                            <svg class="w-3 h-3 text-gray-400 arrow-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="M19 9l-7 7-7-7" stroke-width="3"/>
                                            </svg>
                                        </button>

                                        <ul class="hidden submenu mt-1 mr-2 space-y-1 pr-4 bg-gray-50/50 dark:bg-black/20 rounded-lg">
                                            <li><a href="#" class="block p-3 text-[10px] text-gray-500 dark:text-gray-400 hover:text-brown-600 transition-colors">سری Galaxy S</a></li>
                                            <li><a href="#" class="block p-3 text-[10px] text-gray-500 dark:text-gray-400 hover:text-brown-600 transition-colors">سری Galaxy A</a></li>
                                            <li><a href="#" class="block p-3 text-[10px] text-gray-500 dark:text-gray-400 hover:text-brown-600 transition-colors">سری Galaxy Z</a></li>
                                        </ul>
                                    </li>
                                    <li>
                                        <a href="#" class="block p-2 pl-4 text-[10px] text-gray-500 dark:text-gray-400 hover:text-brown-600 transition-colors">گوشی شیائومی</a>
                                    </li>
                                    <li>
                                        <a href="#" class="block p-2 pl-4 text-[10px] text-gray-500 dark:text-gray-400 hover:text-brown-600 transition-colors">گوشی هوآوی</a>
                                    </li>
                                    <li>
                                        <a href="#" class="block p-2 pl-4 text-[10px] text-gray-500 dark:text-gray-400 hover:text-brown-600 transition-colors">گوشی انارد</a>
                                    </li>
                                </ul>
                            </li>
                            <li>
                                <button class="layer-btn w-full flex items-center justify-between p-3 rounded-xl bg-white/30 dark:bg-white/5 border border-white/40 hover:bg-brown-50/50">
                                    <span class="font-bold text-xs text-gray-700 dark:text-gray-300">لپ‌تاپ و کامپیوتر</span>
                                    <svg class="w-3 h-3 text-gray-400 arrow-icon transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 9l-7 7-7-7" stroke-width="3"/>
                                    </svg>
                                </button>

                                <ul class="hidden submenu mt-2 mr-2 space-y-2 border-r-2 border-gray-400/20 pr-2">
                                    <li><a href="#" class="block p-2 pl-4 text-[10px] text-gray-500 dark:text-gray-400 hover:text-brown-600 transition-colors">لپ‌تاپ گیمینگ</a></li>
                                    <li><a href="#" class="block p-2 pl-4 text-[10px] text-gray-500 dark:text-gray-400 hover:text-brown-600 transition-colors">لپ‌تاپ تجاری</a></li>
                                    <li><a href="#" class="block p-2 pl-4 text-[10px] text-gray-500 dark:text-gray-400 hover:text-brown-600 transition-colors">مک‌بوک اپل</a></li>
                                    <li><a href="#" class="block p-2 pl-4 text-[10px] text-gray-500 dark:text-gray-400 hover:text-brown-600 transition-colors">قطعات کامپیوتر</a></li>
                                </ul>
                            </li>
                            <li>
                                <a href="#" class="flex items-center justify-between p-3 rounded-xl bg-white/30 dark:bg-white/5 border border-white/40 hover:bg-brown-50/50">
                                    <span class="font-bold text-xs text-gray-700 dark:text-gray-300">هدفون و هندزفری</span>
                                </a>
                            </li>
                            <li>
                                <a href="#" class="flex items-center justify-between p-3 rounded-xl bg-white/30 dark:bg-white/5 border border-white/40 hover:bg-brown-50/50">
                                    <span class="font-bold text-xs text-gray-700 dark:text-gray-300">ساعت هوشمند</span>
                                </a>
                            </li>
                            <li>
                                <a href="#" class="flex items-center justify-between p-3 rounded-xl bg-white/30 dark:bg-white/5 border border-white/40 hover:bg-brown-50/50">
                                    <span class="font-bold text-xs text-gray-700 dark:text-gray-300">کنسول بازی</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!--Home and kitchen-->
                    <li class="menu-item">
                        <button class="layer-btn w-full flex items-center justify-between p-4 rounded-2xl bg-white/40 dark:bg-white/5 border border-white/60 dark:border-white/10 shadow-sm transition-all hover:bg-white/60">
                            <div class="flex items-center gap-3 text-gray-800 dark:text-gray-200">
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" stroke-width="1.5"/>
                                </svg>
                                <span class="font-black text-sm">خانه و آشپزخانه</span>
                            </div>
                            <svg class="w-4 h-4 text-gray-400 arrow-icon transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" stroke-width="3"/>
                            </svg>
                        </button>

                        <ul class="hidden submenu mt-2 mr-2 space-y-2 border-r-2 border-green-500/20 pr-2 overflow-hidden transition-all duration-300">
                            <li><a href="#" class="block p-3 text-xs text-gray-600 dark:text-gray-400 hover:text-green-600 transition-colors">لوازم آشپزخانه</a></li>
                            <li><a href="#" class="block p-3 text-xs text-gray-600 dark:text-gray-400 hover:text-green-600 transition-colors">مبلمان و دکوراسیون</a></li>
                            <li><a href="#" class="block p-3 text-xs text-gray-600 dark:text-gray-400 hover:text-green-600 transition-colors">سرویس خواب</a></li>
                            <li><a href="#" class="block p-3 text-xs text-gray-600 dark:text-gray-400 hover:text-green-600 transition-colors">نور و روشنایی</a></li>
                            <li><a href="#" class="block p-3 text-xs text-gray-600 dark:text-gray-400 hover:text-green-600 transition-colors">نظافت و بهداشت</a></li>
                        </ul>
                    </li>

                    <!--Fashion and clothing-->
                    <li class="menu-item">
                        <button class="layer-btn w-full flex items-center justify-between p-4 rounded-2xl bg-white/40 dark:bg-white/5 border border-white/60 dark:border-white/10 shadow-sm transition-all hover:bg-white/60">
                            <div class="flex items-center gap-3 text-gray-800 dark:text-gray-200">
                                <svg class="w-5 h-5 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-width="1.5"/>
                                </svg>
                                <span class="font-black text-sm">مد و پوشاک</span>
                            </div>
                            <svg class="w-4 h-4 text-gray-400 arrow-icon transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" stroke-width="3"/>
                            </svg>
                        </button>

                        <ul class="hidden submenu mt-2 mr-2 space-y-2 border-r-2 border-pink-500/20 pr-2 overflow-hidden transition-all duration-300">
                            <li><a href="#" class="block p-3 text-xs text-gray-600 dark:text-gray-400 hover:text-pink-600 transition-colors">لباس مردانه</a></li>
                            <li><a href="#" class="block p-3 text-xs text-gray-600 dark:text-gray-400 hover:text-pink-600 transition-colors">لباس زنانه</a></li>
                            <li><a href="#" class="block p-3 text-xs text-gray-600 dark:text-gray-400 hover:text-pink-600 transition-colors">کفش</a></li>
                            <li><a href="#" class="block p-3 text-xs text-gray-600 dark:text-gray-400 hover:text-pink-600 transition-colors">اکسسوری</a></li>
                            <li><a href="#" class="block p-3 text-xs text-gray-600 dark:text-gray-400 hover:text-pink-600 transition-colors">زیورآلات</a></li>
                        </ul>
                    </li>

                    <!--Beauty and health-->
                    <li>
                        <a href="#" class="flex items-center gap-3 p-4 rounded-2xl bg-white/40 dark:bg-white/5 border border-white/60 dark:border-white/10 shadow-sm text-gray-800 dark:text-gray-200 font-black text-sm">
                            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="1.5"/>
                            </svg>
                            زیبایی و سلامت
                        </a>
                    </li>

                    <!--Sports and travel-->
                    <li>
                        <a href="#" class="flex items-center gap-3 p-4 rounded-2xl bg-white/40 dark:bg-white/5 border border-white/60 dark:border-white/10 shadow-sm text-gray-800 dark:text-gray-200 font-black text-sm">
                            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" stroke-width="1.5"/>
                            </svg>
                            ورزش و سفر
                        </a>
                    </li>

                    <!--Books and stationery-->
                    <li>
                        <a href="#" class="flex items-center gap-3 p-4 rounded-2xl bg-white/40 dark:bg-white/5 border border-white/60 dark:border-white/10 shadow-sm text-gray-800 dark:text-gray-200 font-black text-sm">
                            <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" stroke-width="1.5"/>
                            </svg>
                            کتاب و لوازم تحریر
                        </a>
                    </li>

                    <!--The amazing-->
                    <li>
                        <a href="#" class="flex items-center gap-3 p-4 rounded-2xl bg-white/40 dark:bg-white/5 border border-white/60 dark:border-white/10 shadow-sm text-gray-800 dark:text-gray-200 font-black text-sm">
                            <svg class="w-5 h-5 text-secondary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M13 10V3L4 14h7v7l9-11h-7z" stroke-width="1.5"/>
                            </svg>
                            شگفت‌انگیزها
                        </a>
                    </li>

                    <!--Special sale-->
                    <li>
                        <a href="#" class="flex items-center gap-3 p-4 rounded-2xl bg-gradient-to-r from-red-500 to-secondary-500 text-white shadow-lg shadow-red-500/20">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" stroke-width="1.5"/>
                            </svg>
                            <span class="font-black text-sm">فروش ویژه</span>
                        </a>
                    </li>
                </ul>

                <!--Customer service-->
                <div class="flex items-center gap-2 px-3 mt-8 mb-3">
                    <span class="w-1 h-4 bg-brown-600 rounded-full"></span>
                    <span class="text-[11px] font-black text-gray-400 uppercase tracking-widest">خدمات مشتریان</span>
                </div>

                <ul class="space-y-3 mb-6">
                    <li>
                        <a href="#" class="flex items-center gap-3 p-3 rounded-xl bg-white/40 dark:bg-white/5 border border-white/60 dark:border-white/10 text-gray-700 dark:text-gray-300 text-xs">
                            <svg class="w-4 h-4 text-brown-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" stroke-width="1.5"/>
                            </svg>
                            پشتیبانی 24 ساعته
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center gap-3 p-3 rounded-xl bg-white/40 dark:bg-white/5 border border-white/60 dark:border-white/10 text-gray-700 dark:text-gray-300 text-xs">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="1.5"/>
                            </svg>
                            سوالات متداول
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center gap-3 p-3 rounded-xl bg-white/40 dark:bg-white/5 border border-white/60 dark:border-white/10 text-gray-700 dark:text-gray-300 text-xs">
                            <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" stroke-width="1.5"/>
                            </svg>
                            گارانتی و ضمانت
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center gap-3 p-3 rounded-xl bg-white/40 dark:bg-white/5 border border-white/60 dark:border-white/10 text-gray-700 dark:text-gray-300 text-xs">
                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M3 10h11M3 14h7m10-8v8a2 2 0 01-2 2h-4.586l-1.707 1.707a1 1 0 01-1.414 0L7.586 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2z" stroke-width="1.5"/>
                            </svg>
                            بازگرداندن کالا
                        </a>
                    </li>
                </ul>

                <!--Contact information-->
                <div class="mt-8 pt-6 border-t border-white/40 dark:border-gray-800 space-y-6">
                    <div class="flex flex-col gap-3 px-3">
                        <a href="tel:0210000" class="flex items-center gap-3 text-xs font-bold text-gray-500 dark:text-gray-400">
                            <svg class="w-5 h-5 p-1 bg-white dark:bg-gray-800 rounded-lg shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" stroke-width="1.5"/>
                            </svg>
                            پشتیبانی: 123456-021
                        </a>
                        <a href="mailto:info@digikala.com" class="flex items-center gap-3 text-xs font-bold text-gray-500 dark:text-gray-400">
                            <svg class="w-5 h-5 p-1 bg-white dark:bg-gray-800 rounded-lg shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke-width="1.5"/>
                            </svg>
                            ایمیل: info@digikala.com
                        </a>
                    </div>

                    <!-- شبکه‌های اجتماعی -->
                    <div class="flex gap-4">
                        <a href="#" class="w-12 h-12 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800 flex items-center justify-center text-gray-400 hover:text-brown-600 hover:border-brown-600 transition-all group">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"></path>
                            </svg>
                        </a>
                        <a href="#" class="w-12 h-12 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800 flex items-center justify-center text-gray-400 hover:text-brown-400 hover:border-brown-400 transition-all group">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"></path>
                            </svg>
                        </a>
                        <a href="#" class="w-12 h-12 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800 flex items-center justify-center text-gray-400 hover:text-brown-400 hover:border-brown-400 transition-all group">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 64 64">
                                <path fill="currentColor" d="m62.8 10.8l-9.4 44c-.7 3.1-2.5 3.8-5.1 2.4L34.2 46.8l-6.9 6.6c-.7.7-1.4 1.4-3 1.4l1.1-14.5l26.3-23.9c1.1-1.1-.3-1.5-1.7-.6L17.3 36.4L3.2 32.1c-3.1-1-3.1-3.1.7-4.5L58.7 6.3c2.7-.8 5 .6 4.1 4.5"/>
                            </svg>
                        </a>
                        <a href="#" class="w-12 h-12 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800 flex items-center justify-center text-gray-400 hover:text-brown-400 hover:border-brown-400 transition-all group">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24">
                                <!-- Icon from Tabler Icons by Paweł Kuna - https://github.com/tabler/tabler-icons/blob/master/LICENSE -->
                                <path fill="currentColor" d="M18 3a5 5 0 0 1 5 5v8a5 5 0 0 1-5 5H6a5 5 0 0 1-5-5V8a5 5 0 0 1 5-5zM9 9v6a1 1 0 0 0 1.514.857l5-3a1 1 0 0 0 0-1.714l-5-3A1 1 0 0 0 9 9"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </nav>
        </div>
    </div>
</div>
<!--END Mobile Menu-->

<script src="{{asset('main/js/plugin/swiper/swiper-bundle.min.js')}}"></script>
<script src="{{asset('main/js/dependencies/swiper-script.js')}}"></script>
<script src="{{asset('main/js/plugin/sweetalert/sweetalert.min.js')}}"></script>

<script src="{{asset('main/js/dependencies/app.js')}}"></script>
<script src="{{asset('dashboard')}}/libs/sweetalert2/sweetalert2@11"></script>

<!-- INITIAL STORY SECTION -->
@livewireScripts
@stack('scripts')
<script>
    document.addEventListener('livewire:init', () => {

        Livewire.on('alert', (event) => {

            Swal.fire({
                position: 'top-end',
                icon: event.type ?? 'success',
                title: event.title ?? '',
                text: event.message ?? '',

                showConfirmButton: false,
                customClass: {
                    popup: 'my-swal-popup'
                },
                timer: 2000,
                toast: true
            });

        });

    });
</script>

</body>

</html>
