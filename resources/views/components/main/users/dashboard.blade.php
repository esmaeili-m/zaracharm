<?php

use Livewire\Component;

new class extends Component
{
    public $user;

    public function mount()
    {
        $this->user = auth()->user();

        $this->user->load([
            'wallet',
            'orders',
            'cart.items',
        ]);
    }
};
?>

<div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-5">
        <div class="group relative overflow-hidden p-8 rounded-[2.5rem] bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/60 dark:border-white/10 shadow-[0_20px_50px_rgba(0,0,0,0.04)] dark:shadow-none transition-all duration-500 hover:-translate-y-2">

            <div class="absolute -top-10 -left-10 w-32 h-32 bg-secondary-500/[0.05] rounded-full blur-md group-hover:bg-secondary-500/10 transition-all duration-700"></div>

            <div class="relative z-10">
                <div class="flex items-center justify-between mb-8">
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
                    <span class="text-3xl font-black text-gray-900 dark:text-white tabular-nums tracking-tight">{{number_format($user->wallet->balance ?? 0)}}</span>
                    <span class="text-[11px] font-black text-gray-400">تومان</span>
                </div>

                <div class="mt-6 w-full h-1.5 bg-gray-100 dark:bg-white/5 rounded-full overflow-hidden">
                    <div class="w-2/3 h-full bg-gradient-to-l from-secondary-500 to-secondary-300 rounded-full  transition-all duration-1000"></div>
                </div>
            </div>
        </div>

    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-5">

        <div class="group relative overflow-hidden bg-white/40 dark:bg-white/[0.02] backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-7 transition-all duration-500 hover:-translate-y-2">
            <div class="absolute -right-4 -top-4 w-20 h-20 bg-emerald-500/[0.05] rounded-full blur-md group-hover:bg-emerald-500/10 transition-all duration-700"></div>
            <div class="relative z-10 flex flex-col gap-5">
                <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400 border border-emerald-500/10 shadow-inner group-hover:scale-110 transition-transform duration-500">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <div>
                    <p class="text-[13px] font-bold text-gray-500 dark:text-gray-400 mb-2">سفارش‌های موفق</p>
                    <h4 class="text-3xl font-black text-gray-900 dark:text-white tabular-nums tracking-tighter">{{$user->orders->where('payment_status', 'completed')->count()}} <span class="text-sm text-gray-400 font-bold mr-1">مورد</span></h4>
                </div>
            </div>
        </div>

        <div class="group relative overflow-hidden bg-white/40 dark:bg-white/[0.02] backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-7 transition-all duration-500 hover:-translate-y-2">
            <div class="absolute -right-4 -top-4 w-20 h-20 bg-primary-500/[0.05] rounded-full blur-md group-hover:bg-primary-500/10 transition-all duration-700"></div>
            <div class="relative z-10 flex flex-col gap-5">
                <div class="w-14 h-14 rounded-2xl bg-primary-500/10 flex items-center justify-center text-primary-600 dark:text-primary-400 border border-primary-500/10 shadow-inner group-hover:scale-110 transition-transform duration-500">
                    <svg class="w-7 h-7 animate-spin-slow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </div>
                <div>
                    <p class="text-[13px] font-bold text-gray-500 dark:text-gray-400 mb-2">در حال پردازش</p>
                    <h4 class="text-3xl font-black text-gray-900 dark:text-white tabular-nums tracking-tighter">{{$user->orders->where('payment_status', 'pending')->count()}} <span class="text-sm text-gray-400 font-bold mr-1">مورد</span></h4>
                </div>
            </div>
        </div>

        <div class="group relative overflow-hidden bg-white/40 dark:bg-white/[0.02] backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-7 transition-all duration-500 hover:-translate-y-2">
            <div class="absolute -right-4 -top-4 w-20 h-20 bg-amber-500/[0.05] rounded-full blur-md group-hover:bg-amber-500/10 transition-all duration-700"></div>
            <div class="relative z-10 flex flex-col gap-5">
                <div class="w-14 h-14 rounded-2xl bg-amber-500/10 flex items-center justify-center text-amber-600 dark:text-amber-400 border border-amber-500/10 shadow-inner group-hover:scale-110 transition-transform duration-500">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </div>
                <div>
                    <p class="text-[13px] font-bold text-gray-500 dark:text-gray-400 mb-2">سبد خرید باز</p>
                    <h4 class="text-3xl font-black text-gray-900 dark:text-white tabular-nums tracking-tighter">{{ $user->cart?->items->count() ?? 0 }} <span class="text-sm text-gray-400 font-bold mr-1">مورد</span></h4>
                </div>
            </div>
        </div>

        <div class="group relative overflow-hidden bg-white/40 dark:bg-white/[0.02] backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-7 transition-all duration-500 hover:-translate-y-2">
            <div class="absolute -right-4 -top-4 w-20 h-20 bg-rose-500/[0.05] rounded-full blur-md group-hover:bg-rose-500/10 transition-all duration-700"></div>
            <div class="relative z-10 flex flex-col gap-5">
                <div class="w-14 h-14 rounded-2xl bg-rose-500/10 flex items-center justify-center text-rose-600 dark:text-rose-400 border border-rose-500/10 shadow-inner group-hover:scale-110 transition-transform duration-500">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                </div>
                <div>
                    <p class="text-[13px] font-bold text-gray-500 dark:text-gray-400 mb-2">سفارش‌های لغو شده</p>
                    <h4 class="text-3xl font-black text-gray-900 dark:text-white tabular-nums tracking-tighter">{{$user->orders->where('payment_status', 'cancelled')->count()}} <span class="text-sm text-gray-400 font-bold mr-1">مورد</span></h4>
                </div>
            </div>
        </div>

    </div>
    <div class="relative overflow-hidden bg-white/40 dark:bg-white/[0.02] backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-8 shadow-[0_20px_50px_rgba(0,0,0,0.02)]">

        <div class="absolute -top-24 -left-24 w-64 h-64 bg-primary-500/5 rounded-full blur-[100px]"></div>

        <div class="relative z-10">

            {{-- Header --}}
            <div class="flex items-center justify-between mb-10">

                <div class="flex items-center gap-4">

                    <div class="w-1.5 h-8 bg-primary-500 rounded-full shadow-[0_0_15px_rgba(var(--color-primary-500),0.5)]"></div>

                    <div>
                        <h3 class="text-lg font-black text-gray-900 dark:text-white">
                            آخرین سفارش‌ها
                        </h3>

                        <p class="text-[10px] font-bold text-gray-400 mt-1">
                            لیست ۵ سفارش اخیر شما در فروشگاه
                        </p>
                    </div>

                </div>

                <a href="{{ route('user.dashboard') }}"
                   class="px-5 py-2 rounded-xl bg-gray-100/50 dark:bg-white/5 text-[11px] font-black text-gray-600 dark:text-gray-400 hover:bg-primary-500 hover:text-white transition-all duration-300">
                    مشاهده همه
                </a>

            </div>


            {{-- Orders --}}
            <div class="overflow-x-auto scrollbar-hide">

                @if($user->orders->isNotEmpty())

                    <table class="w-full text-right border-separate border-spacing-y-3">

                        <thead>
                        <tr class="text-gray-400 text-[11px] font-black">

                            <th class="pb-2 pr-6 font-black">
                                کد سفارش
                            </th>

                            <th class="pb-2">
                                تاریخ ثبت
                            </th>

                            <th class="pb-2">
                                وضعیت
                            </th>

                            <th class="pb-2 text-center">
                                مبلغ نهایی
                            </th>

                            <th class="pb-2 pl-6 text-left">
                                عملیات
                            </th>

                        </tr>
                        </thead>


                        <tbody>

                        @foreach($user->orders->take(5) as $order)
                            @php

                                $status = match($order->status) {

                                    'paid' => [
                                        'title' => 'پرداخت شده',
                                        'class' => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/10',
                                        'dot' => 'bg-emerald-500',
                                    ],

                                    'pending' => [
                                        'title' => 'در انتظار پرداخت',
                                        'class' => 'bg-amber-500/10 text-amber-500 border-amber-500/10',
                                        'dot' => 'bg-amber-500',
                                    ],

                                    'processing' => [
                                        'title' => 'در حال پردازش',
                                        'class' => 'bg-blue-500/10 text-blue-500 border-blue-500/10',
                                        'dot' => 'bg-blue-500',
                                    ],

                                    'shipped' => [
                                        'title' => 'ارسال شده',
                                        'class' => 'bg-purple-500/10 text-purple-500 border-purple-500/10',
                                        'dot' => 'bg-purple-500',
                                    ],

                                    'delivered' => [
                                        'title' => 'تحویل شده',
                                        'class' => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/10',
                                        'dot' => 'bg-emerald-500',
                                    ],

                                    'cancelled' => [
                                        'title' => 'لغو شده',
                                        'class' => 'bg-red-500/10 text-red-500 border-red-500/10',
                                        'dot' => 'bg-red-500',
                                    ],

                                    default => [
                                        'title' => 'نامشخص',
                                        'class' => 'bg-gray-500/10 text-gray-500 border-gray-500/10',
                                        'dot' => 'bg-gray-500',
                                    ],

                                };

                            @endphp


                            <tr class="group transition-all duration-300">

                                {{-- Order ID --}}
                                <td class="py-5 pr-6 rounded-r-[1.5rem]
                                bg-white/50 dark:bg-white/[0.03]
                                border-y border-r border-white/60 dark:border-white/5
                                group-hover:bg-primary-500/5
                                group-hover:border-primary-500/20
                                transition-all">

                                <span class="text-xs font-black text-gray-900 dark:text-white tabular-nums">
                                    #{{ $order->id }}
                                </span>

                                </td>


                                {{-- Date --}}
                                <td class="py-5
                                bg-white/50 dark:bg-white/[0.03]
                                border-y border-white/60 dark:border-white/5
                                group-hover:bg-primary-500/5
                                group-hover:border-primary-500/20
                                transition-all">

                                <span class="text-[11px] font-bold text-gray-500 dark:text-gray-400 tabular-nums">
                                    {{ $order->created_at?->format('Y/m/d') }}
                                </span>

                                </td>


                                {{-- Status --}}
                                <td class="py-5
                                bg-white/50 dark:bg-white/[0.03]
                                border-y border-white/60 dark:border-white/5
                                group-hover:bg-primary-500/5
                                group-hover:border-primary-500/20
                                transition-all">

                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl
                                    {{ $status['class'] }}
                                    text-[10px] font-black border">

                                    <span class="w-1.5 h-1.5 rounded-full {{ $status['dot'] }}"></span>

                                    {{ $status['title'] }}

                                </span>

                                </td>


                                {{-- Price --}}
                                <td class="py-5
                                bg-white/50 dark:bg-white/[0.03]
                                border-y border-white/60 dark:border-white/5
                                text-center
                                group-hover:bg-primary-500/5
                                group-hover:border-primary-500/20
                                transition-all">

                                <span class="text-xs font-black text-gray-900 dark:text-white tabular-nums">

                                    {{ number_format($order->total) }}

                                    تومان

                                </span>

                                </td>


                                {{-- Actions --}}
                                <td class="py-5 pl-6
                                rounded-l-[1.5rem]
                                bg-white/50 dark:bg-white/[0.03]
                                border-y border-l border-white/60 dark:border-white/5
                                group-hover:bg-primary-500/5
                                group-hover:border-primary-500/20
                                transition-all">

                                    <div class="flex items-center justify-end gap-2">

                                        {{-- View --}}
                                        <a href="{{ route('user.dashboard', $order) }}"
                                           class="w-9 h-9 flex items-center justify-center rounded-xl
                                       bg-white dark:bg-gray-900
                                       border border-gray-100 dark:border-white/5
                                       text-gray-400
                                       hover:text-primary-500
                                       hover:border-primary-500
                                       transition-all shadow-sm">

                                            <svg class="w-4 h-4"
                                                 fill="none"
                                                 stroke="currentColor"
                                                 viewBox="0 0 24 24">

                                                <path stroke-linecap="round"
                                                      stroke-linejoin="round"
                                                      stroke-width="2"
                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>

                                                <path stroke-linecap="round"
                                                      stroke-linejoin="round"
                                                      stroke-width="2"
                                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>

                                            </svg>

                                        </a>


                                        {{-- Payment --}}
                                        @if($order->status === 'pending')

                                            <a href="{{ route('user.dashboard', $order) }}"
                                               class="px-4 h-9 flex items-center justify-center rounded-xl
                                           bg-primary-500
                                           text-white
                                           text-[10px]
                                           font-black
                                           shadow-lg shadow-primary-500/20
                                           transition-all
                                           active:scale-95">

                                                پرداخت آنلاین

                                            </a>

                                        @else

                                            <a href="{{ route('user.dashboard', $order) }}"
                                               class="w-9 h-9 flex items-center justify-center rounded-xl
                                           bg-white dark:bg-gray-900
                                           border border-gray-100 dark:border-white/5
                                           text-gray-400
                                           hover:text-secondary-500
                                           hover:border-secondary-500
                                           transition-all shadow-sm">

                                                <svg class="w-4 h-4"
                                                     fill="none"
                                                     stroke="currentColor"
                                                     viewBox="0 0 24 24">

                                                    <path stroke-linecap="round"
                                                          stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M9 5l7 7-7 7"/>

                                                </svg>

                                            </a>

                                        @endif

                                    </div>

                                </td>

                            </tr>

                        @endforeach

                        </tbody>

                    </table>

                @else

                    {{-- Empty --}}
                    <div class="py-16 flex flex-col items-center justify-center text-center">

                        <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">

                            <svg class="w-7 h-7 text-gray-400"
                                 fill="none"
                                 stroke="currentColor"
                                 viewBox="0 0 24 24">

                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      stroke-width="1.8"
                                      d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 2a1 1 0 001 2h11M10 19a1 1 0 11-2 0m10 0a1 1 0 11-2 0"/>

                            </svg>

                        </div>

                        <h4 class="text-sm font-black text-gray-900 dark:text-white">
                            هنوز سفارشی ثبت نکرده‌اید
                        </h4>

                        <p class="text-[10px] font-bold text-gray-400 mt-2">
                            اولین خرید خود را از فروشگاه شروع کنید
                        </p>

                    </div>

                @endif

            </div>

        </div>

    </div>
</div>
