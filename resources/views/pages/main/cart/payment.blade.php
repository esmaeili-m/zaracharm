<?php

use Livewire\Component;
use App\Models\Order;
use App\Models\Payment;

/*
 * نتیجه پرداخت سفارش (بعد از درگاه، کیف پول، کارت‌به‌کارت یا پرداخت در محل)
 * Route: /order/{code}/payment  (code = شماره سفارش) ؛ ?payment=uuid برای نمایش یک پرداخت مشخص
 */
new class extends Component
{
    public Order $order;
    public ?Payment $payment = null;

    public function mount(string $code)
    {
        $this->order = Order::where('order_number', $code)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $uuid = request()->query('payment');

        $this->payment = Payment::where('order_id', $this->order->id)
            ->when($uuid, fn ($q) => $q->where('uuid', $uuid))
            ->with('bankCard')
            ->latest('id')
            ->first();
    }

    /**
     * success | review | cod | failed
     */
    public function state(): string
    {
        if ($this->order->payment_status === 'paid' || $this->payment?->status === 'paid') {
            return 'success';
        }

        if ($this->payment?->status === 'pending') {
            return $this->payment->method === 'cod' ? 'cod' : 'review';
        }

        return 'failed';
    }

    // سفارش هنوز قابل پرداخت است؟ (برای دکمه تلاش دوباره)
    public function canRetry(): bool
    {
        return $this->order->status === 'pending'
            && $this->order->payment_status !== 'paid'
            && (! $this->order->expires_at || $this->order->expires_at->isFuture());
    }
};
?>

