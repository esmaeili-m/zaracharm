<?php

use Livewire\Component;

new class extends Component
{
    public $orderFilter = 'all';
    public $user;

    public function mount($user)
    {
        $this->user=$user;
    }
    public function changeOrderFilter($filter)
    {
        $this->orderFilter = $filter;
    }

    public function getOrdersProperty()
    {
        return $this->user->orders()
            ->with([
                'items.variant.product',
            ])
            ->when($this->orderFilter === 'current', function ($query) {
                $query->whereIn('status', [
                    'paid',
                    'processing',
                    'shipped',
                ]);
            })
            ->when($this->orderFilter === 'delivered', function ($query) {
                $query->where('status', 'delivered');
            })
            ->latest()
            ->paginate(5);
    }
};
?>

<div>
    <!-- Order Items -->
    <div class="space-y-6" dir="rtl">

        @forelse($this->orders as $order)
            @php
                $status = match ($order->status) {

                    'paid' => [
                        'title' => 'پرداخت شده',
                        'class' => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400',
                        'dot' => 'bg-emerald-500',
                    ],

                    'processing' => [
                        'title' => 'در حال آماده‌سازی',
                        'class' => 'bg-blue-500/10 border-blue-500/20 text-blue-600 dark:text-blue-400',
                        'dot' => 'bg-blue-500',
                    ],

                    'shipped' => [
                        'title' => 'ارسال شده',
                        'class' => 'bg-purple-500/10 border-purple-500/20 text-purple-600 dark:text-purple-400',
                        'dot' => 'bg-purple-500',
                    ],

                    'delivered' => [
                        'title' => 'تحویل شده',
                        'class' => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400',
                        'dot' => 'bg-emerald-500',
                    ],

                    'cancelled' => [
                        'title' => 'لغو شده',
                        'class' => 'bg-red-500/10 border-red-500/20 text-red-600 dark:text-red-400',
                        'dot' => 'bg-red-500',
                    ],

                    'returned' => [
                        'title' => 'مرجوع شده',
                        'class' => 'bg-amber-500/10 border-amber-500/20 text-amber-600 dark:text-amber-400',
                        'dot' => 'bg-amber-500',
                    ],

                    'pending' => [
                        'title' => 'در انتظار پرداخت',
                        'class' => 'bg-amber-500/10 border-amber-500/20 text-amber-600 dark:text-amber-400',
                        'dot' => 'bg-amber-500',
                    ],

                    default => [
                        'title' => 'نامشخص',
                        'class' => 'bg-gray-500/10 border-gray-500/20 text-gray-500',
                        'dot' => 'bg-gray-500',
                    ],
                };
            @endphp


            <div class="relative overflow-hidden bg-white/40 dark:bg-gray-950/60 backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-6 shadow-[0_20px_50px_rgba(0,0,0,0.05)] dark:shadow-none group transition-all hover:border-primary-500/30">

                <div class="absolute -top-24 -left-24 w-48 h-48 bg-primary-500/5 rounded-full blur-md group-hover:bg-primary-500/10 transition-colors"></div>

                <div class="relative z-10">

                    {{-- Header --}}
                    <div class="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-gray-100 dark:border-white/5">

                        <div class="flex items-center gap-6">

                            <div class="flex flex-col gap-1">

                            <span class="text-[10px] font-black text-gray-400">
                                شماره سفارش
                            </span>

                                <span class="text-[13px] font-black text-gray-900 dark:text-white tabular-nums">
                                #{{ $order->order_number }}
                            </span>

                            </div>


                            <div class="flex flex-col gap-1">

                            <span class="text-[10px] font-black text-gray-400">
                                مبلغ کل
                            </span>

                                <span class="text-[13px] font-black text-primary-500 tabular-nums">
                                {{ number_format($order->total) }}
                                تومان
                            </span>

                            </div>


                            <div class="flex flex-col gap-1">

                            <span class="text-[10px] font-black text-gray-400">
                                تاریخ سفارش
                            </span>
                                <span class="text-[11px] font-bold text-gray-500 dark:text-gray-400">
                                {{ verta($order->created_at)->format('Y/m/d')}}
                            </span>

                            </div>

                        </div>


                        {{-- Status --}}
                        <div class="flex items-center gap-2 px-4 py-2 rounded-xl {{ $status['class'] }} border">

                            <span class="w-2.5 h-2.5 rounded-full {{ $status['dot'] }}"></span>

                            <span class="text-[11px] font-black">
                            {{ $status['title'] }}
                        </span>

                        </div>

                    </div>


                    {{-- Products --}}
                    <div class="py-6 flex flex-wrap items-start justify-between gap-6">

                        {{-- محصولات --}}
                        <div class="flex flex-col items-start gap-4">

                            {{-- تصاویر محصولات --}}
                            <div class="flex items-center -space-x-4 space-x-reverse">

                                @foreach($order->items->take(4) as $item)
                                    <div class="w-14 h-14 rounded-full border-4 border-white/50 dark:border-gray-900/50 bg-white/30 dark:bg-white/10 backdrop-blur-md overflow-hidden shadow-lg transform transition-transform group-hover:-translate-y-1"> <img src="{{ $item->variant?->product?->featuredImageUrl ?? asset('images/default-product.png') }}" alt="{{ $item->variant?->product?->title }}" class="w-full h-full object-cover" > </div>
                                @endforeach


                                @if($order->items->count() > 4)

                                    <div
                                        class="w-14 h-14 rounded-full border-4 border-white/50 dark:border-gray-900/50
                           bg-primary-500/20 dark:bg-primary-500/10 backdrop-blur-md
                           flex items-center justify-center shadow-lg"
                                    >
                    <span class="text-[10px] font-black text-primary-600 dark:text-primary-400">
                        +{{ $order->items->count() - 4 }}
                    </span>
                                    </div>

                                @endif

                            </div>


                            {{-- اسم محصولات زیر تصاویر --}}
                            <div class="flex flex-col gap-1.5 pr-1">

                                @foreach($order->items->take(4) as $item)

                                    <div class="flex items-center gap-2">

                                        <span class="w-1.5 h-1.5 rounded-full bg-primary-500 shrink-0"></span>

                                        <span
                                            class="text-[11px] font-bold text-gray-700 dark:text-gray-300
                               leading-5"
                                        >
                        {{ $item->variant?->product?->title ?? 'محصول حذف شده' }}
                    </span>

                                    </div>

                                @endforeach


                                @if($order->items->count() > 4)

                                    <span class="text-[10px] font-bold text-gray-400 pr-3.5">
                    و {{ $order->items->count() - 4 }} محصول دیگر
                </span>

                                @endif

                            </div>

                        </div>


                        {{-- دکمه‌ها سمت چپ --}}
                        <div class="flex items-center gap-3 mr-auto">

                            <a
                                href="#"
                                class="px-6 py-3 rounded-2xl bg-primary-500 text-white text-[11px]
                   font-black shadow-lg shadow-primary-500/25
                   hover:bg-primary-600 transition-all active:scale-95 whitespace-nowrap"
                            >
                                مشاهده جزئیات
                            </a>


                            @if($order->status === 'shipped')

                                <button
                                    type="button"
                                    class="px-6 py-3 rounded-2xl bg-purple-500 text-white text-[11px]
                       font-black shadow-lg shadow-purple-500/20
                       hover:bg-purple-600 transition-all active:scale-95 whitespace-nowrap"
                                >
                                    رهگیری مرسوله
                                </button>

                            @elseif($order->status === 'pending')

                                <button
                                    type="button"
                                    class="px-6 py-3 rounded-2xl bg-amber-500 text-white text-[11px]
                       font-black shadow-lg shadow-amber-500/20
                       hover:bg-amber-600 transition-all active:scale-95 whitespace-nowrap"
                                >
                                    پرداخت سفارش
                                </button>

                            @elseif($order->status === 'returned')

                                <button
                                    type="button"
                                    class="px-6 py-3 rounded-2xl bg-amber-500 text-white text-[11px]
                       font-black shadow-lg shadow-amber-500/20
                       hover:bg-amber-600 transition-all active:scale-95 whitespace-nowrap"
                                >
                                    جزئیات استرداد
                                </button>

                            @endif

                        </div>

                    </div>
                </div>

            </div>

        @empty

            <div class="relative overflow-hidden bg-white/40 dark:bg-gray-950/60 backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-12 text-center">

                <div class="w-16 h-16 mx-auto rounded-2xl bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-5">

                    <svg class="w-8 h-8 text-gray-400"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="1.8"
                              d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 2a1 1 0 001 2h11M10 19a1 1 0 11-2 0m10 0a1 1 0 11-2 0"/>

                    </svg>

                </div>

                <h3 class="text-sm font-black text-gray-900 dark:text-white">
                    سفارشی پیدا نشد
                </h3>

                <p class="text-[10px] font-bold text-gray-400 mt-2">
                    در این دسته‌بندی هنوز سفارشی ثبت نکرده‌اید
                </p>

            </div>

        @endforelse

    </div>
</div>
