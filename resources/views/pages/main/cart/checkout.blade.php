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

    // ---- ارسال (تاریخ‌ها از «تنظیمات ارسال» داشبورد محاسبه می‌شوند) ----
    public array $deliveryDates = [];
    public ?string $deliveryDate = null;

    // ---- پرداخت (روش‌ها از پنل > تنظیمات پرداخت؛ منطق در app/Payments) ----
    public string $paymentMethod = '';
    public ?int $gatewayId = null;      // درگاه انتخابی در روش «درگاه بانکی»
    public ?int $bankCardId = null;
    public string $payerName = '';
    public string $payerCard = '';

    public string $errorMessage = '';

    public function mount($code): void
    {
        $order = Order::where('order_number', $code)
            ->where('status', 'pending')
            ->where('payment_status', 'unpaid')
            ->where('expires_at', '>', now())
            ->first();

        // سفارش پرداخت‌شده، منقضی یا ناموجود => بازگشت به سفارش‌های من (به‌جای خطای ۵۰۰)
        if (!$order) {
            session()->flash('error', 'این سفارش قابل پرداخت نیست یا مهلت پرداخت آن به پایان رسیده است.');
            $this->redirectRoute('user.dashboard', ['tab' => 'orders']);
            return;
        }

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

        $this->loadDeliveryDates($order->delivery_date?->toDateString());

        // روش قبلی (اگر هنوز قابل استفاده باشد) یا اولین روش فعال
        $usable = $this->paymentOptions->whereNull('disabled')->pluck('key');
        $this->paymentMethod = $usable->contains($order->payment_method) ? $order->payment_method : (string) $usable->first();
        $this->gatewayId = $this->gatewayOptions->firstWhere('disabled', null)['id'] ?? null;
        $this->bankCardId = $this->bankCards->first()?->id;
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
    public function selectedDelivery(): ?array
    {
        return collect($this->deliveryDates)->firstWhere('date', $this->deliveryDate);
    }

    #[Computed]
    public function shippingCost(): int
    {
        return (int) ($this->selectedDelivery['cost'] ?? 0);
    }

    protected function deliveryService(): \App\Services\Delivery\DeliveryScheduleService
    {
        return app(\App\Services\Delivery\DeliveryScheduleService::class);
    }

    // تاریخ‌های مجاز؛ انتخاب قبلی اگر هنوز مجاز باشد حفظ می‌شود، وگرنه زودترین تاریخ
    protected function loadDeliveryDates(?string $preferred = null): void
    {
        $this->deliveryDates = $this->deliveryService()->availableDates()->all();
        $dates = array_column($this->deliveryDates, 'date');

        $this->deliveryDate = in_array($preferred, $dates, true) ? $preferred : ($dates[0] ?? null);
        unset($this->selectedDelivery, $this->shippingCost, $this->total);
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

    #[Computed]
    public function paymentOptions()
    {
        return app(\App\Payments\PaymentManager::class)->checkoutOptions($this->order, Auth::user());
    }

    // درگاه‌های فعال و تنظیم‌شده (زرین‌پال، اسنپ‌پی، دیجی‌پی، ...) با دلیل عدم دسترسی برای این سفارش
    #[Computed]
    public function gatewayOptions()
    {
        return app(\App\Payments\PaymentManager::class)->gatewayOptions($this->order)->map(fn ($opt) => [
            'id' => $opt['gateway']->id,
            'title' => $opt['gateway']->title,
            'disabled' => $opt['disabled'],
        ])->values();
    }

    #[Computed]
    public function bankCards()
    {
        return \App\Models\BankCard::active()->get();
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

    public function selectDeliveryDate(string $date): void
    {
        if (in_array($date, array_column($this->deliveryDates, 'date'), true)) {
            $this->deliveryDate = $date;
            unset($this->selectedDelivery, $this->shippingCost, $this->total);
        }
    }

    /**
     * اعتبارسنجی تاریخ ارسال در لحظه ثبت (ممکن است از زمان باز شدن صفحه، cut-off گذشته باشد)
     */
    protected function validatedDelivery(): ?array
    {
        $delivery = $this->deliveryService()->find($this->deliveryDate);

        if (! $delivery) {
            $this->loadDeliveryDates();
            $this->errorMessage = 'تاریخ ارسال انتخاب‌شده دیگر در دسترس نیست؛ لطفاً دوباره یک تاریخ انتخاب کنید.';
        }

        return $delivery;
    }

    // ---------------- پرداخت ----------------

    public function selectPayment(string $method): void
    {
        $option = $this->paymentOptions->firstWhere('key', $method);

        if (! $option || $option['disabled']) {
            return;
        }

        $this->paymentMethod = $method;
        $this->errorMessage = '';

        if ($method === 'transfer') {
            $this->openModal();
        }
    }

    public function selectGateway(int $id): void
    {
        $option = $this->gatewayOptions->firstWhere('id', $id);

        if (! $option || $option['disabled']) {
            return;
        }

        $this->gatewayId = $id;
        $this->errorMessage = '';
    }

    /**
     * ذخیره آدرس، تاریخ و هزینه ارسال روی سفارش/فاکتور قبل از شروع پرداخت
     */
    protected function saveShippingDetails(array $delivery): ?\App\Models\Order
    {
        return DB::transaction(function () use ($delivery) {
            $order = Order::lockForUpdate()->findOrFail($this->orderId);

            if ($order->status !== 'pending' || $order->payment_status === 'paid' || ($order->expires_at && $order->expires_at->isPast())) {
                $this->errorMessage = 'مهلت این سفارش به پایان رسیده یا قبلاً پرداخت شده است.';
                return null;
            }

            $shipping = (int) $delivery['cost'];
            $total = max(0, (int) $order->subtotal - (int) $order->discount_amount + (int) $order->tax_amount + $shipping);

            $order->update([
                'address_id'       => $this->selectedAddressId,
                'delivery_date'    => $delivery['date'],
                'shipping_slot_id' => $delivery['slot_id'],
                'shipping_amount'  => $shipping,
                'total_amount'     => $total,
            ]);

            $order->invoice?->update([
                'shipping_amount' => $shipping,
                'total_amount'    => $total,
            ]);

            return $order->fresh();
        });
    }

    /**
     * نتیجه روش پرداخت => انتقال کاربر
     */
    protected function handlePaymentResult(\App\Payments\PaymentResult $result)
    {
        if ($result->isFailed()) {
            $this->errorMessage = $result->message ?? 'پرداخت انجام نشد.';
            unset($this->paymentOptions);
            return null;
        }

        if ($result->type === \App\Payments\PaymentResult::REDIRECT) {
            return redirect()->away($result->redirectUrl);
        }

        if ($result->message) {
            session()->flash('success', $result->message);
        }

        return redirect()->route('order.payment.result', [
            'code' => $this->order->order_number,
            'payment' => $result->payment?->uuid,
        ]);
    }

    // ---------------- ثبت نهایی ----------------
    // تفاوت کلیدی با نسخه‌ی قبل: اینجا دیگر Order/Invoice جدید ساخته نمی‌شود،
    // همان رکورد pending آپدیت می‌شود.

    public function placeOrder()
    {
        $this->errorMessage = '';

        if (!$this->selectedAddressId) {
            $this->errorMessage = 'لطفاً یک آدرس تحویل انتخاب کنید.';
            return;
        }

        if (!$this->deliveryDate) {
            $this->errorMessage = 'لطفاً یک روز ارسال انتخاب کنید.';
            return;
        }

        $manager = app(\App\Payments\PaymentManager::class);

        if (! $this->paymentMethod || ! $manager->isMethodUsable($this->paymentMethod, $this->order, Auth::user())) {
            $this->errorMessage = 'روش پرداخت انتخاب‌شده در دسترس نیست.';
            unset($this->paymentOptions);
            return;
        }

        // کارت‌به‌کارت: اطلاعات واریز در مودال گرفته می‌شود
        if ($this->paymentMethod === 'transfer') {
            $this->openModal();
            return;
        }

        $delivery = $this->validatedDelivery();
        if (! $delivery) {
            return;
        }

        $order = $this->saveShippingDetails($delivery);
        if (! $order) {
            return;
        }
        unset($this->order, $this->total);

        $input = $this->paymentMethod === 'gateway' && $this->gatewayId ? ['gateway_id' => $this->gatewayId] : [];

        return $this->handlePaymentResult($manager->method($this->paymentMethod)->start($order, $input));
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
            'bankCardId'       => ['required', 'integer'],
            'paymentReference' => ['required', 'string', 'max:40'],
            'payerName'        => ['nullable', 'string', 'max:190'],
            'payerCard'        => ['nullable', 'string', 'max:20'],
        ], [
            'bankCardId.required'       => 'کارت مقصد را انتخاب کنید.',
            'paymentReference.required' => 'لطفاً شناسه پرداخت یا کد پیگیری را وارد کنید.',
            'paymentReference.max'      => 'شناسه پرداخت نمی‌تواند بیشتر از ۴۰ کاراکتر باشد.',
            'payerName.max'             => 'نام صاحب کارت بیش از حد طولانی است.',
            'payerCard.max'             => 'چهار رقم آخر کارت را وارد کنید.',
        ]);

        if (!$this->selectedAddressId) {
            $this->errorMessage = 'لطفاً یک آدرس تحویل انتخاب کنید.';
            $this->showModal = false;
            return;
        }

        $manager = app(\App\Payments\PaymentManager::class);

        if (! $manager->isMethodUsable('transfer', $this->order, Auth::user())) {
            $this->addError('paymentReference', 'پرداخت کارت به کارت در حال حاضر فعال نیست.');
            return;
        }

        $delivery = $this->validatedDelivery();
        if (! $delivery) {
            $this->showModal = false;
            return;
        }

        $order = $this->saveShippingDetails($delivery);
        if (! $order) {
            $this->showModal = false;
            return;
        }
        unset($this->order, $this->total);

        $result = $manager->method('transfer')->start($order, [
            'bank_card_id' => $this->bankCardId,
            'reference'    => $this->paymentReference,
            'payer_name'   => $this->payerName,
            'payer_card'   => $this->payerCard,
        ]);

        if ($result->isFailed()) {
            $this->addError('paymentReference', $result->message);
            return;
        }

        return $this->handlePaymentResult($result);
    }

};
?>

