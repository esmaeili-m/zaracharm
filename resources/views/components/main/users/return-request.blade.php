<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\Returns\ReturnRequestService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

new class extends Component
{
    public ?int $orderId = null;

    // [order_item_id => quantity]
    public array $returnItems = [];
    public string $reason = '';
    public string $description = '';

    #[On('open-return-request')]
    public function open(int $orderId): void
    {
        $this->resetErrorBag();
        $this->reset(['returnItems', 'reason', 'description']);

        // فقط سفارش‌های خود کاربر
        $order = Order::where('user_id', Auth::id())->find($orderId);

        if (! $order) {
            return;
        }

        $this->orderId = $order->id;
        unset($this->order, $this->eligibility, $this->returnable);

        // پیش‌فرض: هیچ قلمی انتخاب نشده
        foreach ($this->returnable as $itemId => $qty) {
            $this->returnItems[$itemId] = 0;
        }
    }

    public function close(): void
    {
        $this->orderId = null;
        $this->resetErrorBag();
    }

    #[Computed]
    public function order(): ?Order
    {
        if (! $this->orderId) {
            return null;
        }

        return Order::where('user_id', Auth::id())
            ->with([
                'items.variant.product.featuredImage',
                'shipment',
                'returnRequests' => fn ($q) => $q->latest()->with('items.orderItem'),
            ])
            ->find($this->orderId);
    }

    #[Computed]
    public function eligibility(): array
    {
        return $this->order
            ? app(ReturnRequestService::class)->eligibility($this->order)
            : ['allowed' => false, 'message' => null, 'deadline' => null];
    }

    #[Computed]
    public function returnable()
    {
        return $this->order
            ? app(ReturnRequestService::class)->returnableQuantities($this->order)
            : collect();
    }

    public function submit(): void
    {
        if (! $this->order) {
            return;
        }

        $this->validate(
            [
                'reason' => ['required', Rule::in(array_keys(ReturnRequest::REASONS))],
                'description' => [Rule::requiredIf($this->reason === 'other'), 'nullable', 'string', 'max:1000'],
                'returnItems' => ['array'],
                'returnItems.*' => ['integer', 'min:0'],
            ],
            [
                'reason.required' => 'لطفاً دلیل مرجوعی را انتخاب کنید.',
                'reason.in' => 'دلیل انتخاب‌شده معتبر نیست.',
                'description.required' => 'لطفاً توضیح دلیل مرجوعی را وارد کنید.',
                'description.max' => 'توضیحات نمی‌تواند بیشتر از ۱۰۰۰ کاراکتر باشد.',
                'returnItems.*.integer' => 'تعداد واردشده معتبر نیست.',
                'returnItems.*.min' => 'تعداد نمی‌تواند منفی باشد.',
            ]
        );

        try {
            $request = app(ReturnRequestService::class)->create(
                Auth::user(),
                $this->orderId,
                $this->returnItems,
                $this->reason,
                trim($this->description) ?: null
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator?->errors() ?? $e->errors());
            return;
        }

        $this->reset(['returnItems', 'reason', 'description']);
        unset($this->order, $this->eligibility, $this->returnable);
        foreach ($this->returnable as $itemId => $qty) {
            $this->returnItems[$itemId] = 0;
        }

        $this->dispatch('return-request-updated');
        $this->dispatch('alert', type: 'success', message: 'درخواست مرجوعی ' . $request->return_number . ' ثبت شد و پس از بررسی نتیجه اطلاع‌رسانی می‌شود.');
    }

    public function cancelRequest(int $returnRequestId): void
    {
        try {
            app(ReturnRequestService::class)->cancel(Auth::user(), $returnRequestId);
        } catch (ValidationException $e) {
            $this->dispatch('alert', type: 'error', message: collect($e->errors())->flatten()->first());
            return;
        }

        unset($this->order, $this->eligibility, $this->returnable);
        $this->dispatch('return-request-updated');
        $this->dispatch('alert', type: 'success', message: 'درخواست مرجوعی لغو شد.');
    }
};
?>

