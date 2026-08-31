<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use App\Models\Address;
use App\Models\Transaction;
use App\Models\Order;
use App\Models\ShippingSlot;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/*
 * این کامپوننت از روی یک Order که در صفحه‌ی قبل (سبد خرید) با وضعیت
 * 'pending' ساخته شده کار می‌کند. اینجا دیگر Cart خوانده نمی‌شود و
 * کد تخفیف هم دوباره پرسیده نمی‌شود — چون subtotal/discount_amount
 * از قبل روی همین Order ثبت شده‌اند.
 *
 * Route پیشنهادی:
 * Route::get('/checkout/{order}', Checkout::class)->name('checkout');
 *
 * فرض‌ها:
 * - Order::items()  hasMany OrderItem
 * - Order::invoice() hasOne Invoice
 * - OrderItem::variant() belongsTo ProductVariant (برای گرفتن تصویر محصول)
 * - Auth::user()->wallet() hasOne Wallet
 */

new class extends Component
{
    #[Locked]
    public int $orderId;
    public $paymentReference = '';
    // ---- آدرس ----
    public $addresses = [];
    public ?int $selectedAddressId = null;
    public bool $showAddressList = false;
    public bool $showAddForm = false;
    public bool $showModal = false;

    public string $newTitle = '';
    public string $newName = '';
    public string $newPhone = '';
    public string $newProvince = '';
    public string $newCity = '';
    public string $newAddress = '';
    public string $newPostalCode = '';

    // ---- ارسال ----
    public $shippingSlots = [];
    public ?int $selectedSlotId = null;

    // ---- پرداخت ----
    public string $paymentMethod = 'gateway'; // gateway | cod | transfer | wallet

    public string $errorMessage = '';

    public function mount($code): void
    {
        $order = Order::where('order_number', $code)
            ->where('status', 'pending')
            ->where('payment_status', 'unpaid')
            ->where('expires_at', '>', now())
            ->first();
        abort_unless($order->user_id === Auth::id(), 403);

        if ($order->status !== 'pending' || ($order->expires_at && $order->expires_at->isPast())) {
            // سفارش دیگر معتبر نیست؛ کاربر را به سبد خرید برمی‌گردانیم
            $this->redirectRoute('cart', navigate: true);
            return;
        }

        $this->orderId = $order->id;

        $userId = Auth::id();

        $this->addresses = Address::where('user_id', $userId)->orderByDesc('is_default')->get();
        $default = $this->addresses->firstWhere('is_default', true) ?? $this->addresses->first();
        $this->selectedAddressId = $order->address_id ?? $default?->id;

        $this->shippingSlots = ShippingSlot::where('is_active', true)
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->get();

        $this->selectedSlotId = $order->shipping_slot_id
            ?? $this->shippingSlots->firstWhere('is_holiday', false)?->id
            ?? $this->shippingSlots->first()?->id;

        $this->paymentMethod = $order->payment_method ?? 'gateway';
    }

    #[Computed]
    public function order()
    {
        return Order::with('items.variant.product', 'coupon')->findOrFail($this->orderId);
    }

    #[Computed]
    public function orderItems()
    {
        return $this->order->items;
    }

    // این‌ها دیگر «محاسبه» نمی‌شوند، مستقیم از رکورد ذخیره‌شده در سبد خوانده می‌شوند
    #[Computed]
    public function subtotal(): int
    {
        return (int) $this->order->subtotal;
    }

    #[Computed]
    public function discountAmount(): int
    {
        return (int) $this->order->discount_amount;
    }

    #[Computed]
    public function selectedSlot()
    {
        return $this->shippingSlots->firstWhere('id', $this->selectedSlotId);
    }

    #[Computed]
    public function shippingCost(): int
    {
        return (int) ($this->selectedSlot->cost ?? 0);
    }

    #[Computed]
    public function total(): int
    {
        return max(0, $this->subtotal - $this->discountAmount + $this->shippingCost);
    }

    #[Computed]
    public function walletBalance(): int
    {
        return (int) (Auth::user()?->wallet?->balance ?? 0);
    }

    // ---------------- آدرس ----------------

    public function toggleAddressList(): void
    {
        $this->showAddressList = !$this->showAddressList;
        $this->showAddForm = false;
    }

    public function selectAddress(int $id): void
    {
        $this->selectedAddressId = $id;
        $this->showAddressList = false;
    }

    public function openAddForm(): void
    {
        $this->showAddForm = true;
        $this->showAddressList = true;
    }

    public function cancelAddForm(): void
    {
        $this->showAddForm = false;
        $this->reset(['newTitle', 'newName', 'newPhone', 'newProvince', 'newCity', 'newAddress', 'newPostalCode']);
        $this->resetErrorBag();
    }

    public function saveAddress(): void
    {
        $this->validate([
            'newTitle'      => 'nullable|string|max:191',
            'newName'       => 'required|string|max:191',
            'newPhone'      => 'required|string|max:20',
            'newProvince'   => 'required|string|max:191',
            'newCity'       => 'required|string|max:191',
            'newAddress'    => 'required|string|min:10',
            'newPostalCode' => 'nullable|string|max:20',
        ], [], [
            'newTitle'      => 'عنوان آدرس',
            'newName'       => 'نام گیرنده',
            'newPhone'      => 'شماره تماس',
            'newProvince'   => 'استان',
            'newCity'       => 'شهر',
            'newAddress'    => 'نشانی',
            'newPostalCode' => 'کد پستی',
        ]);

        $address = Address::create([
            'user_id'       => Auth::id(),
            'title'         => $this->newTitle ?: null,
            'receiver_name' => $this->newName,
            'phone'         => $this->newPhone,
            'province'      => $this->newProvince,
            'city'          => $this->newCity,
            'address'       => $this->newAddress,
            'postal_code'   => $this->newPostalCode ?: null,
            'is_default'    => $this->addresses->isEmpty(),
        ]);

        $this->addresses->push($address);
        $this->selectedAddressId = $address->id;
        $this->cancelAddForm();
    }

    // ---------------- ارسال ----------------

    public function selectSlot(int $id): void
    {
        $slot = $this->shippingSlots->firstWhere('id', $id);
        if ($slot && !$slot->is_holiday) {
            $this->selectedSlotId = $id;
        }
    }

    // ---------------- پرداخت ----------------

    public function selectPayment(string $method): void
    {
        $this->paymentMethod = $method;
        if ($method == 'transfer'){
            $this->openModal();
        }
    }

    // ---------------- ثبت نهایی ----------------
    // تفاوت کلیدی با نسخه‌ی قبل: اینجا دیگر Order/Invoice جدید ساخته نمی‌شود،
    // همان رکورد pending آپدیت می‌شود.

    public function placeOrder()
    {
        $this->errorMessage = '';
        if ($this->paymentMethod === 'cod') {
            $this->errorMessage = 'این روش پرداخت غیرفعال است.';
            return;
        }
        if ($this->paymentMethod === 'transfer') {
            $this->openModal();
            return;
        }
        $order = Order::lockForUpdate()->findOrFail($this->orderId);

        if ($order->status !== 'pending' || ($order->expires_at && $order->expires_at->isPast())) {
            $this->errorMessage = 'مهلت این سفارش به پایان رسیده. لطفاً دوباره از سبد خرید اقدام کنید.';
            return;
        }

        if (!$this->selectedAddressId) {
            $this->errorMessage = 'لطفاً یک آدرس تحویل انتخاب کنید.';
            return;
        }

        if (!$this->selectedSlotId) {
            $this->errorMessage = 'لطفاً یک روز ارسال انتخاب کنید.';
            return;
        }

        if ($this->paymentMethod === 'wallet' && $this->walletBalance < $this->total) {
            $this->errorMessage = 'موجودی کیف پول شما کافی نیست.';
            return;
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'address_id'       => $this->selectedAddressId,
                'shipping_slot_id' => $this->selectedSlotId,
                'payment_method'   => $this->paymentMethod,
                'shipping_amount'  => $this->shippingCost,
                'total_amount'     => $this->total,
            ]);

            $order->invoice?->update([
                'shipping_amount' => $this->shippingCost,
                'total_amount'    => $this->total,
            ]);

            if ($this->paymentMethod === 'wallet') {
                Auth::user()->wallet()->decrement('balance', $this->total);

                Payment::create([
                    'order_id' => $order->id,
                    'method'   => 'wallet',
                    'amount'   => $this->total,
                    'status'   => 'success',
                    'paid_at'  => now(),
                ]);

                $order->update([
                    'payment_status' => 'paid',
                    'status'         => 'processing',
                    'expires_at'     => null,
                ]);
                $order->invoice?->update(['status' => 'paid', 'paid_at' => now()]);
            }
        });

        if ($this->paymentMethod === 'gateway') {
            return redirect()->route('payment.gateway', ['order' => $order->id]);
        }

        return redirect()->route('order.success', ['order' => $order->id]);
    }
    public function openModal(): void
    {
        abort_unless(auth()->check(), 403);

        $this->resetValidation();


        $this->showModal = true;
    }
    public function closeWalletModal(): void
    {
        $this->resetValidation();


        $this->showModal = false;
    }

    public function submitCardToCardPayment()
    {
    $this->errorMessage = '';

    $this->validate([
        'paymentReference' => [
            'required',
            'string',
            'max:100',
        ],
    ], [
        'paymentReference.required' => 'لطفاً شناسه پرداخت یا کد پیگیری را وارد کنید.',
        'paymentReference.max'      => 'شناسه پرداخت نمی‌تواند بیشتر از ۱۰۰ کاراکتر باشد.',
    ]);

    try {

        DB::transaction(function () {

            /*
            |--------------------------------------------------------------------------
            | دریافت سفارش
            |--------------------------------------------------------------------------
            */

            $order = Order::lockForUpdate()
                ->find($this->orderId);

            if (!$order) {
                throw new \Exception('سفارش موردنظر پیدا نشد.');
            }

            /*
            |--------------------------------------------------------------------------
            | بررسی وضعیت سفارش
            |--------------------------------------------------------------------------
            */

            if ($order->status !== 'pending') {
                throw new \Exception(
                    'این سفارش دیگر قابل پرداخت نیست.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | بررسی انقضای سفارش
            |--------------------------------------------------------------------------
            */

            if (
                $order->expires_at &&
                $order->expires_at->isPast()
            ) {
                throw new \Exception(
                    'مهلت پرداخت این سفارش به پایان رسیده است.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | بررسی تکراری نبودن شناسه پرداخت
            |--------------------------------------------------------------------------
            */

            $referenceExists = Transaction::where(
                'reference_id',
                $this->paymentReference
            )
                ->where('reference_type', 'card_to_card')
                ->exists();

            if ($referenceExists) {
                throw new \Exception(
                    'این شناسه پرداخت قبلاً ثبت شده است.'
                );
            }
            /*
            |--------------------------------------------------------------------------
            | پیدا کردن پرداخت کارت به کارت سفارش
            |--------------------------------------------------------------------------
            */

            $payment = Payment::where('order_id', $order->id)
                ->where('method', 'transfer')
                ->where('status', 'pending')
                ->latest()
                ->first();

            /*
            |--------------------------------------------------------------------------
            | ایجاد Payment در صورت عدم وجود
            |--------------------------------------------------------------------------
            */

            if (!$payment) {

                $payment = Payment::create([
                    'order_id' => $order->id,
                    'method'   => 'transfer',
                    'amount'   => $order->total_amount,
                    'status'   => 'pending',
                ]);

            } else {

                /*
                |--------------------------------------------------------------------------
                | به‌روزرسانی مبلغ پرداخت
                |--------------------------------------------------------------------------
                */

                $payment->update([
                    'amount' => $order->total_amount,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | ثبت Transaction
            |--------------------------------------------------------------------------
            */

            Transaction::create([
                'user_id'        => auth()->id(),
                'type'           => 'credit',
                'category'       => 'order_payment',
                'amount'         => $order->total_amount,
                'status'         => 'pending',
                'payment_id'     => $payment->id,
                'order_id'       => $order->id,
                'reference_type' => 'card_to_card',
                'reference_id'   => $this->paymentReference,
                'description'    => 'پرداخت کارت به کارت سفارش ' . $order->order_number,
            ]);

            /*
            |--------------------------------------------------------------------------
            | تغییر وضعیت سفارش
            |--------------------------------------------------------------------------
            */

            $order->update([
                'payment_method' => 'transfer',
                'payment_status' => 'pending',
            ]);
        });

    } catch (\Throwable $e) {

        $this->errorMessage = $e->getMessage();

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | موفقیت
    |--------------------------------------------------------------------------
    */

    session()->flash(
        'success',
        'اطلاعات پرداخت شما با موفقیت ثبت شد و پس از بررسی تأیید خواهد شد.'
    );
    return redirect()->route(
        'order.payment.result',
        ['code' => $this->orderId]
    );
}


};
?>

<div>
    <main class="space-y-12">

        <!-- CONTENT -->
        <section class="relative py-16 transition-colors duration-700">

            {{-- استپ‌لاین بالای صفحه بدون تغییر باقی می‌ماند --}}

            <div class="container" dir="rtl">

                @if ($errorMessage)
                    <div class="mb-6 p-4 bg-rose-500/10 border border-rose-500/30 text-rose-600 rounded-2xl text-sm font-bold">
                        {{ $errorMessage }}
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                    <div class="lg:col-span-9 space-y-6">

                        <!-- آدرس تحویل -->
                        <div class="bg-white/40 dark:bg-white/[0.02] backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-8 shadow-xl" dir="rtl">

                            <div class="flex items-center justify-between mb-8">
                                <div>
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white">آدرس تحویل سفارش</h3>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Shipping Details</p>
                                </div>
                                <button wire:click="toggleAddressList" type="button"
                                        class="px-4 py-2 bg-blue-600/10 text-blue-600 rounded-xl text-xs font-black hover:bg-blue-600 hover:text-white transition-all">
                                    تغییر یا ویرایش
                                </button>
                            </div>

                            @php $activeAddress = $addresses->firstWhere('id', $selectedAddressId); @endphp

                            @if ($activeAddress)
                                <div class="p-6 bg-blue-500/5 rounded-3xl border border-blue-500/10">
                                    @if ($activeAddress->title)
                                        <span class="text-[10px] font-black text-blue-600">{{ $activeAddress->title }}</span>
                                    @endif
                                    <p class="text-gray-800 dark:text-gray-200 font-bold leading-loose text-sm mt-1">
                                        {{ $activeAddress->province }}، {{ $activeAddress->city }}، {{ $activeAddress->address }}
                                        @if ($activeAddress->postal_code)
                                            <span class="text-gray-400 text-xs">(کد پستی: {{ $activeAddress->postal_code }})</span>
                                        @endif
                                    </p>
                                    <div class="flex items-center gap-6 mt-4 text-[11px] text-gray-500 font-bold">
                                        <span><b>{{ $activeAddress->receiver_name }}</b></span>
                                        <span dir="ltr"><b>{{ $activeAddress->phone }}</b></span>
                                    </div>
                                </div>
                            @else
                                <div class="p-6 bg-amber-500/5 rounded-3xl border border-amber-500/10 text-sm font-bold text-amber-600">
                                    هنوز آدرسی ثبت نکرده‌اید.
                                </div>
                            @endif

                            @if ($showAddressList)
                                <div class="mt-6 space-y-3">
                                    @foreach ($addresses as $addr)
                                        <div wire:click="selectAddress({{ $addr->id }})"
                                             class="address-item cursor-pointer p-5 rounded-2xl dark:bg-white/5 border-2 transition-all
                                             {{ $addr->id === $selectedAddressId ? 'border-blue-500 bg-white/60 shadow-sm' : 'border-transparent bg-white/30' }}">
                                            <div class="flex justify-between items-center">
                                                <span class="text-[10px] font-black text-blue-600">{{ $addr->is_default ? 'آدرس اصلی' : ($addr->title ?? 'آدرس') }}</span>
                                                <div class="status-dot w-4 h-4 rounded-full {{ $addr->id === $selectedAddressId ? 'bg-blue-500 border-2 border-blue-500' : 'bg-transparent border-2 border-gray-300' }}"></div>
                                            </div>
                                            <p class="text-xs text-gray-700 dark:text-gray-300 mt-2 font-bold leading-relaxed">
                                                {{ $addr->province }}، {{ $addr->city }}، {{ $addr->address }}
                                            </p>
                                        </div>
                                    @endforeach

                                    <button wire:click="openAddForm" type="button"
                                            class="w-full py-4 border-2 border-dashed border-gray-300 dark:border-white/10 rounded-2xl text-gray-400 text-xs font-black hover:bg-blue-50/50 hover:border-blue-500/50 transition-all">
                                        + افزودن آدرس جدید
                                    </button>
                                </div>
                            @endif

                            @if ($showAddForm)
                                <div class="mt-6 space-y-4">
                                    <div>
                                        <input type="text" wire:model="newTitle" placeholder="عنوان آدرس (مثلاً: خانه، محل کار) — اختیاری"
                                               class="w-full bg-white/50 dark:bg-white/5 border border-white/60 dark:border-white/10 rounded-2xl px-4 py-3 text-sm outline-none focus:border-blue-500 font-bold">
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <input type="text" wire:model="newName" placeholder="نام و نام خانوادگی گیرنده"
                                                   class="w-full bg-white/50 dark:bg-white/5 border border-white/60 dark:border-white/10 rounded-2xl px-4 py-3 text-sm outline-none focus:border-blue-500 font-bold">
                                            @error('newName') <span class="text-[10px] text-rose-500 font-bold">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <input type="tel" wire:model="newPhone" placeholder="شماره تماس (مثلاً ۰۹۱۲...)" dir="ltr"
                                                   class="w-full bg-white/50 dark:bg-white/5 border border-white/60 dark:border-white/10 rounded-2xl px-4 py-3 text-sm outline-none focus:border-blue-500 text-left font-bold">
                                            @error('newPhone') <span class="text-[10px] text-rose-500 font-bold">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div>
                                            <input type="text" wire:model="newProvince" placeholder="استان"
                                                   class="w-full bg-white/50 dark:bg-white/5 border border-white/60 dark:border-white/10 rounded-2xl px-4 py-3 text-sm outline-none focus:border-blue-500 font-bold">
                                            @error('newProvince') <span class="text-[10px] text-rose-500 font-bold">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <input type="text" wire:model="newCity" placeholder="شهر"
                                                   class="w-full bg-white/50 dark:bg-white/5 border border-white/60 dark:border-white/10 rounded-2xl px-4 py-3 text-sm outline-none focus:border-blue-500 font-bold">
                                            @error('newCity') <span class="text-[10px] text-rose-500 font-bold">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <input type="text" wire:model="newPostalCode" placeholder="کد پستی (اختیاری)" dir="ltr"
                                                   class="w-full bg-white/50 dark:bg-white/5 border border-white/60 dark:border-white/10 rounded-2xl px-4 py-3 text-sm outline-none focus:border-blue-500 text-left font-bold">
                                            @error('newPostalCode') <span class="text-[10px] text-rose-500 font-bold">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div>
                                        <textarea wire:model="newAddress" placeholder="نشانی دقیق پستی (خیابان، کوچه، پلاک، واحد...)" rows="3"
                                                  class="w-full bg-white/50 dark:bg-white/5 border border-white/60 dark:border-white/10 rounded-2xl px-4 py-3 text-sm outline-none focus:border-blue-500 resize-none font-bold"></textarea>
                                        @error('newAddress') <span class="text-[10px] text-rose-500 font-bold">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="flex gap-3 pt-2">
                                        <button wire:click="saveAddress" type="button"
                                                class="flex-1 bg-blue-600 text-white py-3.5 rounded-2xl font-black text-sm shadow-lg shadow-blue-500/30">
                                            ثبت و انتخاب این آدرس
                                        </button>
                                        <button wire:click="cancelAddForm" type="button"
                                                class="px-6 bg-gray-100 dark:bg-white/5 text-gray-500 py-3.5 rounded-2xl font-black text-sm">
                                            انصراف
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- روش و زمان ارسال -->
                        <div class="bg-white/40 dark:bg-white/[0.02] backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-8 shadow-xl" dir="rtl">

                            <div class="flex items-center gap-3 mb-8">
                                <div>
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white">روش و زمان ارسال</h3>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Delivery Schedule</p>
                                </div>
                            </div>

                            <!-- محصولات سفارش (از order_items، نه cart_items) -->
                            <div class="flex gap-4 mb-10 overflow-x-auto pb-4 ps-3">
                                @foreach ($this->orderItems as $item)
                                    <div class="flex-shrink-0 w-20 h-20 bg-white dark:bg-white/5 rounded-3xl border border-white/60 dark:border-white/10 p-2 relative">
                                        <img src="{{ $item->variant->product->main_image_url ?? asset('assets/images/product/default.png') }}"
                                             class="w-full h-full object-contain rounded-2xl" alt="{{ $item->product_name }}">
                                        <span class="absolute -bottom-2 -right-2 bg-gray-800 text-white text-[10px] font-black px-2 py-1 rounded-lg">{{ $item->quantity }} عدد</span>
                                    </div>
                                @endforeach
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-4">
                                @foreach ($shippingSlots as $slot)
                                    <button wire:click="selectSlot({{ $slot->id }})" type="button"
                                            @disabled($slot->is_holiday)
                                            class="day-card group relative p-5 rounded-[2.5rem] border-2 text-center transition-all duration-300 shadow-sm
                                            {{ $slot->id === $selectedSlotId ? 'border-blue-600 bg-white/80 dark:bg-blue-600/10' : 'border-white/60 dark:border-white/5 bg-white/30 dark:bg-white/[0.02]' }}
                                            {{ $slot->is_holiday ? 'opacity-50 cursor-not-allowed' : '' }}">

                                        @if ($slot->id === $selectedSlotId)
                                            <div class="absolute top-3 left-3 w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center">
                                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                            </div>
                                        @endif

                                        <span class="block text-[10px] font-black uppercase tracking-widest mb-1 {{ $slot->is_holiday ? 'text-rose-400' : 'text-gray-400' }}">
                                            {{ $slot->is_holiday ? 'تعطیل' : ($slot->label ?? '-') }}
                                        </span>
                                        <span class="block text-sm font-bold text-gray-600 dark:text-gray-400">
                                            {{ \Morilog\Jalali\Jalalian::fromDateTime($slot->date)->format('l j F') }}
                                        </span>
                                        <div class="mt-4 py-1.5 px-2 rounded-xl {{ $slot->cost > 0 ? 'bg-gray-50 dark:bg-white/5' : 'bg-emerald-500/10' }}">
                                            <span class="block text-[10px] font-black {{ $slot->cost > 0 ? 'text-gray-400' : 'text-emerald-600' }}">
                                                {{ $slot->cost > 0 ? number_format($slot->cost) . ' تومان' : 'ارسال رایگان' }}
                                            </span>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- روش پرداخت -->
                        <div class="bg-white/40 dark:bg-white/[0.02] backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-8 shadow-xl" dir="rtl">

                            <div class="flex items-center gap-3 mb-8">
                                <div>
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white">انتخاب روش پرداخت</h3>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Payment Gateway</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                @php
                                    $paymentOptions = [
                                        'gateway' => [
                                            'title' => 'درگاه بانکی (آنلاین)',
                                            'sub'   => '۱٪ سود بیشتر',
                                            'icon'  => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
                                        ],
                                        'cod' => [
                                            'title' => 'پرداخت در محل',
                                            'sub'   => 'نقدی یا با کارت‌خوان',
                                            'icon'  => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
                                        ],
                                        'transfer' => [
                                            'title' => 'کارت به کارت',
                                            'sub'   => 'ارسال فیش در چت',
                                            'icon'  => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
                                        ],
                                        'wallet' => [
                                            'title' => 'کیف پول زارا',
                                            'sub'   => 'موجودی: ' . number_format($this->walletBalance) . ' تومان',
                                            'icon'  => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                                        ],
                                    ];
                                @endphp

                                @foreach ($paymentOptions as $key => $opt)
                                    <label wire:click="selectPayment('{{ $key }}')"
                                           class="payment-card relative flex items-center p-6 border-2 rounded-[2rem] cursor-pointer transition-all
                                           {{ $paymentMethod === $key ? 'border-blue-600 bg-white shadow-sm' : 'border-gray-100 dark:border-white/5 bg-white/50 dark:bg-white/[0.02]' }}">
                                        <input type="radio" name="payment" value="{{ $key }}" @checked($paymentMethod === $key) class="sr-only">
                                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center ml-4 transition-all {{ $paymentMethod === $key ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/20' : 'bg-gray-100 dark:bg-white/5 text-gray-400' }}">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $opt['icon'] }}"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <span class="block text-sm font-black text-gray-900 dark:text-white">{{ $opt['title'] }}</span>
                                            <span class="text-[10px] {{ $key === 'wallet' ? 'text-blue-500 font-black' : 'text-gray-400 font-bold' }}">{{ $opt['sub'] }}</span>
                                        </div>
                                        <div class="w-6 h-6 border-2 rounded-full flex items-center justify-center {{ $paymentMethod === $key ? 'border-blue-600 bg-blue-600' : 'border-gray-200 dark:border-white/10' }}">
                                            @if ($paymentMethod === $key)
                                                <div class="w-2 h-2 bg-white rounded-full"></div>
                                            @endif
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                    </div>

                    <!-- خلاصه فاکتور -->
                    <div class="lg:col-span-3">
                        <div class="sticky top-8 group">
                            <div class="absolute -top-10 -left-10 w-40 h-40 bg-blue-500/10 rounded-full blur-[80px]"></div>

                            <div class="relative bg-white/20 dark:bg-black/20 backdrop-blur-[60px] border border-white/50 dark:border-white/5 rounded-[3.5rem] p-2 shadow-lg overflow-hidden">

                                <div class="p-8 pb-4 text-center">
                                    <h3 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">خلاصه فاکتور</h3>
                                    <p class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-[4px] mt-1">Order Summary</p>
                                </div>

                                <div class="bg-white/40 dark:bg-white/[0.02] rounded-[3rem] p-8 space-y-6 border border-white/60 dark:border-white/5 shadow-inner">

                                    <div class="space-y-4">
                                        <div class="flex justify-between items-center group/row">
                                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 group-hover/row:text-gray-900 dark:group-hover/row:text-white transition-colors">قیمت کالاها ({{ $this->orderItems->count() }})</span>
                                            <div class="flex-1 border-b border-dashed border-gray-300 dark:border-white/10 mx-4 mb-1"></div>
                                            <span class="text-sm font-black text-gray-900 dark:text-white">{{ number_format($this->subtotal) }}</span>
                                        </div>

                                        @if ($this->discountAmount > 0)
                                            <div class="flex justify-between items-center group/row">
                                                <span class="text-xs font-bold text-gray-500 dark:text-gray-400 group-hover/row:text-rose-500 transition-colors">
                                                    تخفیف {{ $this->order->coupon?->code ? '(کد ' . $this->order->coupon->code . ')' : '' }}
                                                </span>
                                                <div class="flex-1 border-b border-dashed border-gray-300 dark:border-white/10 mx-4 mb-1"></div>
                                                <span class="text-sm font-black text-rose-500 tracking-tighter">{{ number_format($this->discountAmount) }}-</span>
                                            </div>
                                        @endif

                                        <div class="flex justify-between items-center group/row">
                                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400">هزینه ارسال</span>
                                            <div class="flex-1 border-b border-dashed border-gray-300 dark:border-white/10 mx-4 mb-1"></div>
                                            <div>
                                                @if ($this->shippingCost > 0)
                                                    <span class="text-sm font-black text-gray-900 dark:text-white">{{ number_format($this->shippingCost) }}</span>
                                                @else
                                                    <span class="text-[10px] font-black text-emerald-500 bg-emerald-500/10 px-2 py-0.5 rounded-lg">رایگان</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="relative h-px bg-gradient-to-r from-transparent via-gray-300 dark:via-white/10 to-transparent my-2"></div>

                                    <div class="flex flex-col items-center gap-1">
                                        <span class="text-[10px] font-black text-blue-600 dark:text-blue-400 uppercase tracking-[5px]">Net Payable</span>
                                        <div class="flex items-baseline gap-1">
                                            <span class="text-4xl font-black text-gray-900 dark:text-white tracking-tighter transition-all duration-300">{{ number_format($this->total) }}</span>
                                            <span class="text-[10px] font-bold text-gray-400">تومان</span>
                                        </div>
                                    </div>

                                    {{--
                                        کادر کد تخفیف عمداً اینجا نیست: تخفیف در صفحه‌ی سبد خرید
                                        اعمال و روی همین سفارش ثبت شده. اگر کاربر می‌خواهد کد را
                                        عوض کند باید به سبد خرید برگردد.
                                    --}}

                                </div>

                                <div class="p-6">
                                    <button wire:click="placeOrder" wire:loading.attr="disabled" wire:target="placeOrder" type="button"
                                            class="group/pay relative w-full h-20 bg-blue-600 dark:bg-blue-500 rounded-[2.2rem] overflow-hidden transition-all duration-500 shadow-[0_20px_40px_-10px_rgba(37,99,235,0.5)] hover:shadow-[0_25px_50px_-12px_rgba(37,99,235,0.7)] hover:-translate-y-1 active:scale-95">

                                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover/pay:animate-[shimmer_1.5s_infinite]"></div>

                                        <div class="relative flex items-center justify-between px-8">
                                            <span class="text-white font-black text-xl tracking-tight" wire:loading.remove wire:target="placeOrder">تایید و پرداخت</span>
                                            <span class="text-white font-black text-xl tracking-tight" wire:loading wire:target="placeOrder">در حال ثبت سفارش...</span>
                                            <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-md border border-white/30 transition-all duration-500 shadow-inner">
                                                <svg class="rotate-180 w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </button>

                                    <p class="text-[9px] text-center text-gray-400 font-bold mt-4 leading-relaxed px-4">
                                        با ثبت سفارش، قوانین و مقررات زاراچرم را می‌پذیرم.
                                    </p>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </main>
    @if($showModal)

        <div
            class="fixed inset-0 z-[999] flex items-center justify-center p-4 sm:p-6"
            wire:key="wallet-modal"
            dir="rtl"
        >
            {{-- Overlay --}}
            <div
                wire:click="closeWalletModal"
                class="absolute inset-0 bg-gray-950/60 backdrop-blur-md"
            ></div>

            {{-- Card --}}
            <div
                class="relative w-full max-w-md overflow-hidden
               rounded-[2rem]
               border border-white/20 dark:border-white/10
               bg-white/95 dark:bg-gray-950/95
               shadow-[0_25px_80px_rgba(0,0,0,0.25)]
               backdrop-blur-xl"
            >

                {{-- Decorative --}}
                <div
                    class="absolute -top-24 -right-24
                   w-48 h-48 rounded-full
                   bg-primary-500/10 blur-3xl
                   pointer-events-none"
                ></div>

                {{-- Header --}}
                <div class="relative flex items-center justify-between px-6 pt-6 pb-5">

                    <div class="flex items-center gap-3">

                        <div
                            class="w-11 h-11 rounded-2xl
                           bg-primary-500/10
                           flex items-center justify-center"
                        >
                            <svg
                                class="w-5 h-5 text-primary-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                                />
                            </svg>
                        </div>

                        <div>
                            <h3 class="text-sm font-black text-gray-900 dark:text-white">
                                افزایش موجودی کیف پول
                            </h3>

                            <p class="mt-1 text-[10px] font-bold text-gray-400">
                                پرداخت کارت به کارت
                            </p>
                        </div>

                    </div>

                    {{-- Close --}}
                    <button
                        wire:click="closeWalletModal"
                        type="button"
                        class="w-9 h-9 rounded-xl
                       bg-gray-100 dark:bg-white/5
                       text-gray-400
                       hover:text-gray-700
                       dark:hover:text-white
                       transition"
                    >
                        <svg
                            class="w-4 h-4 mx-auto"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>

                </div>

                {{-- Body --}}
                <div class="relative px-6 pb-6">

                    {{-- Amount --}}
                    <div
                        class="p-4 rounded-2xl
                       bg-primary-500/5
                       border border-primary-500/10
                       mb-5"
                    >
                        <div class="flex items-center justify-between">

                    <span class="text-[10px] font-bold text-gray-400">
                        مبلغ قابل پرداخت
                    </span>

                            <div class="flex items-baseline gap-1">
                        <span class="text-xl font-black text-primary-500">
                           {{ number_format($this->total) }}
                        </span>

                                <span class="text-[9px] font-bold text-gray-400">
                            تومان
                        </span>
                            </div>

                        </div>
                    </div>


                    {{-- Card Number --}}
                    <div class="mb-5">

                        <label
                            class="block mb-2 text-[11px] font-black
                           text-gray-700 dark:text-gray-300"
                        >
                            شماره کارت جهت واریز
                        </label>

                        <div
                            class="relative flex items-center
                           h-16 px-4
                           rounded-2xl
                           bg-gray-50 dark:bg-white/[0.04]
                           border border-gray-200 dark:border-white/10"
                        >

                            <div
                                class="w-10 h-10 rounded-xl
                               bg-primary-500/10
                               flex items-center justify-center
                               text-primary-500 ml-3"
                            >
                                <svg
                                    class="w-5 h-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                                    />
                                </svg>
                            </div>

                            <div class="flex-1">

                        <span
                            class="block text-base font-black
                                   text-gray-900 dark:text-white
                                   tracking-[2px]"
                            dir="ltr"
                        >
                            6037 - 9918 - 1234 - 5678
                        </span>

                                <span class="block mt-1 text-[9px] font-bold text-gray-400">
                            به نام: نام صاحب حساب
                        </span>

                            </div>

                        </div>

                        {{-- Copy --}}
                        <button
                            type="button"
                            onclick="navigator.clipboard.writeText('6037991812345678')"
                            class="mt-2 text-[9px] font-black
                           text-primary-500
                           hover:text-primary-600
                           transition"
                        >
                            کپی شماره کارت
                        </button>

                    </div>


                    {{-- Warning / Instruction --}}
                    <div
                        class="mb-5 flex items-start gap-3
                       p-3.5 rounded-2xl
                       bg-amber-500/5
                       border border-amber-500/10"
                    >

                        <svg
                            class="w-4 h-4 mt-0.5 shrink-0 text-amber-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01M10.29 3.86l-7.82 14A2 2 0 004.21 21h15.58a2 2 0 001.74-3.14l-7.82-14a2 2 0 00-3.42 0z"
                            />
                        </svg>

                        <p
                            class="text-[9px] leading-5 font-bold
                           text-gray-500 dark:text-gray-400"
                        >
                            مبلغ بالا را به شماره کارت اعلام‌شده واریز کنید.
                            سپس شناسه پرداخت یا کد پیگیری تراکنش را در کادر زیر وارد کنید.
                        </p>

                    </div>


                    {{-- Payment ID --}}
                    <div>

                        <label
                            for="paymentReference"
                            class="block mb-2 text-[11px] font-black
                           text-gray-700 dark:text-gray-300"
                        >
                            شناسه پرداخت / کد پیگیری
                        </label>

                        <input
                            id="paymentReference"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            wire:model.live="paymentReference"
                            placeholder="مثلاً ۱۲۳۴۵۶۷۸۹"
                            class="w-full h-14
                           rounded-2xl
                           border border-gray-200 dark:border-white/10
                           bg-gray-50 dark:bg-white/[0.04]
                           px-4
                           text-sm font-black
                           text-gray-900 dark:text-white
                           placeholder:text-gray-300 dark:placeholder:text-gray-600
                           focus:border-primary-500
                           focus:ring-4 focus:ring-primary-500/10
                           outline-none transition"
                            dir="ltr"
                        >

                        @error('paymentReference')
                        <p class="mt-2 text-[10px] font-bold text-red-500">
                            {{ $message }}
                        </p>
                        @enderror

                    </div>


                    {{-- Submit --}}
                    <button
                        wire:click="submitCardToCardPayment"
                        wire:loading.attr="disabled"
                        wire:target="submitCardToCardPayment"
                        type="button"
                        class="mt-5 w-full h-14
                       rounded-2xl
                       bg-primary-500
                       text-white
                       text-[11px]
                       font-black
                       shadow-lg shadow-primary-500/25
                       hover:bg-primary-600
                       disabled:opacity-60
                       disabled:cursor-not-allowed
                       transition-all
                       active:scale-[.98]"
                    >

                <span
                    wire:loading.remove
                    wire:target="submitCardToCardPayment"
                >
                    پرداخت کردم
                </span>

                        <span
                            wire:loading
                            wire:target="submitCardToCardPayment"
                            class="flex items-center justify-center gap-2"
                        >

                    <svg
                        class="w-4 h-4 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>

                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                        ></path>
                    </svg>

                    در حال ثبت پرداخت...

                </span>

                    </button>


                    {{-- Footer --}}
                    <p
                        class="mt-4 text-center text-[9px]
                       leading-5 font-bold
                       text-gray-400"
                    >
                        پس از بررسی و تأیید پرداخت توسط مدیریت،
                        مبلغ به کیف پول شما اضافه خواهد شد.
                    </p>

                </div>

            </div>

        </div>

    @endif

</div>
