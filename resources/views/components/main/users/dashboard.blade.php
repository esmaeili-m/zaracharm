<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Support\OrderStatus;

new class extends Component
{
    public $user;

    public function mount($user = null)
    {
        $this->user = $user ?? auth()->user();
    }

    // آمار سفارش‌ها با یک کوئری
    #[Computed]
    public function stats(): array
    {
        $counts = $this->user->orders()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sum = fn (array $statuses) => (int) collect($statuses)->sum(fn ($s) => $counts[$s] ?? 0);

        return [
            'done' => $sum(OrderStatus::DONE),
            'in_progress' => $sum(OrderStatus::IN_PROGRESS),
            'cancelled' => $sum(OrderStatus::CANCELLED),
        ];
    }

    #[Computed]
    public function recentOrders()
    {
        return $this->user->orders()->latest()->limit(5)->get();
    }

    #[Computed]
    public function walletBalance(): int
    {
        return (int) ($this->user->wallet?->balance ?? 0);
    }

    #[Computed]
    public function cartCount(): int
    {
        $cart = $this->user->cart()->where('status', 'active')->first();

        return $cart ? (int) $cart->items()->count() : 0;
    }

    /** تغییر بخش پنل (کامپوننت والد) */
    public function goTo(string $tab): void
    {
        $this->dispatch('user-panel-tab', status: $tab);
    }
};
?>