<div>
    @if($this->order)
        @php($order = $this->order)
        <div class="fixed inset-0 z-[2000] flex items-end sm:items-center justify-center p-0 sm:p-6" dir="rtl"
             x-data="{
                 init() { document.body.style.overflow = 'hidden' },
                 destroy() { document.body.style.overflow = '' },
             }"
             x-on:keydown.escape.window="$wire.close()">

            <div class="absolute inset-0 bg-gray-900/60 dark:bg-black/80 backdrop-blur-md" wire:click="close"></div>

            <div class="relative w-full sm:max-w-3xl max-h-[92vh] overflow-y-auto custom-scrollbar bg-white/95 dark:bg-gray-950/95 backdrop-blur-md border border-white/60 dark:border-white/10 rounded-t-[2.5rem] sm:rounded-[2.5rem] shadow-2xl">

                {{-- Header --}}
                <div class="sticky top-0 z-10 flex items-center justify-between gap-4 p-6 bg-white/90 dark:bg-gray-950/90 backdrop-blur-md border-b border-gray-100 dark:border-white/5">
                    <div>
                        <h3 class="text-base font-black text-gray-900 dark:text-white">مرجوعی سفارش</h3>
                        <p class="text-[11px] font-bold text-gray-400 mt-1 tabular-nums">#{{ $order->order_number }}</p>
                    </div>
                    <button type="button" wire:click="close" class="w-10 h-10 rounded-2xl bg-gray-100 dark:bg-white/5 text-gray-500 flex items-center justify-center hover:bg-gray-200 dark:hover:bg-white/10 transition-all" aria-label="بستن">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-6 space-y-8">

                    {{-- Existing requests --}}
                    @if($order->returnRequests->isNotEmpty())
                        <div class="space-y-3">
                            <h4 class="text-[13px] font-black text-gray-900 dark:text-white">درخواست‌های ثبت‌شده</h4>

                            @foreach($order->returnRequests as $returnRequest)
                                <div wire:key="return-{{ $returnRequest->id }}" class="p-4 rounded-[1.8rem] bg-gray-50 dark:bg-white/[0.03] border border-gray-100 dark:border-white/5 space-y-3">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <span class="text-[12px] font-black text-gray-900 dark:text-white tabular-nums">{{ $returnRequest->return_number }}</span>
                                            <span class="text-[10px] font-bold text-gray-400">{{ verta($returnRequest->created_at)->format('Y/m/d') }}</span>
                                        </div>
                                        <span class="px-3 py-1 rounded-lg border text-[10px] font-black {{ $returnRequest->status_class }}">
                                            {{ $returnRequest->status_label }}
                                        </span>
                                    </div>

                                    <ul class="text-[11px] font-bold text-gray-600 dark:text-gray-300 space-y-1">
                                        @foreach($returnRequest->items as $returnItem)
                                            <li>{{ $returnItem->orderItem?->product_name ?? 'کالا' }} × {{ $returnItem->quantity }}</li>
                                        @endforeach
                                    </ul>

                                    <p class="text-[11px] font-bold text-gray-500 dark:text-gray-400">دلیل: {{ $returnRequest->reason_label }}</p>

                                    @if($returnRequest->status === 'approved')
                                        <p class="text-[11px] font-bold text-blue-600 dark:text-blue-400">درخواست شما تأیید شد؛ لطفاً کالا را همراه تمام متعلقات و بسته‌بندی اصلی ارسال کنید.</p>
                                    @elseif($returnRequest->status === 'refunded')
                                        <p class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">مبلغ {{ number_format($returnRequest->refund_amount) }} تومان به کیف پول شما بازگردانده شد.</p>
                                    @endif

                                    @if(filled($returnRequest->admin_note))
                                        <div class="p-3 rounded-xl bg-white dark:bg-white/5 text-[11px] font-bold text-gray-600 dark:text-gray-300 leading-6">
                                            <span class="text-gray-400">پاسخ پشتیبانی:</span> {{ $returnRequest->admin_note }}
                                        </div>
                                    @endif

                                    @if($returnRequest->isPending())
                                        <button type="button"
                                                wire:click="cancelRequest({{ $returnRequest->id }})"
                                                wire:confirm="از لغو این درخواست مرجوعی مطمئن هستید؟"
                                                class="text-[11px] font-black text-red-500 hover:underline">
                                            لغو درخواست
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- New request form --}}
                    @if($this->eligibility['allowed'])
                        <form wire:submit="submit" class="space-y-6">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <h4 class="text-[13px] font-black text-gray-900 dark:text-white">ثبت درخواست مرجوعی جدید</h4>
                                <span class="text-[10px] font-bold text-gray-400">
                                    مهلت ثبت تا {{ verta($this->eligibility['deadline'])->format('Y/m/d') }}
                                </span>
                            </div>

                            {{-- Items --}}
                            <div class="space-y-3">
                                <p class="text-[11px] font-bold text-gray-500 dark:text-gray-400">کالا و تعداد موردنظر برای مرجوعی را مشخص کنید:</p>

                                @foreach($order->items as $item)
                                    @php($max = $this->returnable[$item->id] ?? 0)
                                    <div wire:key="return-item-{{ $item->id }}" class="flex items-center gap-4 p-3 rounded-[1.5rem] border {{ $max > 0 ? 'bg-white/60 dark:bg-white/[0.03] border-gray-200 dark:border-white/10' : 'opacity-50 border-dashed border-gray-200 dark:border-white/10' }}">
                                        <span class="w-14 h-14 rounded-[1rem] bg-gray-100 dark:bg-white/5 overflow-hidden flex-shrink-0">
                                            @if($item->variant?->product?->featured_image_url)
                                                <img src="{{ $item->variant->product->featured_image_url }}" alt="" class="w-full h-full object-cover">
                                            @endif
                                        </span>
                                        <span class="flex-1 min-w-0">
                                            <span class="block text-[12px] font-black text-gray-900 dark:text-white line-clamp-1">{{ $item->product_name }}</span>
                                            <span class="block text-[10px] font-bold text-gray-400 mt-1 tabular-nums">
                                                خریداری‌شده: {{ $item->quantity }} · قابل مرجوع: {{ $max }}
                                            </span>
                                        </span>

                                        @if($max > 0)
                                            <div class="flex items-center gap-1 bg-gray-100 dark:bg-white/5 rounded-xl p-1"
                                                 x-data="{ max: {{ $max }} }">
                                                <button type="button" class="w-8 h-8 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-white/10 font-black"
                                                        x-on:click="$wire.set('returnItems.{{ $item->id }}', Math.min(max, (parseInt($wire.returnItems[{{ $item->id }}]) || 0) + 1))">+</button>
                                                <span class="w-8 text-center text-[13px] font-black text-gray-900 dark:text-white tabular-nums">{{ (int) ($returnItems[$item->id] ?? 0) }}</span>
                                                <button type="button" class="w-8 h-8 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-white/10 font-black"
                                                        x-on:click="$wire.set('returnItems.{{ $item->id }}', Math.max(0, (parseInt($wire.returnItems[{{ $item->id }}]) || 0) - 1))">−</button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach

                                @error('returnItems')
                                <p class="text-xs font-bold text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Reason --}}
                            <div class="space-y-2">
                                <label class="text-[12px] font-black text-gray-700 dark:text-gray-300">دلیل مرجوعی</label>
                                <select wire:model.live="reason"
                                        class="w-full bg-white/80 dark:bg-black/40 border border-gray-200 dark:border-white/10 rounded-2xl px-5 py-4 text-[13px] font-bold text-gray-900 dark:text-white outline-none focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 transition-all">
                                    <option value="">انتخاب کنید</option>
                                    @foreach(\App\Models\ReturnRequest::REASONS as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('reason')
                                <p class="text-xs font-bold text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Description --}}
                            <div class="space-y-2">
                                <label class="text-[12px] font-black text-gray-700 dark:text-gray-300">
                                    توضیحات {{ $reason === 'other' ? '' : '(اختیاری)' }}
                                </label>
                                <textarea wire:model="description" rows="3"
                                          class="w-full bg-white/80 dark:bg-black/40 border border-gray-200 dark:border-white/10 rounded-2xl px-5 py-4 text-[13px] font-bold text-gray-900 dark:text-white outline-none focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 transition-all resize-none"
                                          placeholder="جزئیات مشکل کالا را بنویسید..."></textarea>
                                @error('description')
                                <p class="text-xs font-bold text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                <a href="{{ route('page.show', \App\Support\Sections\ReturnPolicy::PAGE_SLUG) }}" target="_blank" class="text-[11px] font-black text-primary-500 hover:underline">
                                    مطالعه شرایط مرجوعی
                                </a>
                                <button type="submit"
                                        wire:loading.attr="disabled"
                                        wire:target="submit"
                                        class="w-full sm:w-auto px-10 py-4 rounded-2xl bg-primary-500 text-white text-[12px] font-black shadow-lg shadow-primary-500/25 hover:bg-primary-600 transition-all active:scale-95 disabled:opacity-50">
                                    <span wire:loading.remove wire:target="submit">ثبت درخواست مرجوعی</span>
                                    <span wire:loading wire:target="submit">در حال ثبت...</span>
                                </button>
                            </div>
                        </form>
                    @elseif($this->eligibility['message'])
                        <div class="flex items-start gap-3 p-4 rounded-[1.5rem] bg-amber-500/10 border border-amber-500/20 text-[12px] font-bold text-amber-700 dark:text-amber-400 leading-6">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $this->eligibility['message'] }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