@php
    $state = $this->state();
    $theme = [
        'success' => ['color' => 'emerald', 'title' => 'پرداخت با موفقیت انجام شد!', 'sub' => 'Order Confirmed Successfully', 'icon' => 'M5 13l4 4L19 7'],
        'review'  => ['color' => 'amber', 'title' => 'پرداخت شما ثبت شد', 'sub' => 'Awaiting Payment Review', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        'cod'     => ['color' => 'emerald', 'title' => 'سفارش شما ثبت شد!', 'sub' => 'Cash On Delivery', 'icon' => 'M5 13l4 4L19 7'],
        'failed'  => ['color' => 'rose', 'title' => 'پرداخت انجام نشد', 'sub' => 'Payment Failed', 'icon' => 'M6 18L18 6M6 6l12 12'],
    ][$state];
    $c = $theme['color'];
@endphp

<div>
    <main class="space-y-12">

        <!-- CONTENT -->
        <section class="relative overflow-hidden py-16 transition-colors duration-700">
            <div class="max-w-4xl mx-auto py-12 px-4" dir="rtl">
                <div class="relative bg-white/40 dark:bg-white/[0.02] backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[3.5rem] p-8 md:p-12 shadow-lg overflow-hidden">

                    <div class="absolute -top-24 -right-24 w-64 h-64 bg-{{ $c }}-500/10 rounded-full blur-[80px]"></div>
                    <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-brown-500/10 rounded-full blur-[80px]"></div>

                    <div class="relative flex flex-col items-center text-center">
                        <div class="relative mb-8">
                            <div class="absolute inset-0 bg-{{ $c }}-500/20 rounded-full blur-md animate-pulse"></div>
                            <div class="relative w-24 h-24 bg-{{ $c }}-500 rounded-full flex items-center justify-center shadow-lg border-4 border-white dark:border-gray-900">
                                <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="{{ $theme['icon'] }}"></path>
                                </svg>
                            </div>
                        </div>

                        <h2 class="text-3xl font-black text-gray-900 dark:text-white mb-2">{{ $theme['title'] }}</h2>
                        <p class="text-gray-500 dark:text-gray-400 font-bold text-sm mb-10 uppercase tracking-widest">{{ $theme['sub'] }}</p>

                        <div class="w-full grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">
                            <div class="p-6 bg-white/60 dark:bg-white/5 rounded-[2.5rem] border border-white/60 dark:border-white/10">
                                <span class="block text-[10px] font-black text-gray-400 mb-2">
                                    {{ $payment?->reference ? 'کد رهگیری' : 'شماره سفارش' }}
                                </span>
                                <span class="block text-lg font-black text-brown-600 tracking-widest" dir="ltr">
                                    {{ $payment?->reference ?: $order->order_number }}
                                </span>
                            </div>
                            <div class="p-6 bg-white/60 dark:bg-white/5 rounded-[2.5rem] border border-white/60 dark:border-white/10">
                                <span class="block text-[10px] font-black text-gray-400 mb-2">تاریخ ثبت</span>
                                <span class="block text-lg font-black text-gray-800 dark:text-white">
                                    {{ verta($payment?->created_at ?? $order->created_at)->format('j F Y') }}
                                </span>
                            </div>
                            <div class="p-6 bg-white/60 dark:bg-white/5 rounded-[2.5rem] border border-white/60 dark:border-white/10">
                                <span class="block text-[10px] font-black text-gray-400 mb-2">{{ $state === 'success' ? 'مبلغ پرداختی' : 'مبلغ سفارش' }}</span>
                                <div class="flex items-center justify-center gap-1">
                                    <span class="text-lg font-black text-{{ $c }}-600 tabular-nums">{{ number_format($payment?->amount ?? $order->total_amount) }}</span>
                                    <span class="text-[10px] font-bold text-gray-400">تومان</span>
                                </div>
                            </div>
                        </div>

                        <div class="w-full bg-{{ $c }}-500/5 dark:bg-{{ $c }}-500/10 border border-{{ $c }}-500/20 rounded-[2.5rem] p-6 mb-10 flex flex-col md:flex-row items-center justify-between gap-6">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-{{ $c }}-500/20 rounded-2xl flex items-center justify-center text-{{ $c }}-600">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                                </div>
                                <div class="text-right">
                                    @if($state === 'success' || $state === 'cod')
                                        <span class="block text-xs font-black text-{{ $c }}-700 dark:text-{{ $c }}-400">آماده‌سازی برای ارسال</span>
                                        <p class="text-[10px] font-bold text-{{ $c }}-600/70">
                                            @if($order->delivery_date)
                                                سفارش شما {{ verta($order->delivery_date)->format('l j F') }} ارسال می‌شود.
                                            @else
                                                سفارش شما در صف آماده‌سازی قرار گرفت.
                                            @endif
                                            @if($state === 'cod')
                                                مبلغ هنگام تحویل دریافت می‌شود.
                                            @endif
                                        </p>
                                    @elseif($state === 'review')
                                        <span class="block text-xs font-black text-{{ $c }}-700 dark:text-{{ $c }}-400">در انتظار تأیید پرداخت</span>
                                        <p class="text-[10px] font-bold text-{{ $c }}-600/70">
                                            اطلاعات واریز شما ثبت شد و پس از بررسی، سفارش تأیید می‌شود.
                                            @if($payment?->bankCard)
                                                (کارت مقصد: {{ $payment->bankCard->bank_name }})
                                            @endif
                                        </p>
                                    @else
                                        <span class="block text-xs font-black text-{{ $c }}-700 dark:text-{{ $c }}-400">پرداخت ناموفق</span>
                                        <p class="text-[10px] font-bold text-{{ $c }}-600/70">
                                            {{ $payment?->failure_reason ?: 'پرداخت انجام نشد. اگر مبلغی از حساب شما کسر شده باشد، طی ۷۲ ساعت بازگردانده می‌شود.' }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                            <a href="{{ route('user.dashboard', ['tab' => 'orders']) }}" class="text-xs font-black text-{{ $c }}-600 hover:text-{{ $c }}-700 underline underline-offset-8">مشاهده جزئیات سفارش</a>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto">
                            @if($state === 'failed' && $this->canRetry())
                                <a href="{{ route('checkout', $order->order_number) }}" class="px-10 h-16 flex items-center justify-center bg-brown-600 text-white font-black rounded-3xl shadow-lg shadow-brown-500/20 hover:bg-brown-700 transition-all active:scale-95">
                                    تلاش دوباره برای پرداخت
                                </a>
                            @else
                                <a href="{{ route('user.dashboard', ['tab' => 'orders']) }}" class="px-10 h-16 flex items-center justify-center bg-brown-600 text-white font-black rounded-3xl shadow-lg shadow-brown-500/20 hover:bg-brown-700 transition-all active:scale-95">
                                    پیگیری سفارش
                                </a>
                            @endif
                            <a href="{{ route('home') }}" class="px-10 h-16 flex items-center justify-center bg-white dark:bg-white/5 text-gray-700 dark:text-white font-black rounded-3xl border border-gray-200 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-white/10 transition-all active:scale-95">
                                بازگشت به فروشگاه
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </section>
        <!-- END CONTENT -->

        <!-- SHOP FEATURE -->
        <section class="relative overflow-hidden transition-colors duration-500">
            <div class="container pt-5">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-12 lg:gap-8">

                    <div class="flex flex-col items-center text-center group">
                        <div class="relative w-20 h-20 mb-6 flex items-center justify-center">
                            <div class="absolute inset-0 bg-brown-500/10 dark:bg-brown-500/20 blur-md rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            <div class="relative z-10 w-full h-full bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm transition-all duration-500 group-hover:-translate-y-2 group-hover:border-brown-500/50 group-hover:shadow-lg group-hover:shadow-brown-500/10 flex items-center justify-center">
                                <svg class="w-9 h-9 text-gray-700 dark:text-gray-300 group-hover:text-brown-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-sm font-black text-gray-900 dark:text-white mb-2 transition-colors group-hover:text-brown-600 dark:group-hover:text-brown-400">ارسال فوق سریع</h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-6 max-w-[150px]">تحویل کالا در کمتر از ۲۴ ساعت در سراسر کشور</p>
                    </div>

                    <div class="flex flex-col items-center text-center group">
                        <div class="relative w-20 h-20 mb-6 flex items-center justify-center">
                            <div class="absolute inset-0 bg-secondary-500/10 dark:bg-secondary-500/20 blur-md rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            <div class="relative z-10 w-full h-full bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm transition-all duration-500 group-hover:-translate-y-2 group-hover:border-secondary-500/50 group-hover:shadow-lg group-hover:shadow-secondary-500/10 flex items-center justify-center">
                                <svg class="w-9 h-9 text-gray-700 dark:text-gray-300 group-hover:text-secondary-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-sm font-black text-gray-900 dark:text-white mb-2 transition-colors group-hover:text-secondary-600 dark:group-hover:text-secondary-500">۷ روز ضمانت بازگشت</h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-6 max-w-[150px]">امکان بازگشت کالا در صورت عدم رضایت یا نقص</p>
                    </div>

                    <div class="flex flex-col items-center text-center group">
                        <div class="relative w-20 h-20 mb-6 flex items-center justify-center">
                            <div class="absolute inset-0 bg-emerald-500/10 dark:bg-emerald-500/20 blur-md rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            <div class="relative z-10 w-full h-full bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm transition-all duration-500 group-hover:-translate-y-2 group-hover:border-emerald-500/50 group-hover:shadow-lg group-hover:shadow-emerald-500/10 flex items-center justify-center">
                                <svg class="w-9 h-9 text-gray-700 dark:text-gray-300 group-hover:text-emerald-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-sm font-black text-gray-900 dark:text-white mb-2 transition-colors group-hover:text-emerald-600 dark:group-hover:text-emerald-500">پرداخت امن</h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-6 max-w-[150px]">استفاده از پروتکل‌های امن و درگاه‌های معتبر</p>
                    </div>

                    <div class="flex flex-col items-center text-center group">
                        <div class="relative w-20 h-20 mb-6 flex items-center justify-center">
                            <div class="absolute inset-0 bg-brown-500/10 dark:bg-brown-500/20 blur-md rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            <div class="relative z-10 w-full h-full bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm transition-all duration-500 group-hover:-translate-y-2 group-hover:border-brown-500/50 group-hover:shadow-lg group-hover:shadow-brown-500/10 flex items-center justify-center">
                                <svg class="w-9 h-9 text-gray-700 dark:text-gray-300 group-hover:text-brown-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-sm font-black text-gray-900 dark:text-white mb-2 transition-colors group-hover:text-brown-600 dark:group-hover:text-brown-400">ضمانت اصالت</h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-6 max-w-[150px]">تضمین ۱۰۰٪ کالاها با گارانتی معتبر</p>
                    </div>

                </div>
            </div>
        </section>
        <!-- END SHOP FEATURE -->
    </main>
</div>
