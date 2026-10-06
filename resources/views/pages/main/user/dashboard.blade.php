<?php

use Livewire\Component;

new class extends Component
{

    public $status = 1;
    public $user ;

    public function mount()
    {
        $this->user = auth()->user();

        $this->user->load([
            'wallet',
            'orders',
            'cart.items',
        ]);

        // امکان باز کردن مستقیم یک بخش، مثل ?tab=wishlist
        $this->changeStatus(request()->query('tab', 'dashboard'));
    }
    public function changeStatus($status)
    {

        $this->status = match ($status) {
            'dashboard' => 1,
            'orders'    => 2,
            'wallet'    => 3,
            'settings'  => 4,
            'tickets' => 5,
            'wishlist'  => 6,
            default     => 1,
        };

    }

    public function logout()
    {
        \Illuminate\Support\Facades\Auth::logout();

        return redirect()->route('login');
    }
};
?>

<div>
    <section class=" mx-auto py-10 px-4" dir="rtl">
        <div class="flex flex-col lg:flex-row gap-8">

            <!--Sidebar-->
            <aside class="w-full lg:block hidden lg:w-80 flex-shrink-0" dir="rtl">
                <div class="sticky top-10 space-y-6">

                    <!-- Item Menu -->
                    <div class="relative overflow-hidden bg-white/40 dark:bg-gray-950/60 backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-6 shadow-[0_20px_50px_rgba(0,0,0,0.05)] dark:shadow-none">

                        <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary-500/10 rounded-full blur-3xl"></div>

                        <div class="relative flex flex-col items-center text-center pb-8 mb-6 border-b border-gray-200/30 dark:border-white/5">
                            <div class="relative mb-4">
                                <div class="w-24 h-24 rounded-[2rem] bg-gradient-to-tr from-primary-500/20 to-primary-500/5 p-1 backdrop-blur-md border border-white/50">
                                    <img src="{{auth()->user()->avatarUrl }}" alt="User" class="w-full h-full rounded-[1.8rem] object-cover">
                                </div>
                                <div class="absolute -bottom-1 -right-1 w-7 h-7
                            {{ auth()->user()->status ? 'bg-emerald-500' : 'bg-red-500' }}
                            border-4 border-white/80 dark:border-gray-900 rounded-2xl
                            flex items-center justify-center shadow-lg">

                                    @if(auth()->user()->status)
                                        {{-- Active --}}
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                  d="M5 13l4 4L19 7"/>
                                        </svg>
                                    @else
                                        {{-- Inactive --}}
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                  d="M6 6l12 12M18 6L6 18"/>
                                        </svg>
                                    @endif
                                </div>
                            </div>
                            <h3 class="text-base font-black text-gray-900 dark:text-white">{{auth()->user()?->name ?? auth()->user()->phone}}</h3>
                            <span class="inline-flex items-center px-3 py-1 mt-2 rounded-full bg-primary-500/10 text-primary-600 dark:text-primary-400 text-[10px] font-black">کاربر سطح عادی</span>
                        </div>

                        <nav class="space-y-2 relative">

                            {{-- Dashboard --}}
                            <a
                                wire:click.prevent="changeStatus('dashboard')"
                                href="#"
                                class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all
        {{ $status === 1
            ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/30 scale-[1.02]'
            : 'text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500' }}
        group"
                            >
                                <svg class="w-5 h-5 {{ $status === 1 ? 'opacity-90' : 'group-hover:scale-110 transition-transform' }}"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                                    </path>
                                </svg>

                                <span class="text-xs font-black">پیشخوان</span>
                            </a>


                            {{-- Orders --}}
                            <a
                                wire:click.prevent="changeStatus('orders')"
                                href="#"
                                class="flex items-center justify-between px-5 py-4 rounded-2xl transition-all
        {{ $status === 2
            ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/30 scale-[1.02]'
            : 'text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500' }}
        group"
                            >

                                <div class="flex items-center gap-4">

                                    <svg class="w-5 h-5 {{ $status === 2 ? '' : 'group-hover:scale-110 transition-transform' }}"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z">
                                        </path>
                                    </svg>

                                    <span class="text-xs font-black">
                سفارش‌های من
            </span>

                                </div>

                                <span class="w-5 h-5 flex items-center justify-center rounded-lg
            {{ $status === 2
                ? 'bg-white/20 text-white'
                : 'bg-gray-100 dark:bg-white/10 text-[10px] font-black' }}
            group-hover:bg-primary-500 group-hover:text-white transition-colors">
            {{ $user->orders->count() }}
        </span>

                            </a>


                            {{-- Wallet --}}
                            <a
                                wire:click.prevent="changeStatus('wallet')"
                                href="#"
                                class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all
        {{ $status === 3
            ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/30 scale-[1.02]'
            : 'text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500' }}
        group"
                            >

                                <svg class="w-5 h-5 {{ $status === 3 ? '' : 'group-hover:scale-110 transition-transform' }}"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round"
                                          stroke-linejoin="round"
                                          stroke-width="2"
                                          d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2m0 0h3a1 1 0 001-1v-4a1 1 0 00-1-1h-3v6zm0 0h-1">
                                    </path>
                                </svg>

                                <span class="text-xs font-black">
            کیف پول و تراکنش‌ها
        </span>

                            </a>


                            {{-- Settings --}}
                            <a
                                wire:click="changeStatus('settings')"
                                href="#"
                                class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all
        {{ $status === 4
            ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/30 scale-[1.02]'
            : 'text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500' }}
        group"
                            >
                                <svg class="w-5 h-5 {{ $status === 4 ? '' : 'group-hover:scale-110 transition-transform' }} transition-transform duration-700 group-hover:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>


                                <span class="text-xs font-black">
            تنظیمات حساب
        </span>

                            </a>



                            {{-- Notifications --}}
                            <a
                                wire:click.prevent="changeStatus('tickets')"
                                href="#"
                                class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all
        {{ $status === 5
            ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/30 scale-[1.02]'
            : 'text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500' }}
        group"
                            >

                                <svg
                                    class="w-5 h-5 {{ $status === 5 ? '' : 'group-hover:animate-bounce' }}"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 10h8M8 14h5"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 11.5a7.5 7.5 0 01-7.5 7.5H8l-4 2 1.5-4.5A7.5 7.5 0 1119 11.5z"
                                    />
                                </svg>

                                <span class="text-xs font-black">
            تیکت ها
        </span>

                            </a>


                            {{-- Wishlist --}}
                            <a wire:click.prevent="changeStatus('wishlist')" href="#"
                               class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all
                            {{ $status === 6 ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/30 scale-[1.02]' : 'text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500' }} group">
                                <svg class="w-5 h-5 {{ $status === 6 ? '' : 'group-hover:scale-110 transition-transform' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                                <span class="text-xs font-black">علاقه‌مندی‌ها</span>
                            </a>

                            {{-- Admin panel (فقط کاربرانی که دسترسی ورود به پنل دارند) --}}
                            @can('settings.dashboard')
                            <a href="{{ route('dashboard') }}"
                               class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500 group">
                                <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                                </svg>
                                <span class="text-xs font-black">پنل مدیریت</span>
                            </a>
                            @endcan

                            {{-- Logout --}}
                            <div class="pt-4 mt-4 border-t border-gray-200/30 dark:border-white/5">

                                <a wire:click="logout" href="#"
                                   class="flex items-center gap-4 px-5 py-4 rounded-2xl text-red-500 hover:bg-red-500/10 transition-all group">

                                    <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform"
                                         fill="none"
                                         stroke="currentColor"
                                         viewBox="0 0 24 24">

                                        <path stroke-linecap="round"
                                              stroke-linejoin="round"
                                              stroke-width="2"
                                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                                        </path>

                                    </svg>

                                    <span class="text-xs font-black">
                خروج از حساب
            </span>

                                </a>

                            </div>

                        </nav>
                    </div>

                    <!-- Support Center -->
                    <div class="p-6 rounded-[2.5rem] bg-white/10 dark:bg-primary-500/5 backdrop-blur-md border border-white/40 dark:border-white/10 relative overflow-hidden group shadow-[0_20px_40px_rgba(0,0,0,0.05)]">
                        <div class="absolute -top-10 -right-10 w-32 h-32 bg-primary-500/20 rounded-full blur-md group-hover:bg-primary-500/30 transition-all duration-700"></div>

                        <div class="relative z-10">
                            <div class="w-12 h-12 rounded-2xl bg-primary-500/20 backdrop-blur-sm border border-primary-500/30 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform duration-500">
                                <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>

                            <h4 class="text-gray-900 dark:text-white text-[14px] font-black mb-1">مرکز پشتیبانی زاراچرم</h4>
                            <p class="text-gray-500 dark:text-gray-400 text-[10px] font-bold mb-5 leading-5">سوالی دارید؟ تیم ما آماده پاسخگویی سریع به شماست.</p>

                            <button class="w-full py-3.5 bg-primary-500 text-white dark:bg-primary-500/20 dark:text-primary-400 dark:border dark:border-primary-500/30 rounded-2xl text-[11px] font-black transition-all active:scale-95 shadow-lg shadow-primary-500/20 hover:shadow-primary-500/40">
                                گفتگوی آنلاین
                            </button>
                        </div>

                        <div class="absolute -bottom-12 -left-12 w-28 h-28 bg-primary-400/10 rounded-full blur-md group-hover:scale-110 transition-transform duration-1000"></div>
                    </div>

                </div>
            </aside>

            <!-- Sidebar Showing in Responsive Break Point -->
            <!--Open Sidebar button on mobile-->
            <div class="fixed bottom-28 right-6 z-[95] lg:hidden">
                <button onclick="toggleFilters(true)" class="flex items-center justify-center w-14 h-14 bg-white/40 dark:bg-white/[0.05] backdrop-blur-md text-blue-600 rounded-2xl shadow-lg border border-white/60 dark:border-white/10 active:scale-90 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M12 17.25h8.25"></path>
                    </svg>
                </button>
            </div>

            <!--Overlay for mobile Sidebar-->
            <div id="filter-overlay" wire:ignore.self class="fixed inset-0 bg-black/30 backdrop-blur-sm z-[140] opacity-0 pointer-events-none transition-opacity duration-300 lg:hidden"></div>

            <!--Offcanvas Sidebar for Mobile-->
            <div id="filter-offcanvas" wire:ignore.self onclick="if (event.target.closest('nav a')) toggleFilters(false)" class="fixed top-0 right-0 h-full w-[85%] max-w-[380px] bg-white/30 dark:bg-black/40 backdrop-blur-[30px] z-[150] translate-x-full transition-transform duration-500 ease-in-out border-l border-white/40 dark:border-white/10 shadow-lg lg:hidden">
                <div class="flex flex-col h-full">
                    <div class="p-6 flex items-center justify-between border-b border-white/40 dark:border-white/5 bg-white/20 dark:bg-white/[0.02]">
                        <h2 class="text-lg font-black text-gray-900 dark:text-white">پنل کاربری</h2>
                        <button onclick="toggleFilters(false)" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-white/40 dark:bg-white/5 text-gray-600 dark:text-gray-300 border border-white/60 dark:border-white/10">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-8 custom-scrollbar">
                        <div class="sticky top-10 space-y-6">

                            {{-- Item Menu --}}
                            <div class="relative overflow-hidden bg-white/40 dark:bg-gray-950/60 backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-6 shadow-[0_20px_50px_rgba(0,0,0,0.05)] dark:shadow-none">

                                <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary-500/10 rounded-full blur-3xl"></div>

                                <div class="relative flex flex-col items-center text-center pb-8 mb-6 border-b border-gray-200/30 dark:border-white/5">
                                    <div class="relative mb-4">
                                        <div class="w-24 h-24 rounded-[2rem] bg-gradient-to-tr from-primary-500/20 to-primary-500/5 p-1 backdrop-blur-md border border-white/50">
                                            <img src="{{ auth()->user()->avatarUrl }}" alt="User" class="w-full h-full rounded-[1.8rem] object-cover">
                                        </div>
                                        <div class="absolute -bottom-1 -right-1 w-7 h-7 {{ auth()->user()->status ? 'bg-emerald-500' : 'bg-red-500' }} border-4 border-white/80 dark:border-gray-900 rounded-2xl flex items-center justify-center shadow-lg">
                                            @if(auth()->user()->status)
                                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            @else
                                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 6l12 12M18 6L6 18"/>
                                                </svg>
                                            @endif
                                        </div>
                                    </div>
                                    <h3 class="text-base font-black text-gray-900 dark:text-white">{{ auth()->user()?->name ?? auth()->user()->phone }}</h3>
                                    <span class="inline-flex items-center px-3 py-1 mt-2 rounded-full bg-primary-500/10 text-primary-600 dark:text-primary-400 text-[10px] font-black">کاربر سطح عادی</span>
                                </div>

                                <nav class="space-y-2 relative">

                                    {{-- Dashboard --}}
                                    <a wire:click.prevent="changeStatus('dashboard')" href="#"
                                       class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all
           {{ $status === 1 ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/30 scale-[1.02]' : 'text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500' }} group">
                                        <svg class="w-5 h-5 {{ $status === 1 ? 'opacity-90' : 'group-hover:scale-110 transition-transform' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                        </svg>
                                        <span class="text-xs font-black">پیشخوان</span>
                                    </a>

                                    {{-- Orders --}}
                                    <a wire:click.prevent="changeStatus('orders')" href="#"
                                       class="flex items-center justify-between px-5 py-4 rounded-2xl transition-all
           {{ $status === 2 ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/30 scale-[1.02]' : 'text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500' }} group">
                                        <div class="flex items-center gap-4">
                                            <svg class="w-5 h-5 {{ $status === 2 ? '' : 'group-hover:scale-110 transition-transform' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                            </svg>
                                            <span class="text-xs font-black">سفارش‌های من</span>
                                        </div>
                                        <span class="w-5 h-5 flex items-center justify-center rounded-lg
                {{ $status === 2 ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-white/10 text-[10px] font-black' }}
                group-hover:bg-primary-500 group-hover:text-white transition-colors">
                {{ $user->orders->count() }}
            </span>
                                    </a>

                                    {{-- Wallet --}}
                                    <a wire:click.prevent="changeStatus('wallet')" href="#"
                                       class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all
           {{ $status === 3 ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/30 scale-[1.02]' : 'text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500' }} group">
                                        <svg class="w-5 h-5 {{ $status === 3 ? '' : 'group-hover:scale-110 transition-transform' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2m0 0h3a1 1 0 001-1v-4a1 1 0 00-1-1h-3v6zm0 0h-1"/>
                                        </svg>
                                        <span class="text-xs font-black">کیف پول و تراکنش‌ها</span>
                                    </a>

                                    {{-- Settings --}}
                                    <a wire:click.prevent="changeStatus('settings')" href="#"
                                       class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all
           {{ $status === 4 ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/30 scale-[1.02]' : 'text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500' }} group">
                                        <svg class="w-5 h-5 transition-transform duration-700 group-hover:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span class="text-xs font-black">تنظیمات حساب</span>
                                    </a>

                                    {{-- Tickets --}}
                                    <a wire:click.prevent="changeStatus('tickets')" href="#"
                                       class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all
           {{ $status === 5 ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/30 scale-[1.02]' : 'text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500' }} group">
                                        <svg class="w-5 h-5 {{ $status === 5 ? '' : 'group-hover:animate-bounce' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8M8 14h5"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11.5a7.5 7.5 0 01-7.5 7.5H8l-4 2 1.5-4.5A7.5 7.5 0 1119 11.5z"/>
                                        </svg>
                                        <span class="text-xs font-black">تیکت ها</span>
                                    </a>

                                    {{-- Wishlist --}}
                                    <a wire:click.prevent="changeStatus('wishlist')" href="#"
                                       class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all
                                    {{ $status === 6 ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/30 scale-[1.02]' : 'text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500' }} group">
                                        <svg class="w-5 h-5 {{ $status === 6 ? '' : 'group-hover:scale-110 transition-transform' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                        </svg>
                                        <span class="text-xs font-black">علاقه‌مندی‌ها</span>
                                    </a>

                                    {{-- Admin panel (فقط کاربرانی که دسترسی ورود به پنل دارند) --}}
                                    @can('settings.dashboard')
                                    <a href="{{ route('dashboard') }}"
                                       class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all text-gray-600 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/5 hover:text-primary-500 group">
                                        <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                                        </svg>
                                        <span class="text-xs font-black">پنل مدیریت</span>
                                    </a>
                                    @endcan

                                    {{-- Logout --}}
                                    <div class="pt-4 mt-4 border-t border-gray-200/30 dark:border-white/5">
                                        <a wire:click.prevent="logout" href="#"
                                           class="flex items-center gap-4 px-5 py-4 rounded-2xl text-red-500 hover:bg-red-500/10 transition-all group">
                                            <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                            </svg>
                                            <span class="text-xs font-black">خروج از حساب</span>
                                        </a>
                                    </div>

                                </nav>
                            </div>

                            {{-- Support Center --}}
                            <div class="p-6 rounded-[2.5rem] bg-white/10 dark:bg-primary-500/5 backdrop-blur-md border border-white/40 dark:border-white/10 relative overflow-hidden group shadow-[0_20px_40px_rgba(0,0,0,0.05)]">
                                <div class="absolute -top-10 -right-10 w-32 h-32 bg-primary-500/20 rounded-full blur-md group-hover:bg-primary-500/30 transition-all duration-700"></div>

                                <div class="relative z-10">
                                    <div class="w-12 h-12 rounded-2xl bg-primary-500/20 backdrop-blur-sm border border-primary-500/30 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform duration-500">
                                        <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                                        </svg>
                                    </div>

                                    <h4 class="text-gray-900 dark:text-white text-[14px] font-black mb-1">مرکز پشتیبانی زاراچرم</h4>
                                    <p class="text-gray-500 dark:text-gray-400 text-[10px] font-bold mb-5 leading-5">سوالی دارید؟ تیم ما آماده پاسخگویی سریع به شماست.</p>

                                    <button class="w-full py-3.5 bg-primary-500 text-white dark:bg-primary-500/20 dark:text-primary-400 dark:border dark:border-primary-500/30 rounded-2xl text-[11px] font-black transition-all active:scale-95 shadow-lg shadow-primary-500/20 hover:shadow-primary-500/40">
                                        گفتگوی آنلاین
                                    </button>
                                </div>

                                <div class="absolute -bottom-12 -left-12 w-28 h-28 bg-primary-400/10 rounded-full blur-md group-hover:scale-110 transition-transform duration-1000"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <!-- End Sidebar Showing in Responsive Break Point -->

            <main class="flex-1 space-y-8">
                @if($status == 1)
                    <livewire:main.users.dashboard :user="$user" />

                @elseif($status == 2)
                    <livewire:main.users.orders :user="$user"/>

                @elseif($status == 3)
                    <livewire:main.users.wallet :user="$user"/>

                @elseif($status == 4)
                    <livewire:main.users.settings :user="$user"/>

                @elseif($status == 5)
                    <livewire:main.users.tickets :user="$user"/>

                @elseif($status == 6)
                    <livewire:main.users.wishlist :user="$user"/>
                @endif
            </main>

        </div>
    </section>
</div>
