<?php

use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $orderFilter = 'all';
    public $user;
    public ?int $expandedId = null;   // سفارشی که جزئیاتش باز است

    public const FILTERS = [
        'all' => 'همه',
        'awaiting' => 'در انتظار پرداخت',
        'current' => 'جاری',
        'delivered' => 'تحویل شده',
        'cancelled' => 'لغو / مرجوع',
    ];

    public function mount($user = null)
    {
        $this->user = $user ?? auth()->user();
    }
    public function changeOrderFilter($filter)
    {
        $this->orderFilter = array_key_exists($filter, self::FILTERS) ? $filter : 'all';
        $this->expandedId = null;
        $this->resetPage();
    }

    public function toggleDetails(int $orderId): void
    {
        $this->expandedId = $this->expandedId === $orderId ? null : $orderId;
    }

    // بعد از ثبت/لغو مرجوعی، وضعیت کارت‌ها بروز شود
    #[\Livewire\Attributes\On('return-request-updated')]
    public function refreshOrders(): void
    {
        unset($this->orders);
    }

    // سفارش‌هایی که دکمه «درخواست مرجوعی» دارند
    public function canRequestReturn($order): bool
    {
        return app(\App\Services\Returns\ReturnRequestService::class)->eligibility($order)['allowed'];
    }

    public function getOrdersProperty()
    {
        return $this->user->orders()
            ->with([
                'items.variant.product.featuredImage',
                'items.variant.optionValues.optionValue',
                'address',
                'shipment',
                'coupon',
                'returnRequests' => fn ($q) => $q->latest(),
            ])
            ->when($this->orderFilter === 'awaiting', fn ($q) => $q->where('status', 'pending'))
            ->when($this->orderFilter === 'current', fn ($q) => $q->whereIn('status', \App\Support\OrderStatus::IN_PROGRESS))
            ->when($this->orderFilter === 'delivered', fn ($q) => $q->whereIn('status', \App\Support\OrderStatus::DONE))
            ->when($this->orderFilter === 'cancelled', fn ($q) => $q->whereIn('status', ['cancelled', 'returned']))
            ->latest()
            ->paginate(5);
    }
};
?>

