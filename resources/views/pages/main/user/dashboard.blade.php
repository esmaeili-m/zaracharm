<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use App\Models\RewardLevel;

new class extends Component
{
    // بخش فعال؛ در آدرس صفحه می‌ماند (?tab=orders) تا رفرش/لینک مستقیم همان بخش را باز کند
    #[Url(except: 'dashboard')]
    public string $tab = 'dashboard';

    public $user;

    public const TABS = ['dashboard', 'orders', 'wallet', 'settings', 'tickets', 'wishlist'];

    public function mount()
    {
        $this->user = auth()->user();

        $this->changeStatus($this->tab);
    }

    #[On('user-panel-tab')]
    public function changeStatus($status)
    {
        $this->tab = in_array($status, self::TABS, true) ? $status : 'dashboard';
    }

    // منوی پنل (عنوان + مسیرهای آیکن)
    public function menu(): array
    {
        return [
            'dashboard' => ['title' => 'پیشخوان', 'icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
            'orders' => ['title' => 'سفارش‌های من', 'icon' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'],
            'wallet' => ['title' => 'کیف پول و تراکنش‌ها', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2m0 0h3a1 1 0 001-1v-4a1 1 0 00-1-1h-3v6zm0 0h-1'],
            'settings' => ['title' => 'تنظیمات حساب', 'icon' => [
                'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
                'M15 12a3 3 0 11-6 0 3 3 0 016 0z',
            ]],
            'tickets' => ['title' => 'تیکت‌ها', 'icon' => ['M8 10h8M8 14h5', 'M19 11.5a7.5 7.5 0 01-7.5 7.5H8l-4 2 1.5-4.5A7.5 7.5 0 1119 11.5z']],
            'wishlist' => ['title' => 'علاقه‌مندی‌ها', 'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'],
        ];
    }

    /** سطح باشگاه مشتریان (همان محاسبه بخش کیف پول) */
    public function rewardLevel(): ?string
    {
        $points = (int) $this->user->rewardPointTransactions()->sum('amount');

        return RewardLevel::query()
            ->where('is_active', true)
            ->where('min_points', '<=', $points)
            ->orderByDesc('min_points')
            ->value('name');
    }

    public function siteName(): ?string
    {
        $settings = \Illuminate\Support\Facades\Cache::remember('settings', 3600, fn () => \App\Models\Setting::pluck('value', 'key')->toArray());

        return $settings['site_name'] ?? null;
    }
};
?>

<div>
    @php
        $sidebar = [
            'user' => $user,
            'tab' => $tab,
            'menu' => $this->menu(),
            'level' => $this->rewardLevel(),
            'ordersCount' => $user->orders()->count(),
            'siteName' => $this->siteName(),
        ];
    @endphp

    <section class="mx-auto py-10 px-4" dir="rtl">
        <div class="flex flex-col lg:flex-row gap-8">

            {{-- Sidebar --}}
            <aside class="w-full lg:block hidden lg:w-80 flex-shrink-0" dir="rtl">
                <div class="sticky top-10 space-y-6">
                    <x-main.users.sidebar-menu :user="$sidebar['user']" :tab="$sidebar['tab']" :menu="$sidebar['menu']"
                                               :level="$sidebar['level']" :orders-count="$sidebar['ordersCount']" :site-name="$sidebar['siteName']" />
                </div>
            </aside>

            {{-- دکمه باز کردن منو در موبایل --}}
            <div class="fixed bottom-28 right-6 z-[95] lg:hidden">
                <button onclick="toggleFilters(true)" aria-label="منوی پنل کاربری" class="flex items-center justify-center w-14 h-14 bg-white/40 dark:bg-white/[0.05] backdrop-blur-md text-brown-600 rounded-2xl shadow-lg border border-white/60 dark:border-white/10 active:scale-90 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M12 17.25h8.25"></path>
                    </svg>
                </button>
            </div>

            <div id="filter-overlay" wire:ignore.self class="fixed inset-0 bg-black/30 backdrop-blur-sm z-[140] opacity-0 pointer-events-none transition-opacity duration-300 lg:hidden"></div>

            {{-- منوی کشویی موبایل --}}
            <div id="filter-offcanvas" wire:ignore.self onclick="if (event.target.closest('nav a, [wire\\:click]')) toggleFilters(false)" class="fixed top-0 right-0 h-full w-[85%] max-w-[380px] bg-white/30 dark:bg-black/40 backdrop-blur-[30px] z-[150] translate-x-full transition-transform duration-500 ease-in-out border-l border-white/40 dark:border-white/10 shadow-lg lg:hidden">
                <div class="flex flex-col h-full">
                    <div class="p-6 flex items-center justify-between border-b border-white/40 dark:border-white/5 bg-white/20 dark:bg-white/[0.02]">
                        <h2 class="text-lg font-black text-gray-900 dark:text-white">پنل کاربری</h2>
                        <button onclick="toggleFilters(false)" aria-label="بستن" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-white/40 dark:bg-white/5 text-gray-600 dark:text-gray-300 border border-white/60 dark:border-white/10">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar">
                        <x-main.users.sidebar-menu :user="$sidebar['user']" :tab="$sidebar['tab']" :menu="$sidebar['menu']"
                                                   :level="$sidebar['level']" :orders-count="$sidebar['ordersCount']" :site-name="$sidebar['siteName']" />
                    </div>
                </div>
            </div>

            <main class="flex-1 min-w-0 space-y-8">
                @switch($tab)
                    @case('orders')
                        <livewire:main.users.orders :user="$user" wire:key="user-tab-orders" />
                        @break
                    @case('wallet')
                        <livewire:main.users.wallet :user="$user" wire:key="user-tab-wallet" />
                        @break
                    @case('settings')
                        <livewire:main.users.settings :user="$user" wire:key="user-tab-settings" />
                        @break
                    @case('tickets')
                        <livewire:main.users.tickets :user="$user" wire:key="user-tab-tickets" />
                        @break
                    @case('wishlist')
                        <livewire:main.users.wishlist :user="$user" wire:key="user-tab-wishlist" />
                        @break
                    @default
                        <livewire:main.users.dashboard :user="$user" wire:key="user-tab-dashboard" />
                @endswitch
            </main>

        </div>
    </section>
</div>