<div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6 mb-4 md:mb-5">
        {{-- کیف پول --}}
        <div class="group relative overflow-hidden p-5 md:p-8 rounded-3xl md:rounded-[2.5rem] bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/60 dark:border-white/10 shadow-[0_20px_50px_rgba(0,0,0,0.04)] dark:shadow-none transition-all duration-500 md:hover:-translate-y-2">

            <div class="absolute -top-10 -left-10 w-32 h-32 bg-secondary-500/[0.05] rounded-full blur-md group-hover:bg-secondary-500/10 transition-all duration-700"></div>

            <div class="relative z-10">
                <div class="flex items-center justify-between mb-5 md:mb-8">
                    <div class="w-14 h-14 rounded-[1.5rem] bg-secondary-500/10 dark:bg-secondary-500/20 border border-secondary-500/20 flex items-center justify-center text-secondary-600 dark:text-secondary-400 shadow-inner group-hover:scale-110 transition-transform duration-500">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                    </div>

                    <div class="flex flex-col items-end text-left">
                        <span class="text-[10px] font-black text-secondary-600 dark:text-secondary-400 bg-secondary-500/10 px-3 py-1 rounded-full uppercase tracking-tighter">Wallet</span>
                        <span class="text-[11px] font-bold text-gray-400 mt-1">موجودی کیف پول</span>
                    </div>
                </div>

                <div class="flex items-baseline gap-2">
                    <span class="text-2xl md:text-3xl font-black text-gray-900 dark:text-white tabular-nums tracking-tight">{{ number_format($this->walletBalance) }}</span>
                    <span class="text-[11px] font-black text-gray-400">تومان</span>
                </div>

                <button type="button" wire:click="goTo('wallet')"
                        class="mt-5 md:mt-6 w-full py-3 rounded-2xl bg-secondary-500/10 text-secondary-600 dark:text-secondary-400 text-[11px] font-black hover:bg-secondary-500 hover:text-white transition-all">
                    مدیریت کیف پول و تراکنش‌ها
                </button>
            </div>
        </div>

        {{-- خوش‌آمد / تکمیل اطلاعات --}}
        <div class="group relative overflow-hidden p-5 md:p-8 rounded-3xl md:rounded-[2.5rem] bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/60 dark:border-white/10 shadow-[0_20px_50px_rgba(0,0,0,0.04)] dark:shadow-none">
            <div class="relative z-10 flex flex-col h-full justify-between gap-4 md:gap-6">
                <div>
                    <p class="text-[11px] font-bold text-gray-400">خوش آمدید</p>
                    <h3 class="text-lg md:text-xl font-black text-gray-900 dark:text-white mt-1 truncate">{{ trim((string) $user->full_name) ?: ($user->mobile ?? 'کاربر') }}</h3>
                    <p class="text-[11px] font-bold text-gray-500 dark:text-gray-400 mt-3 leading-6">
                        @if(blank($user->first_name) || blank($user->last_name))
                            برای ارسال سریع‌تر سفارش‌ها، اطلاعات حساب خود را تکمیل کنید.
                        @else
                            وضعیت سفارش‌ها، کیف پول و تیکت‌های خود را از همین‌جا پیگیری کنید.
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="goTo('settings')" class="px-5 py-2.5 rounded-xl bg-primary-500 text-white text-[11px] font-black shadow-lg shadow-primary-500/20 active:scale-95 transition-all">تنظیمات حساب</button>
                    <a href="{{ route('cartItem') }}" class="px-5 py-2.5 rounded-xl bg-gray-100/70 dark:bg-white/5 text-gray-600 dark:text-gray-300 text-[11px] font-black hover:bg-primary-500 hover:text-white transition-all">سبد خرید</a>
                </div>
            </div>
        </div>
    </div>

    @php
        $cards = [
            ['title' => 'سفارش‌های تحویل‌شده', 'value' => $this->stats['done'], 'color' => 'emerald', 'icon' => 'M5 13l4 4L19 7', 'tab' => 'orders'],
            ['title' => 'در حال پردازش', 'value' => $this->stats['in_progress'], 'color' => 'primary', 'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15', 'tab' => 'orders'],
            ['title' => 'سبد خرید', 'value' => $this->cartCount, 'color' => 'amber', 'icon' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z', 'href' => route('cartItem')],
            ['title' => 'سفارش‌های لغو شده', 'value' => $this->stats['cancelled'], 'color' => 'rose', 'icon' => 'M6 18L18 6M6 6l12 12', 'tab' => 'orders'],
        ];
        // کلاس‌های کامل برای Tailwind
        $colors = [
            'emerald' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/10',
            'primary' => 'bg-primary-500/10 text-primary-600 dark:text-primary-400 border-primary-500/10',
            'amber' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/10',
            'rose' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/10',
        ];
    @endphp

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-6 mb-4 md:mb-5">
        @foreach($cards as $card)
            <{{ isset($card['href']) ? 'a' : 'button' }}
                @if(isset($card['href'])) href="{{ $card['href'] }}" @else type="button" wire:click="goTo('{{ $card['tab'] }}')" @endif
                class="group relative overflow-hidden text-right bg-white/40 dark:bg-white/[0.02] backdrop-blur-md border border-white/60 dark:border-white/10 rounded-3xl md:rounded-[2.5rem] p-4 md:p-7 transition-all duration-500 md:hover:-translate-y-2">
                <div class="relative z-10 flex flex-col gap-3 md:gap-5">
                    <div class="w-11 h-11 md:w-14 md:h-14 rounded-2xl flex items-center justify-center border shadow-inner group-hover:scale-110 transition-transform duration-500 {{ $colors[$card['color']] }}">
                        <svg class="w-5 h-5 md:w-7 md:h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="{{ $card['icon'] }}"></path></svg>
                    </div>
                    <div>
                        <p class="text-[11px] md:text-[13px] font-bold text-gray-500 dark:text-gray-400 mb-1 md:mb-2 truncate">{{ $card['title'] }}</p>
                        <h4 class="text-2xl md:text-3xl font-black text-gray-900 dark:text-white tabular-nums tracking-tighter">{{ number_format($card['value']) }} <span class="text-[11px] md:text-sm text-gray-400 font-bold mr-1">مورد</span></h4>
                    </div>
                </div>
            </{{ isset($card['href']) ? 'a' : 'button' }}>
        @endforeach
    </div>

    <div class="relative overflow-hidden bg-white/40 dark:bg-white/[0.02] backdrop-blur-md border border-white/60 dark:border-white/10 rounded-3xl md:rounded-[2.5rem] p-4 md:p-8 shadow-[0_20px_50px_rgba(0,0,0,0.02)]">

        <div class="absolute -top-24 -left-24 w-64 h-64 bg-primary-500/5 rounded-full blur-[100px]"></div>

        <div class="relative z-10">

            <div class="flex items-center justify-between gap-3 mb-5 md:mb-8">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-1.5 h-8 bg-primary-500 rounded-full shrink-0"></div>
                    <div class="min-w-0">
                        <h3 class="text-base md:text-lg font-black text-gray-900 dark:text-white">آخرین سفارش‌ها</h3>
                        <p class="text-[10px] font-bold text-gray-400 mt-1">۵ سفارش اخیر شما در فروشگاه</p>
                    </div>
                </div>

                <button type="button" wire:click="goTo('orders')"
                        class="shrink-0 px-4 py-2 rounded-xl bg-gray-100/50 dark:bg-white/5 text-[11px] font-black text-gray-600 dark:text-gray-400 hover:bg-primary-500 hover:text-white transition-all duration-300 whitespace-nowrap">
                    مشاهده همه
                </button>
            </div>

            @if($this->recentOrders->isNotEmpty())

                {{-- جدول: تبلت (بدون سایدبار) و دسکتاپ عریض؛ در lg سایدبار جا را تنگ می‌کند --}}
                <div class="hidden md:block lg:hidden xl:block overflow-x-auto">
                    <table class="w-full text-right border-separate border-spacing-y-2">
                        <thead>
                        <tr class="text-gray-400 text-[11px] font-black whitespace-nowrap">
                            <th class="pb-1 pr-5 font-black">کد سفارش</th>
                            <th class="pb-1 px-3 font-black">تاریخ ثبت</th>
                            <th class="pb-1 px-3 font-black">وضعیت</th>
                            <th class="pb-1 px-3 font-black">مبلغ نهایی</th>
                            <th class="pb-1 pl-5 font-black text-left">عملیات</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach($this->recentOrders as $order)
                            @php $badge = OrderStatus::badge($order->status); @endphp
                            <tr class="group whitespace-nowrap" wire:key="recent-{{ $order->id }}">
                                <td class="py-4 pr-5 rounded-r-2xl bg-white/50 dark:bg-white/[0.03] border-y border-r border-white/60 dark:border-white/5 group-hover:bg-primary-500/5 transition-colors">
                                    <span class="text-xs font-black text-gray-900 dark:text-white tabular-nums">#{{ $order->order_number }}</span>
                                </td>
                                <td class="py-4 px-3 bg-white/50 dark:bg-white/[0.03] border-y border-white/60 dark:border-white/5 group-hover:bg-primary-500/5 transition-colors">
                                    <span class="text-[11px] font-bold text-gray-500 dark:text-gray-400 tabular-nums">{{ verta($order->created_at)->format('Y/m/d') }}</span>
                                </td>
                                <td class="py-4 px-3 bg-white/50 dark:bg-white/[0.03] border-y border-white/60 dark:border-white/5 group-hover:bg-primary-500/5 transition-colors">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl {{ $badge['class'] }} text-[10px] font-black border">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $badge['dot'] }}"></span>
                                        {{ $badge['title'] }}
                                    </span>
                                </td>
                                <td class="py-4 px-3 bg-white/50 dark:bg-white/[0.03] border-y border-white/60 dark:border-white/5 group-hover:bg-primary-500/5 transition-colors">
                                    <span class="text-xs font-black text-gray-900 dark:text-white tabular-nums">{{ number_format($order->total_amount) }}</span>
                                    <span class="text-[10px] font-bold text-gray-400">تومان</span>
                                </td>
                                <td class="py-4 pl-5 rounded-l-2xl bg-white/50 dark:bg-white/[0.03] border-y border-l border-white/60 dark:border-white/5 group-hover:bg-primary-500/5 transition-colors">
                                    <div class="flex items-center justify-end gap-2">
                                        @if(OrderStatus::isPayable($order))
                                            <a href="{{ route('checkout', $order->order_number) }}"
                                               class="px-4 h-9 flex items-center justify-center rounded-xl bg-primary-500 text-white text-[10px] font-black shadow-lg shadow-primary-500/20 transition-all active:scale-95">
                                                پرداخت سفارش
                                            </a>
                                        @endif
                                        <button type="button" wire:click="goTo('orders')" title="جزئیات در سفارش‌های من" aria-label="جزئیات سفارش"
                                                class="w-9 h-9 flex items-center justify-center rounded-xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/5 text-gray-400 hover:text-primary-500 hover:border-primary-500 transition-all shadow-sm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- موبایل و lg: لیست فشرده --}}
                <ul class="md:hidden lg:grid xl:hidden lg:grid-cols-2 gap-2.5 space-y-2.5 lg:space-y-0">
                    @foreach($this->recentOrders as $order)
                        @php $badge = OrderStatus::badge($order->status); @endphp
                        <li wire:key="recent-m-{{ $order->id }}" class="p-3.5 rounded-2xl bg-white/50 dark:bg-white/[0.03] border border-white/60 dark:border-white/5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="min-w-0 truncate text-[12px] font-black text-gray-900 dark:text-white tabular-nums">#{{ $order->order_number }}</span>
                                <span class="shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg {{ $badge['class'] }} text-[10px] font-black border whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $badge['dot'] }}"></span>
                                    {{ $badge['title'] }}
                                </span>
                            </div>
                            <div class="mt-2.5 flex items-center justify-between gap-2 text-[11px]">
                                <span class="font-bold text-gray-500 dark:text-gray-400 tabular-nums">{{ verta($order->created_at)->format('Y/m/d') }}</span>
                                <span class="font-black text-gray-900 dark:text-white tabular-nums">{{ number_format($order->total_amount) }} <span class="text-[10px] font-bold text-gray-400">تومان</span></span>
                            </div>
                            <div class="mt-3 grid {{ OrderStatus::isPayable($order) ? 'grid-cols-2' : 'grid-cols-1' }} gap-2">
                                @if(OrderStatus::isPayable($order))
                                    <a href="{{ route('checkout', $order->order_number) }}"
                                       class="h-9 flex items-center justify-center rounded-xl bg-primary-500 text-white text-[11px] font-black active:scale-95 transition-all">
                                        پرداخت سفارش
                                    </a>
                                @endif
                                <button type="button" wire:click="goTo('orders')"
                                        class="h-9 flex items-center justify-center rounded-xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/5 text-gray-600 dark:text-gray-300 text-[11px] font-black active:scale-95 transition-all">
                                    جزئیات سفارش
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>

            @else
                <div class="py-12 md:py-16 flex flex-col items-center justify-center text-center">
                    <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">
                        <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 2a1 1 0 001 2h11M10 19a1 1 0 11-2 0m10 0a1 1 0 11-2 0"/>
                        </svg>
                    </div>
                    <h4 class="text-sm font-black text-gray-900 dark:text-white">هنوز سفارشی ثبت نکرده‌اید</h4>
                    <p class="text-[10px] font-bold text-gray-400 mt-2">اولین خرید خود را از فروشگاه شروع کنید</p>
                    <a href="{{ url('/') }}" class="mt-5 px-6 py-2.5 rounded-xl bg-primary-500 text-white text-[11px] font-black">رفتن به فروشگاه</a>
                </div>
            @endif

        </div>
    </div>
</div>