<div>
    {{-- فیلتر سفارش‌ها --}}
    <div class="flex flex-wrap gap-2 mb-6" dir="rtl">
        @foreach($this::FILTERS as $key => $label)
            <button type="button" wire:click="changeOrderFilter('{{ $key }}')"
                    class="px-5 py-2.5 rounded-2xl text-[11px] font-black transition-all
                    {{ $orderFilter === $key ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20' : 'bg-white/40 dark:bg-white/[0.03] border border-white/60 dark:border-white/10 text-gray-500 dark:text-gray-400 hover:text-primary-500' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <!-- Order Items -->
    <div class="space-y-6" dir="rtl">

        @forelse($this->orders as $order)
            @php $status = \App\Support\OrderStatus::badge($order->status); @endphp


            <div class="relative overflow-hidden bg-white/40 dark:bg-gray-950/60 backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-6 shadow-[0_20px_50px_rgba(0,0,0,0.05)] dark:shadow-none group transition-all hover:border-primary-500/30">

                <div class="absolute -top-24 -left-24 w-48 h-48 bg-primary-500/5 rounded-full blur-md group-hover:bg-primary-500/10 transition-colors"></div>

                <div class="relative z-10">

                    {{-- Header --}}
                    <div class="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-gray-100 dark:border-white/5">

                        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">

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
                                {{ number_format($order->total_amount) }}
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

                            @if($order->delivery_date)
                                <div class="flex flex-col gap-1">
                                <span class="text-[10px] font-black text-gray-400">
                                    تاریخ ارسال
                                </span>
                                    <span class="text-[11px] font-bold text-brown-600 dark:text-brown-400">
                                    {{ verta($order->delivery_date)->format('l j F') }}
                                </span>
                                </div>
                            @endif

                        </div>


                        {{-- Status --}}
                        <div class="flex flex-wrap items-center gap-2">

                            @if($latestReturn = $order->returnRequests->first())
                                <button type="button"
                                        wire:click="$dispatch('open-return-request', { orderId: {{ $order->id }} })"
                                        class="px-3 py-2 rounded-xl border text-[10px] font-black {{ $latestReturn->status_class }}">
                                    مرجوعی: {{ $latestReturn->status_label }}
                                </button>
                            @endif

                        <div class="flex items-center gap-2 px-4 py-2 rounded-xl {{ $status['class'] }} border">

                            <span class="w-2.5 h-2.5 rounded-full {{ $status['dot'] }}"></span>

                            <span class="text-[11px] font-black">
                            {{ $status['title'] }}
                        </span>

                        </div>

                        </div>

                    </div>


                    {{-- Products --}}
                    <div class="py-6 flex flex-wrap items-start justify-between gap-6">

                        {{-- محصولات --}}
                        <div class="flex flex-col items-start gap-4">

                            {{-- تصاویر محصولات --}}
                            <div class="flex items-center -space-x-4 space-x-reverse">

                                @foreach($order->items->take(4) as $item)
                                    <div class="w-14 h-14 rounded-full border-4 border-white/50 dark:border-gray-900/50 bg-white/30 dark:bg-white/10 backdrop-blur-md overflow-hidden shadow-lg transform transition-transform group-hover:-translate-y-1">
                                        @if($image = $item->variant?->product?->featuredImageUrl)
                                            <img src="{{ $image }}" alt="{{ $item->variant?->product?->title }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                        @endif
                                    </div>
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

                            <button
                                type="button"
                                wire:click="toggleDetails({{ $order->id }})"
                                class="px-6 py-3 rounded-2xl bg-primary-500 text-white text-[11px]
                   font-black shadow-lg shadow-primary-500/25
                   hover:bg-primary-600 transition-all active:scale-95 whitespace-nowrap"
                            >
                                {{ $expandedId === $order->id ? 'بستن جزئیات' : 'مشاهده جزئیات' }}
                            </button>


                            @if($order->status === 'shipped' && $order->shipment?->tracking_code)

                                <button
                                    type="button"
                                    wire:click="toggleDetails({{ $order->id }})"
                                    class="px-6 py-3 rounded-2xl bg-purple-500 text-white text-[11px]
                       font-black shadow-lg shadow-purple-500/20
                       hover:bg-purple-600 transition-all active:scale-95 whitespace-nowrap"
                                >
                                    رهگیری مرسوله
                                </button>

                            @elseif($order->status === 'pending' && $order->payment_status === 'unpaid')

                                @if(\App\Support\OrderStatus::isPayable($order))

                                    <a
                                        href="{{ route('checkout', $order->order_number) }}"
                                        class="px-6 py-3 rounded-2xl bg-amber-500 text-white text-[11px]
                       font-black shadow-lg shadow-amber-500/20
                       hover:bg-amber-600 transition-all active:scale-95 whitespace-nowrap"
                                    >
                                        پرداخت سفارش
                                    </a>

                                @else

                                    <span
                                        class="px-6 py-3 rounded-2xl bg-gray-100 dark:bg-white/5 text-gray-400 text-[11px]
                       font-black whitespace-nowrap"
                                    >
                                        مهلت پرداخت تمام شده
                                    </span>

                                @endif

                            @elseif($order->payment_status === 'pending')

                                <span class="px-6 py-3 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 text-[11px] font-black whitespace-nowrap">
                                    پرداخت در حال بررسی
                                </span>

                            @endif

                            {{-- مرجوعی --}}
                            @if($this->canRequestReturn($order))

                                <button
                                    type="button"
                                    wire:click="$dispatch('open-return-request', { orderId: {{ $order->id }} })"
                                    class="px-6 py-3 rounded-2xl bg-white/70 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-200 text-[11px]
                       font-black hover:border-amber-500/50 hover:text-amber-600 transition-all active:scale-95 whitespace-nowrap"
                                >
                                    درخواست مرجوعی
                                </button>

                            @elseif($order->returnRequests->isNotEmpty())

                                <button
                                    type="button"
                                    wire:click="$dispatch('open-return-request', { orderId: {{ $order->id }} })"
                                    class="px-6 py-3 rounded-2xl bg-amber-500 text-white text-[11px]
                       font-black shadow-lg shadow-amber-500/20
                       hover:bg-amber-600 transition-all active:scale-95 whitespace-nowrap"
                                >
                                    جزئیات مرجوعی
                                </button>

                            @endif

                        </div>

                    </div>

                    {{-- جزئیات سفارش --}}
                    @if($expandedId === $order->id)
                        <div class="pt-6 mt-2 border-t border-gray-100 dark:border-white/5 grid grid-cols-1 lg:grid-cols-3 gap-6" wire:key="order-details-{{ $order->id }}">

                            {{-- اقلام --}}
                            <div class="lg:col-span-2 space-y-3">
                                <h4 class="text-[12px] font-black text-gray-900 dark:text-white">اقلام سفارش</h4>
                                @foreach($order->items as $item)
                                    @php
                                        $options = $item->variant?->optionValues
                                            ?->map(fn ($ov) => $ov->optionValue?->title)->filter()->implode(' / ');
                                    @endphp
                                    <div class="flex items-center justify-between gap-4 p-4 rounded-2xl bg-white/50 dark:bg-white/[0.03] border border-white/60 dark:border-white/5">
                                        <div class="min-w-0">
                                            @if($item->variant?->product?->slug)
                                                <a href="{{ route('products.show', $item->variant->product->slug) }}" class="text-[12px] font-black text-gray-900 dark:text-white hover:text-primary-500 truncate block">{{ $item->variant->product->title }}</a>
                                            @else
                                                <span class="text-[12px] font-black text-gray-900 dark:text-white">{{ $item->product_name ?? 'محصول حذف شده' }}</span>
                                            @endif
                                            @if($options)
                                                <span class="text-[10px] font-bold text-gray-400">{{ $options }}</span>
                                            @endif
                                        </div>
                                        <div class="text-left shrink-0">
                                            <div class="text-[11px] font-bold text-gray-500 tabular-nums">{{ number_format($item->quantity) }} × {{ number_format($item->price) }}</div>
                                            <div class="text-[12px] font-black text-gray-900 dark:text-white tabular-nums">{{ number_format($item->total_price ?? $item->price * $item->quantity) }} تومان</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- خلاصه، ارسال، پرداخت --}}
                            <div class="space-y-4">
                                <div class="p-5 rounded-2xl bg-white/50 dark:bg-white/[0.03] border border-white/60 dark:border-white/5 space-y-2 text-[11px] font-bold text-gray-500 dark:text-gray-400">
                                    <div class="flex justify-between"><span>جمع کالاها</span><span class="tabular-nums">{{ number_format($order->subtotal) }}</span></div>
                                    @if($order->discount_amount)
                                        <div class="flex justify-between text-emerald-600"><span>تخفیف @if($order->coupon)({{ $order->coupon->code }})@endif</span><span class="tabular-nums">-{{ number_format($order->discount_amount) }}</span></div>
                                    @endif
                                    @if($order->tax_amount)
                                        <div class="flex justify-between"><span>مالیات</span><span class="tabular-nums">{{ number_format($order->tax_amount) }}</span></div>
                                    @endif
                                    <div class="flex justify-between"><span>هزینه ارسال</span><span class="tabular-nums">{{ $order->shipping_amount ? number_format($order->shipping_amount) : 'رایگان' }}</span></div>
                                    <div class="flex justify-between pt-2 border-t border-gray-100 dark:border-white/5 text-[12px] font-black text-gray-900 dark:text-white"><span>مبلغ نهایی</span><span class="tabular-nums">{{ number_format($order->total_amount) }} تومان</span></div>
                                    <div class="flex justify-between pt-2"><span>وضعیت پرداخت</span><span>{{ \App\Support\OrderStatus::paymentStatusLabel($order->payment_status) }}</span></div>
                                    @if($method = \App\Support\OrderStatus::paymentMethodLabel($order->payment_method))
                                        <div class="flex justify-between"><span>روش پرداخت</span><span>{{ $method }}</span></div>
                                    @endif
                                </div>

                                @if($order->address)
                                    <div class="p-5 rounded-2xl bg-white/50 dark:bg-white/[0.03] border border-white/60 dark:border-white/5 text-[11px] font-bold text-gray-500 dark:text-gray-400 leading-6">
                                        <div class="text-[12px] font-black text-gray-900 dark:text-white mb-1">آدرس تحویل</div>
                                        <div>{{ $order->address->receiver_name }} @if($order->address->receiver_phone)<span dir="ltr" class="tabular-nums">({{ $order->address->receiver_phone }})</span>@endif</div>
                                        <div>{{ $order->address->province }}، {{ $order->address->city }}، {{ $order->address->address }}@if($order->address->plate) - پلاک {{ $order->address->plate }}@endif @if($order->address->unit) - واحد {{ $order->address->unit }}@endif</div>
                                        @if($order->address->postal_code)<div>کد پستی: <span class="tabular-nums">{{ $order->address->postal_code }}</span></div>@endif
                                    </div>
                                @endif

                                @if($order->shipment)
                                    <div class="p-5 rounded-2xl bg-purple-500/5 border border-purple-500/10 text-[11px] font-bold text-gray-500 dark:text-gray-400 leading-6">
                                        <div class="text-[12px] font-black text-gray-900 dark:text-white mb-1">ارسال مرسوله</div>
                                        @if($order->shipment->carrier)<div>شرکت ارسال: {{ $order->shipment->carrier }}</div>@endif
                                        @if($order->shipment->tracking_code)
                                            <div class="flex items-center gap-2" x-data="{ copied: false }">
                                                کد رهگیری:
                                                <span dir="ltr" class="tabular-nums font-black text-purple-600 dark:text-purple-400">{{ $order->shipment->tracking_code }}</span>
                                                <button type="button" class="text-[10px] text-primary-500"
                                                        x-on:click="navigator.clipboard?.writeText(@js($order->shipment->tracking_code)); copied = true; setTimeout(() => copied = false, 1500)"
                                                        x-text="copied ? 'کپی شد' : 'کپی'"></button>
                                            </div>
                                        @endif
                                        @if($order->shipment->sent_at)<div>تاریخ ارسال: {{ verta($order->shipment->sent_at)->format('Y/m/d') }}</div>@endif
                                        @if($order->shipment->delivered_at)<div>تاریخ تحویل: {{ verta($order->shipment->delivered_at)->format('Y/m/d') }}</div>@endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

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

        @if($this->orders->hasPages())
            <div class="pt-2">{{ $this->orders->links() }}</div>
        @endif

    </div>

    {{-- مودال مرجوعی (یک نمونه برای همه سفارش‌ها) --}}
    <livewire:main.users.return-request />
</div>
