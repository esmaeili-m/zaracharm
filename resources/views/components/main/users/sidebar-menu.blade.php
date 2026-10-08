{{--
    منوی پنل کاربر (یک نسخه برای دسکتاپ و منوی کشویی موبایل)
    props: user, tab, menu, level, ordersCount, siteName
--}}
@props(['user', 'tab', 'menu', 'level' => null, 'ordersCount' => 0, 'siteName' => null])

@php
    $displayName = trim((string) $user->full_name) ?: ($user->mobile ?? 'کاربر');
@endphp

<div class="relative overflow-hidden bg-white/40 dark:bg-gray-950/60 backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-6 shadow-[0_20px_50px_rgba(0,0,0,0.05)] dark:shadow-none">

    <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary-500/10 rounded-full blur-3xl"></div>

    <div class="relative flex flex-col items-center text-center pb-8 mb-6 border-b border-gray-200/30 dark:border-white/5">
        <div class="relative mb-4">
            <div class="w-24 h-24 rounded-[2rem] bg-gradient-to-tr from-primary-500/20 to-primary-500/5 p-1 backdrop-blur-md border border-white/50">
                <img src="{{ $user->avatar_url }}" alt="{{ $displayName }}" class="w-full h-full rounded-[1.8rem] object-cover">
            </div>
            <div class="absolute -bottom-1 -right-1 w-7 h-7 {{ $user->status ? 'bg-emerald-500' : 'bg-red-500' }} border-4 border-white/80 dark:border-gray-900 rounded-2xl flex items-center justify-center shadow-lg"
                 title="{{ $user->status ? 'حساب فعال' : 'حساب غیرفعال' }}">
                @if($user->status)
                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                @else
                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 6l12 12M18 6L6 18"/></svg>
                @endif
            </div>
        </div>
        <h3 class="text-base font-black text-gray-900 dark:text-white">{{ $displayName }}</h3>
        @if($user->mobile && $displayName !== $user->mobile)
            <span class="text-[11px] font-bold text-gray-400 mt-1 tabular-nums" dir="ltr">{{ $user->mobile }}</span>
        @endif
        @if($level)
            <span class="inline-flex items-center px-3 py-1 mt-2 rounded-full bg-primary-500/10 text-primary-600 dark:text-primary-400 text-[10px] font-black">سطح {{ $level }}</span>
        @endif
    </div>

    <nav class="space-y-2 relative">
        @foreach($menu as $key => $item)
            @php $active = $tab === $key; @endphp
            <a wire:click.prevent="changeStatus('{{ $key }}')" href="{{ route('user.dashboard', ['tab' => $key]) }}"
               class="flex items-center justify-between gap-4 px-5 py-4 rounded-2xl transition-all group
               {{ $active ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/30 scale-[1.02]' : 'text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500' }}">
                <span class="flex items-center gap-4">
                    <svg class="w-5 h-5 {{ $active ? '' : 'group-hover:scale-110 transition-transform' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @foreach((array) $item['icon'] as $path)
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $path }}"/>
                        @endforeach
                    </svg>
                    <span class="text-xs font-black">{{ $item['title'] }}</span>
                </span>
                @if($key === 'orders' && $ordersCount)
                    <span class="min-w-5 h-5 px-1 flex items-center justify-center rounded-lg text-[10px] font-black
                        {{ $active ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-white/10' }}">{{ $ordersCount }}</span>
                @endif
            </a>
        @endforeach

        {{-- پنل مدیریت (فقط کاربرانی که دسترسی ورود به پنل دارند) --}}
        @can('settings.dashboard')
            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500 group">
                <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                </svg>
                <span class="text-xs font-black">پنل مدیریت</span>
            </a>
        @endcan

        {{-- خروج (POST با CSRF) --}}
        <div class="pt-4 mt-4 border-t border-gray-200/30 dark:border-white/5">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-4 px-5 py-4 rounded-2xl text-red-500 hover:bg-red-500/10 transition-all group">
                    <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span class="text-xs font-black">خروج از حساب</span>
                </button>
            </form>
        </div>
    </nav>
</div>

{{-- مرکز پشتیبانی --}}
<div class="p-6 rounded-[2.5rem] bg-white/10 dark:bg-primary-500/5 backdrop-blur-md border border-white/40 dark:border-white/10 relative overflow-hidden group shadow-[0_20px_40px_rgba(0,0,0,0.05)]">
    <div class="absolute -top-10 -right-10 w-32 h-32 bg-primary-500/20 rounded-full blur-md group-hover:bg-primary-500/30 transition-all duration-700"></div>

    <div class="relative z-10">
        <div class="w-12 h-12 rounded-2xl bg-primary-500/20 backdrop-blur-sm border border-primary-500/30 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform duration-500">
            <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
        </div>

        <h4 class="text-gray-900 dark:text-white text-[14px] font-black mb-1">مرکز پشتیبانی{{ $siteName ? ' ' . $siteName : '' }}</h4>
        <p class="text-gray-500 dark:text-gray-400 text-[10px] font-bold mb-5 leading-5">سوالی دارید؟ با ثبت تیکت، تیم پشتیبانی پاسخ شما را می‌دهد.</p>

        <button type="button" wire:click="changeStatus('tickets')"
                class="w-full py-3.5 bg-primary-500 text-white dark:bg-primary-500/20 dark:text-primary-400 dark:border dark:border-primary-500/30 rounded-2xl text-[11px] font-black transition-all active:scale-95 shadow-lg shadow-primary-500/20 hover:shadow-primary-500/40">
            ثبت تیکت پشتیبانی
        </button>
    </div>

    <div class="absolute -bottom-12 -left-12 w-28 h-28 bg-primary-400/10 rounded-full blur-md group-hover:scale-110 transition-transform duration-1000"></div>
</div>