<div>
    <main class="space-y-12">

        <!-- CONTENT -->
        <section class="relative py-16 transition-colors duration-700">

            {{-- استپ‌لاین بالای صفحه بدون تغییر باقی می‌ماند --}}

            <div class="" dir="rtl">

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
                                        class="px-4 py-2 bg-brown-600/10 text-brown-600 rounded-xl text-xs font-black hover:bg-brown-600 hover:text-white transition-all">
                                    تغییر یا ویرایش
                                </button>
                            </div>

                            @php $activeAddress = $addresses->firstWhere('id', $selectedAddressId); @endphp

                            @if ($activeAddress)
                                <div class="p-6 bg-brown-500/5 rounded-3xl border border-brown-500/10">
                                    @if ($activeAddress->title)
                                        <span class="text-[10px] font-black text-brown-600">{{ $activeAddress->title }}</span>
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
                                             {{ $addr->id === $selectedAddressId ? 'border-brown-500 bg-white/60 shadow-sm' : 'border-transparent bg-white/30' }}">
                                            <div class="flex justify-between items-center">
                                                <span class="text-[10px] font-black text-brown-600">{{ $addr->is_default ? 'آدرس اصلی' : ($addr->title ?? 'آدرس') }}</span>
                                                <div class="status-dot w-4 h-4 rounded-full {{ $addr->id === $selectedAddressId ? 'bg-brown-500 border-2 border-brown-500' : 'bg-transparent border-2 border-gray-300' }}"></div>
                                            </div>
                                            <p class="text-xs text-gray-700 dark:text-gray-300 mt-2 font-bold leading-relaxed">
                                                {{ $addr->province }}، {{ $addr->city }}، {{ $addr->address }}
                                            </p>
                                        </div>
                                    @endforeach

                                    <button wire:click="openAddForm" type="button"
                                            class="w-full py-4 border-2 border-dashed border-gray-300 dark:border-white/10 rounded-2xl text-gray-400 text-xs font-black hover:bg-brown-50/50 hover:border-brown-500/50 transition-all">
                                        + افزودن آدرس جدید
                                    </button>
                                </div>
                            @endif

                            @if ($showAddForm)
                                <div class="mt-6 space-y-4">
                                    <div>
                                        <input type="text" wire:model="newTitle" placeholder="عنوان آدرس (مثلاً: خانه، محل کار) — اختیاری"
                                               class="w-full bg-white/50 dark:bg-white/5 border border-white/60 dark:border-white/10 rounded-2xl px-4 py-3 text-sm outline-none focus:border-brown-500 font-bold">
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <input type="text" wire:model="newName" placeholder="نام و نام خانوادگی گیرنده"
                                                   class="w-full bg-white/50 dark:bg-white/5 border border-white/60 dark:border-white/10 rounded-2xl px-4 py-3 text-sm outline-none focus:border-brown-500 font-bold">
                                            @error('newName') <span class="text-[10px] text-rose-500 font-bold">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <input type="tel" wire:model="newPhone" placeholder="شماره تماس (مثلاً ۰۹۱۲...)" dir="ltr"
                                                   class="w-full bg-white/50 dark:bg-white/5 border border-white/60 dark:border-white/10 rounded-2xl px-4 py-3 text-sm outline-none focus:border-brown-500 text-left font-bold">
                                            @error('newPhone') <span class="text-[10px] text-rose-500 font-bold">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div>
                                            <input type="text" wire:model="newProvince" placeholder="استان"
                                                   class="w-full bg-white/50 dark:bg-white/5 border border-white/60 dark:border-white/10 rounded-2xl px-4 py-3 text-sm outline-none focus:border-brown-500 font-bold">
                                            @error('newProvince') <span class="text-[10px] text-rose-500 font-bold">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <input type="text" wire:model="newCity" placeholder="شهر"
                                                   class="w-full bg-white/50 dark:bg-white/5 border border-white/60 dark:border-white/10 rounded-2xl px-4 py-3 text-sm outline-none focus:border-brown-500 font-bold">
                                            @error('newCity') <span class="text-[10px] text-rose-500 font-bold">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <input type="text" wire:model="newPostalCode" placeholder="کد پستی (اختیاری)" dir="ltr"
                                                   class="w-full bg-white/50 dark:bg-white/5 border border-white/60 dark:border-white/10 rounded-2xl px-4 py-3 text-sm outline-none focus:border-brown-500 text-left font-bold">
                                            @error('newPostalCode') <span class="text-[10px] text-rose-500 font-bold">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div>
                                        <textarea wire:model="newAddress" placeholder="نشانی دقیق پستی (خیابان، کوچه، پلاک، واحد...)" rows="3"
                                                  class="w-full bg-white/50 dark:bg-white/5 border border-white/60 dark:border-white/10 rounded-2xl px-4 py-3 text-sm outline-none focus:border-brown-500 resize-none font-bold"></textarea>
                                        @error('newAddress') <span class="text-[10px] text-rose-500 font-bold">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="flex gap-3 pt-2">
                                        <button wire:click="saveAddress" type="button"
                                                class="flex-1 bg-brown-600 text-white py-3.5 rounded-2xl font-black text-sm shadow-lg shadow-brown-500/30">
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

                            @if (empty($deliveryDates))
                                <div class="p-5 rounded-[2rem] bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400 text-sm font-bold">
                                    در حال حاضر تاریخی برای ارسال در دسترس نیست. لطفاً بعداً تلاش کنید یا با پشتیبانی تماس بگیرید.
                                </div>
                            @else
                                <div class="flex items-center justify-between gap-3 mb-4">
                                    <span class="text-xs font-black text-gray-500 dark:text-gray-400">روز تحویل را انتخاب کنید</span>
                                    @if ($this->selectedDelivery)
                                        <span class="text-xs font-black text-brown-600">
                                            {{ $this->selectedDelivery['weekday'] }} {{ $this->selectedDelivery['day'] }} {{ $this->selectedDelivery['month'] }}
                                        </span>
                                    @endif
                                </div>

                                {{-- موبایل: اسکرول افقی با snap | دسکتاپ: گرید --}}
                                <div class="flex gap-3 overflow-x-auto snap-x snap-mandatory pb-3 -mx-2 px-2 sm:mx-0 sm:px-0 sm:grid sm:grid-cols-4 lg:grid-cols-7 sm:overflow-visible" role="radiogroup" aria-label="تاریخ ارسال">
                                    @foreach ($deliveryDates as $option)
                                        @php
                                            $isSelected = $option['date'] === $deliveryDate;
                                        @endphp
                                        <button wire:click="selectDeliveryDate('{{ $option['date'] }}')"
                                                wire:key="delivery-{{ $option['date'] }}"
                                                type="button"
                                                role="radio"
                                                aria-checked="{{ $isSelected ? 'true' : 'false' }}"
                                                class="relative shrink-0 snap-start w-[5.5rem] sm:w-auto min-h-28 px-2 py-4 rounded-[1.75rem] border-2 text-center transition-all duration-300
                                                {{ $isSelected ? 'border-brown-600 bg-white/90 dark:bg-brown-600/15 shadow-lg shadow-brown-600/10' : 'border-white/60 dark:border-white/5 bg-white/30 dark:bg-white/[0.02] hover:border-brown-600/40' }}">

                                            @if ($isSelected)
                                                <span class="absolute top-2 left-2 w-5 h-5 bg-brown-600 rounded-full flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                </span>
                                            @endif

                                            <span class="block text-[10px] font-black mb-1 {{ $option['label'] ? 'text-brown-600' : 'text-gray-400' }}">
                                                {{ $option['label'] ?? $option['weekday'] }}
                                            </span>
                                            <span class="block text-2xl font-black text-gray-900 dark:text-white leading-none tabular-nums">{{ $option['day'] }}</span>
                                            <span class="block text-[11px] font-bold text-gray-500 dark:text-gray-400 mt-1">{{ $option['month'] }}</span>
                                            @if ($option['label'])
                                                <span class="block text-[10px] font-bold text-gray-400 mt-0.5">{{ $option['weekday'] }}</span>
                                            @endif

                                            <span class="mt-3 inline-block py-1 px-2 rounded-lg text-[9px] font-black {{ $option['cost'] > 0 ? 'bg-gray-50 dark:bg-white/5 text-gray-500' : 'bg-emerald-500/10 text-emerald-600' }}">
                                                {{ $option['cost'] > 0 ? number_format($option['cost']) . ' ت' : 'رایگان' }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
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

                                @forelse ($this->paymentOptions as $opt)
                                    @php
                                        $key = $opt['key'];
                                    @endphp
                                    <label wire:click="selectPayment('{{ $key }}')" wire:key="pay-{{ $key }}"
                                           @if($opt['disabled']) title="{{ $opt['disabled'] }}" @endif
                                           class="payment-card relative flex items-center p-6 border-2 rounded-[2rem] transition-all
                                           {{ $opt['disabled'] ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}
                                           {{ $paymentMethod === $key ? 'border-brown-600 bg-white shadow-sm' : 'border-gray-100 dark:border-white/5 bg-white/50 dark:bg-white/[0.02]' }}">
                                        <input type="radio" name="payment" value="{{ $key }}" @checked($paymentMethod === $key) class="sr-only">
                                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center ml-4 transition-all {{ $paymentMethod === $key ? 'bg-brown-600 text-white shadow-lg shadow-brown-500/20' : 'bg-gray-100 dark:bg-white/5 text-gray-400' }}">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $opt['icon'] }}"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <span class="block text-sm font-black text-gray-900 dark:text-white">{{ $opt['title'] }}</span>
                                            <span class="text-[10px] {{ $opt['disabled'] ? 'text-rose-500 font-black' : ($key === 'wallet' ? 'text-brown-500 font-black' : 'text-gray-400 font-bold') }}">{{ $opt['disabled'] ?? ($opt['hint'] ?? $opt['description']) }}</span>
                                        </div>
                                        <div class="w-6 h-6 border-2 rounded-full flex items-center justify-center {{ $paymentMethod === $key ? 'border-brown-600 bg-brown-600' : 'border-gray-200 dark:border-white/10' }}">
                                            @if ($paymentMethod === $key)
                                                <div class="w-2 h-2 bg-white rounded-full"></div>
                                            @endif
                                        </div>
                                    </label>

                                    {{-- انتخاب درگاه (فقط وقتی بیش از یک درگاه فعال است) --}}
                                    @if ($key === 'gateway' && $paymentMethod === 'gateway' && $this->gatewayOptions->count() > 1)
                                        <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-3" wire:key="gateways-list">
                                            @foreach ($this->gatewayOptions as $gw)
                                                <button type="button" wire:key="gw-{{ $gw['id'] }}" wire:click="selectGateway({{ $gw['id'] }})"
                                                        @disabled($gw['disabled']) @if($gw['disabled']) title="{{ $gw['disabled'] }}" @endif
                                                        class="flex items-center justify-between gap-3 px-5 py-4 rounded-2xl border-2 text-right transition-all
                                                        {{ $gw['disabled'] ? 'opacity-50 cursor-not-allowed border-gray-100 dark:border-white/5' : 'cursor-pointer' }}
                                                        {{ $gatewayId === $gw['id'] ? 'border-brown-600 bg-white dark:bg-white/5 shadow-sm' : 'border-gray-100 dark:border-white/5 bg-white/50 dark:bg-white/[0.02]' }}">
                                                    <span>
                                                        <span class="block text-xs font-black text-gray-900 dark:text-white">{{ $gw['title'] }}</span>
                                                        @if ($gw['disabled'])
                                                            <span class="block text-[10px] font-bold text-rose-500 mt-1">{{ $gw['disabled'] }}</span>
                                                        @endif
                                                    </span>
                                                    <span class="w-5 h-5 shrink-0 border-2 rounded-full flex items-center justify-center {{ $gatewayId === $gw['id'] ? 'border-brown-600 bg-brown-600' : 'border-gray-200 dark:border-white/10' }}">
                                                        @if ($gatewayId === $gw['id'])
                                                            <span class="w-1.5 h-1.5 bg-white rounded-full"></span>
                                                        @endif
                                                    </span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                @empty
                                    <div class="md:col-span-2 p-5 rounded-[2rem] bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400 text-sm font-bold">
                                        در حال حاضر روش پرداخت فعالی وجود ندارد.
                                    </div>
                                @endforelse
                            </div>
                        </div>

                    </div>

                    <!-- خلاصه فاکتور -->
                    <div class="lg:col-span-3">
                        <div class="sticky top-8 group">
                            <div class="absolute -top-10 -left-10 w-40 h-40 bg-brown-500/10 rounded-full blur-[80px]"></div>

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
                                        <span class="text-[10px] font-black text-brown-600 dark:text-brown-400 uppercase tracking-[5px]">Net Payable</span>
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
                                            class="group/pay relative w-full h-20 bg-brown-600 dark:bg-brown-500 rounded-[2.2rem] overflow-hidden transition-all duration-500 shadow-[0_20px_40px_-10px_rgba(101,67,33,0.5)]
                                                    hover:shadow-[0_25px_50px_-12px_rgba(101,67,33,0.7)] hover:-translate-y-1 active:scale-95">

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
                                        با ثبت سفارش، قوانین و مقررات {{ \App\Models\Setting::option('site_name', config('app.name')) }} را می‌پذیرم.
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
                   bg-brown-500/10 blur-3xl
                   pointer-events-none"
                ></div>

                {{-- Header --}}
                <div class="relative flex items-center justify-between px-6 pt-6 pb-5">

                    <div class="flex items-center gap-3">

                        <div
                            class="w-11 h-11 rounded-2xl
                           bg-brown-500/10
                           flex items-center justify-center"
                        >
                            <svg
                                class="w-5 h-5 text-brown-500"
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
                                پرداخت کارت به کارت
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
                       bg-brown-500/5
                       border border-brown-500/10
                       mb-5"
                    >
                        <div class="flex items-center justify-between">

                    <span class="text-[10px] font-bold text-gray-400">
                        مبلغ قابل پرداخت
                    </span>

                            <div class="flex items-baseline gap-1">
                        <span class="text-xl font-black text-brown-500">
                           {{ number_format($this->total) }}
                        </span>

                                <span class="text-[9px] font-bold text-gray-400">
                            تومان
                        </span>
                            </div>

                        </div>
                    </div>


                    {{-- Bank cards (از پنل > تنظیمات پرداخت > کارت‌های بانکی) --}}
                    <div class="mb-5">
                        <label class="block mb-2 text-[11px] font-black text-gray-700 dark:text-gray-300">
                            شماره کارت جهت واریز
                        </label>

                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            @forelse($this->bankCards as $card)
                                <label wire:key="bank-card-{{ $card->id }}"
                                       class="relative flex items-center h-16 px-4 rounded-2xl border cursor-pointer transition
                                       {{ (int) $bankCardId === $card->id ? 'border-brown-500 bg-brown-500/5' : 'bg-gray-50 dark:bg-white/[0.04] border-gray-200 dark:border-white/10' }}">
                                    <input type="radio" class="sr-only" wire:model.live="bankCardId" value="{{ $card->id }}">
                                    <div class="w-10 h-10 rounded-xl bg-brown-500/10 flex items-center justify-center text-brown-500 ml-3 shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="block text-base font-black text-gray-900 dark:text-white tracking-[2px]" dir="ltr">{{ $card->formatted_number }}</span>
                                        <span class="block mt-1 text-[9px] font-bold text-gray-400 truncate">
                                            {{ $card->bank_name }} — به نام: {{ $card->owner_name }}
                                        </span>
                                    </div>
                                    <button type="button"
                                            x-data
                                            x-on:click.stop.prevent="navigator.clipboard.writeText('{{ $card->card_number }}'); $el.innerText = 'کپی شد'"
                                            class="mr-2 shrink-0 text-[9px] font-black text-brown-500 hover:text-brown-600 transition">
                                        کپی
                                    </button>
                                </label>
                            @empty
                                <p class="text-[10px] font-bold text-rose-500">کارتی برای واریز ثبت نشده است.</p>
                            @endforelse
                        </div>
                        @error('bankCardId')
                        <p class="mt-2 text-[10px] font-bold text-red-500">{{ $message }}</p>
                        @enderror
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
                           focus:border-brown-500
                           focus:ring-4 focus:ring-brown-500/10
                           outline-none transition"
                            dir="ltr"
                        >

                        @error('paymentReference')
                        <p class="mt-2 text-[10px] font-bold text-red-500">
                            {{ $message }}
                        </p>
                        @enderror

                        <div class="grid grid-cols-2 gap-3 mt-4">
                            <div>
                                <label class="block mb-2 text-[11px] font-black text-gray-700 dark:text-gray-300">نام صاحب کارت (اختیاری)</label>
                                <input type="text" wire:model="payerName" autocomplete="off"
                                       class="w-full h-12 rounded-2xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/[0.04] px-4 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-brown-500 focus:ring-4 focus:ring-brown-500/10 transition">
                            </div>
                            <div>
                                <label class="block mb-2 text-[11px] font-black text-gray-700 dark:text-gray-300">۴ رقم آخر کارت (اختیاری)</label>
                                <input type="text" inputmode="numeric" maxlength="4" wire:model="payerCard" autocomplete="off" dir="ltr"
                                       class="w-full h-12 rounded-2xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/[0.04] px-4 text-xs font-black text-gray-900 dark:text-white outline-none focus:border-brown-500 focus:ring-4 focus:ring-brown-500/10 transition">
                            </div>
                        </div>

                    </div>


                    {{-- Submit --}}
                    <button
                        wire:click="submitCardToCardPayment"
                        wire:loading.attr="disabled"
                        wire:target="submitCardToCardPayment"
                        type="button"
                        class="mt-5 w-full h-14
                       rounded-2xl
                       bg-brown-500
                       text-white
                       text-[11px]
                       font-black
                       shadow-lg shadow-brown-500/25
                       hover:bg-brown-600
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
                        سفارش شما تأیید و آماده ارسال می‌شود.
                    </p>

                </div>

            </div>

        </div>

    @endif

</div>
