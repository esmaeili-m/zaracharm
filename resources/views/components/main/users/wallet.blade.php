<?php

use Livewire\Component;
use App\Models\RewardLevel;
use App\Models\RewardPointTransaction;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
    public bool $showWalletModal = false;

    public $walletAmount = null;
    public $user = null;
    public int $rewardPoints = 0;

    public ?RewardLevel $rewardLevel = null;

    public ?RewardLevel $nextRewardLevel = null;

    public int $pointsToNextLevel = 0;

    public int $rewardProgress = 100;
    private function loadRewardData(): void
    {
        $user = auth()->user();

        /*
        |--------------------------------------------------------------------------
        | امتیاز فعلی کاربر
        |--------------------------------------------------------------------------
        */

        $this->rewardPoints = (int) $user
            ->rewardPointTransactions()
            ->sum('amount');


        /*
        |--------------------------------------------------------------------------
        | سطح فعلی
        |--------------------------------------------------------------------------
        */

        $this->rewardLevel = RewardLevel::query()
            ->where('is_active', true)
            ->where('min_points', '<=', $this->rewardPoints)
            ->orderByDesc('min_points')
            ->first();


        /*
        |--------------------------------------------------------------------------
        | سطح بعدی
        |--------------------------------------------------------------------------
        */

        $this->nextRewardLevel = RewardLevel::query()
            ->where('is_active', true)
            ->where('min_points', '>', $this->rewardPoints)
            ->orderBy('min_points')
            ->first();


        /*
        |--------------------------------------------------------------------------
        | محاسبه پیشرفت
        |--------------------------------------------------------------------------
        */

        if ($this->nextRewardLevel) {

            $currentLevelMin =
                $this->rewardLevel?->min_points ?? 0;

            $nextLevelMin =
                $this->nextRewardLevel->min_points;

            $levelRange =
                $nextLevelMin - $currentLevelMin;

            $currentProgress =
                $this->rewardPoints - $currentLevelMin;


            $this->pointsToNextLevel =
                max(0, $nextLevelMin - $this->rewardPoints);


            $this->rewardProgress = $levelRange > 0
                ? min(
                    100,
                    (int) round(
                        ($currentProgress / $levelRange) * 100
                    )
                )
                : 100;

        } else {

            /*
            |--------------------------------------------------------------------------
            | کاربر به بالاترین سطح رسیده
            |--------------------------------------------------------------------------
            */

            $this->pointsToNextLevel = 0;

            $this->rewardProgress = 100;
        }
    }
    public function mount($user)
    {
        $this->user=$user;
        $this->loadRewardData();

    }
    public bool $showWithdrawModal = false;

    public $withdrawAmount = null;

    public $withdrawableBalance = 0;

    public $withdrawalAccount = null;


    public function openWithdrawModal(): void
    {
        abort_unless(auth()->check(), 403);

        $this->resetValidation();

        $this->withdrawAmount = null;

        // این مقدار را از دیتابیس محاسبه کن
        $this->withdrawableBalance = $this->getWithdrawableBalance();

        // حساب بانکی تأییدشده کاربر
        $this->withdrawalAccount = auth()->user()
            ->withdrawalAccounts()
            ->where('is_verified', true)
            ->first();

        $this->showWithdrawModal = true;

    }


    public function closeWithdrawModal(): void
    {
        $this->resetValidation();

        $this->withdrawAmount = null;

        $this->showWithdrawModal = false;
    }


    public function requestWithdrawal(): void
    {
        abort_unless(auth()->check(), 403);

        $this->validate([
            'withdrawAmount' => [
                'required',
                'integer',
                'min:50000',
            ],
        ], [
            'withdrawAmount.required' => 'مبلغ تسویه را وارد کنید.',
            'withdrawAmount.integer' => 'مبلغ باید عددی باشد.',
            'withdrawAmount.min' => 'حداقل مبلغ تسویه ۵۰,۰۰۰ تومان است.',
        ]);


        // بسیار مهم:
        // موجودی را از مقدار ارسال‌شده توسط کاربر محاسبه نکن.
        // دوباره موجودی واقعی را از DB بخوان.

        $user = auth()->user();

        $balance = $this->getWithdrawableBalance();

        $amount = (int) $this->withdrawAmount;


        if ($amount > $balance) {
            $this->addError(
                'withdrawAmount',
                'مبلغ تسویه بیشتر از موجودی قابل برداشت است.'
            );

            return;
        }


        $account = $user->withdrawalAccounts()
            ->where('is_verified', true)
            ->first();


        if (!$account) {
            $this->addError(
                'withdrawalAccount',
                'حساب بانکی تأییدشده‌ای برای تسویه وجود ندارد.'
            );

            return;
        }


        /*
         |--------------------------------------------------------------------------
         | اینجا باید تراکنش تسویه را ایجاد کنی
         |--------------------------------------------------------------------------
         |
         | وضعیت اولیه:
         |
         | pending
         |
         | سپس سیستم مالی/ادمین آن را بررسی و پرداخت می‌کند.
         |
         */


        // Withdrawal::create([
        //     'user_id' => $user->id,
        //     'withdrawal_account_id' => $account->id,
        //     'amount' => $amount,
        //     'status' => 'pending',
        //     'reference' => Str::uuid(),
        // ]);


        $this->closeWithdrawModal();

        // notification / toast
    }


    private function getWithdrawableBalance(): int
    {
        // این متد باید موجودی واقعی و قابل برداشت را
        // از سیستم مالی شما محاسبه کند.

        return (int) auth()->user()->wallet_balance;
    }
    public function openWalletModal(): void
    {
        abort_unless(auth()->check(), 403);

        $this->resetValidation();

        $this->walletAmount = null;

        $this->showWalletModal = true;
    }


    public function closeWalletModal(): void
    {
        $this->resetValidation();

        $this->walletAmount = null;

        $this->showWalletModal = false;
    }
    public function getTransactionsProperty()
    {
        return auth()->user()
            ->transactions()
            ->latest()
            ->paginate(10);
    }

    public function startWalletPayment()
    {
        abort_unless(auth()->check(), 403);

        $this->validate([
            'walletAmount' => [
                'required',
                'integer',
                'min:10000',
                'max:50000000',
            ],
        ], [
            'walletAmount.required' => 'مبلغ را وارد کنید.',
            'walletAmount.integer' => 'مبلغ باید عددی باشد.',
            'walletAmount.min' => 'حداقل مبلغ افزایش موجودی ۱۰,۰۰۰ تومان است.',
            'walletAmount.max' => 'حداکثر مبلغ افزایش موجودی ۵۰,۰۰۰,۰۰۰ تومان است.',
        ]);

        $amount = (int) $this->walletAmount;

        // اینجا هنوز موجودی را افزایش نده!
        //
        // 1. یک payment/transaction با وضعیت pending ایجاد کن
        // 2. user_id را از auth()->id() بگیر، نه از input
        // 3. مبلغ را از همین مقدار validate‌شده بگیر
        // 4. یک شناسه یکتا برای تراکنش بساز
        // 5. کاربر را به درگاه پرداخت بفرست
    }
};
?>

<div>
    <main class="flex-1 space-y-6 relative z-10">

        <div class="space-y-8" dir="rtl">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <div class="lg:col-span-2 relative overflow-hidden bg-gradient-to-br from-primary-600 via-primary-500 to-primary-700 rounded-[2.5rem] p-8 shadow-[0_20px_50px_rgba(59,130,246,0.3)] group">
                    <div class="absolute top-0 left-0 w-full h-full opacity-10 pointer-events-none">
                        <svg viewBox="0 0 100 100" class="w-full h-full"><circle cx="10" cy="10" r="30" fill="white"></circle><circle cx="90" cy="90" r="40" fill="white"></circle></svg>
                    </div>

                    <div class="relative z-10 flex flex-col h-full justify-between min-h-[200px]">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="text-white/70 text-[11px] font-black uppercase tracking-[0.2em]">موجودی کل حساب</span>
                                <div class="flex items-baseline gap-3 mt-2">
                                    <span class="text-4xl font-black text-white tabular-nums tracking-tight">{{number_format($user->wallet->balance ?? 0)}}</span>
                                    <span class="text-sm font-bold text-white/80">تومان</span>
                                </div>
                            </div>
                            <div class="w-14 h-14 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/30">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 mt-8">
                            <button     wire:click="openWalletModal"
                                        class="flex-1 bg-white text-primary-600 py-4 rounded-2xl text-[12px] font-black shadow-lg hover:bg-gray-50 transition-all active:scale-95">
                                + افزایش موجودی
                            </button>
                            <button wire:click="openWithdrawModal" class="flex-1 bg-white/20 backdrop-blur-md text-white py-4 rounded-2xl text-[12px] font-black border border-white/30 hover:bg-white/30 transition-all active:scale-95">
                                درخواست تسویه
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    class="relative overflow-hidden
           bg-white/40 dark:bg-gray-950/60
           backdrop-blur-md
           border border-white/60 dark:border-white/10
           rounded-[2.5rem]
           p-8
           flex flex-col justify-center
           group"
                >

                    {{-- Glow --}}
                    <div
                        class="absolute -bottom-10 -right-10
               w-32 h-32
               bg-amber-500/10
               rounded-full
               blur-3xl
               transition-all
               duration-500
               group-hover:bg-amber-500/20"
                    ></div>


                    <div class="relative z-10">

                        {{-- Header --}}
                        <div class="flex items-center justify-between gap-3">

            <span
                class="text-gray-400
                       text-[11px]
                       font-black
                       uppercase
                       tracking-widest"
            >
                امتیاز مانا کلاب
            </span>


                            @if($rewardLevel)

                                <span
                                    class="px-3 py-1.5
                           rounded-xl
                           bg-amber-500/10
                           text-amber-500
                           text-[9px]
                           font-black"
                                >
                    {{ $rewardLevel->name }}
                </span>

                            @endif

                        </div>


                        {{-- Points --}}
                        <div class="flex items-center gap-3 mt-4">

                            <div
                                class="w-12 h-12
                       rounded-2xl
                       bg-amber-500/10
                       flex items-center justify-center
                       text-amber-500
                       shrink-0"
                            >

                                <svg
                                    class="w-6 h-6"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                    />
                                </svg>

                            </div>


                            <div>

                                <div class="flex items-baseline gap-2">

                    <span
                        class="text-3xl
                               font-black
                               text-gray-800
                               dark:text-white
                               tabular-nums"
                    >
                        {{ number_format($rewardPoints) }}
                    </span>

                                    <span
                                        class="text-[10px]
                               font-bold
                               text-gray-400"
                                    >
                        امتیاز
                    </span>

                                </div>


                                @if($rewardLevel)

                                    <span
                                        class="text-[9px]
                               font-bold
                               text-amber-500"
                                    >
                        سطح {{ $rewardLevel->name }}
                    </span>

                                @endif

                            </div>

                        </div>


                        {{-- Level Progress --}}
                        <div class="mt-5">

                            @if($nextRewardLevel)

                                <div class="flex items-center justify-between mb-2">

                    <span
                        class="text-[9px]
                               font-bold
                               text-gray-400"
                    >
                        سطح بعدی:
                        <span class="text-gray-600 dark:text-gray-300">
                            {{ $nextRewardLevel->name }}
                        </span>
                    </span>


                                    <span
                                        class="text-[9px]
                               font-black
                               text-gray-500
                               dark:text-gray-400"
                                    >
                        {{ number_format($pointsToNextLevel) }}
                        امتیاز باقی مانده
                    </span>

                                </div>


                                <div
                                    class="h-2
                           rounded-full
                           bg-gray-100
                           dark:bg-white/5
                           overflow-hidden"
                                >

                                    <div
                                        class="h-full
                               rounded-full
                               bg-amber-500
                               transition-all
                               duration-700"
                                        style="width: {{ $rewardProgress }}%"
                                    ></div>

                                </div>

                            @else

                                <div
                                    class="p-3
                           rounded-2xl
                           bg-amber-500/10
                           border border-amber-500/10"
                                >

                                    <p
                                        class="text-[10px]
                               font-black
                               text-amber-500"
                                    >
                                        🎉 شما به بالاترین سطح مانا کلاب رسیده‌اید.
                                    </p>

                                </div>

                            @endif

                        </div>


                        {{-- Description --}}
                        @if($rewardLevel?->description)

                            <p
                                class="text-[10px]
                       font-bold
                       text-gray-400
                       mt-4
                       leading-6"
                            >
                                {{ $rewardLevel->description }}
                            </p>

                        @endif

                    </div>

                </div>

            </div>

            <div
                class="relative overflow-hidden
           bg-white/40 dark:bg-gray-950/60
           backdrop-blur-md
           border border-white/60 dark:border-white/10
           rounded-[2.5rem]
           shadow-[0_20px_50px_rgba(0,0,0,0.05)]"
            >

                {{-- Header --}}
                <div
                    class="p-8
               border-b border-gray-100 dark:border-white/5
               flex items-center justify-between"
                >

                    <h3
                        class="text-lg font-black
                   text-gray-900 dark:text-white
                   flex items-center gap-3"
                    >

            <span
                class="w-2 h-6
                       bg-primary-500
                       rounded-full
                       shadow-[0_0_15px_rgba(59,130,246,0.5)]"
            ></span>

                        تاریخچه تراکنش‌ها

                    </h3>

                    <div class="flex gap-2">

                        <button
                            type="button"
                            class="p-2.5
                       rounded-xl
                       bg-gray-50 dark:bg-white/5
                       text-gray-400
                       hover:text-primary-500
                       transition-all"
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
                                    d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707L13 17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
                                />
                            </svg>

                        </button>

                    </div>

                </div>


                {{-- Table --}}
                <div class="overflow-x-auto">

                    <table class="w-full text-right">

                        <thead>

                        <tr
                            class="text-gray-400
                       text-[10px]
                       font-black
                       uppercase
                       tracking-widest
                       border-b border-gray-100
                       dark:border-white/5"
                        >

                            <th class="p-6">
                                نوع تراکنش
                            </th>

                            <th class="p-6">
                                شرح تراکنش
                            </th>

                            <th class="p-6">
                                تاریخ و زمان
                            </th>

                            <th class="p-6 text-left">
                                مبلغ (تومان)
                            </th>

                        </tr>

                        </thead>


                        <tbody
                            class="divide-y
                       divide-gray-100/50
                       dark:divide-white/5"
                        >

                        @forelse($this->transactions as $transaction)

                            @php

                                $transactionData = match ($transaction->category) {

                                    'wallet_deposit' => [
                                        'title' => 'افزایش موجودی',
                                        'color' => 'emerald',
                                        'icon' => 'up',
                                    ],

                                    'order_payment' => [
                                        'title' => 'پرداخت سفارش',
                                        'color' => 'red',
                                        'icon' => 'down',
                                    ],

                                    'refund' => [
                                        'title' => 'استرداد وجه',
                                        'color' => 'blue',
                                        'icon' => 'up',
                                    ],

                                    'withdrawal' => [
                                        'title' => 'برداشت وجه',
                                        'color' => 'orange',
                                        'icon' => 'down',
                                    ],

                                    'adjustment' => [
                                        'title' => 'اصلاح موجودی',
                                        'color' => $transaction->type === 'credit'
                                            ? 'emerald'
                                            : 'red',
                                        'icon' => $transaction->type === 'credit'
                                            ? 'up'
                                            : 'down',
                                    ],

                                    default => [
                                        'title' => 'تراکنش مالی',
                                        'color' => $transaction->type === 'credit'
                                            ? 'emerald'
                                            : 'red',
                                        'icon' => $transaction->type === 'credit'
                                            ? 'up'
                                            : 'down',
                                    ],

                                };

                            @endphp


                            <tr
                                class="group
                           hover:bg-white/50
                           dark:hover:bg-white/[0.02]
                           transition-all"
                            >

                                {{-- Transaction Type --}}
                                <td class="p-6">

                                    <div class="flex items-center gap-3">

                                        <div
                                            @class([
                                                'w-10 h-10 rounded-xl flex items-center justify-center',

                                                'bg-emerald-500/10 text-emerald-500'
                                                    => $transactionData['color'] === 'emerald',

                                                'bg-red-500/10 text-red-500'
                                                    => $transactionData['color'] === 'red',

                                                'bg-blue-500/10 text-blue-500'
                                                    => $transactionData['color'] === 'blue',

                                                'bg-orange-500/10 text-orange-500'
                                                    => $transactionData['color'] === 'orange',
                                            ])
                                        >

                                            @if($transactionData['icon'] === 'up')

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
                                                        d="M7 11l5-5m0 0l5 5m-5-5v12"
                                                    />
                                                </svg>

                                            @else

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
                                                        d="M17 13l-5 5m0 0l-5 5"
                                                    />
                                                </svg>

                                            @endif

                                        </div>


                                        <span
                                            class="text-[12px]
                                       font-black
                                       text-gray-800
                                       dark:text-white"
                                        >
                                {{ $transactionData['title'] }}
                            </span>

                                    </div>

                                </td>


                                {{-- Description --}}
                                <td
                                    class="p-6
                               text-[11px]
                               font-bold
                               text-gray-500
                               dark:text-gray-400"
                                >

                                    {{ $transaction->description ?? 'بدون توضیحات' }}

                                </td>


                                {{-- Date --}}
                                <td
                                    class="p-6
                               text-[11px]
                               font-black
                               text-gray-800
                               dark:text-white
                               tabular-nums
                               whitespace-nowrap"
                                >

                                    {{ verta($transaction->created_at)->format('Y/m/d') }}

                                    <span class="text-gray-400 px-1">
                            -
                        </span>

                                    {{ verta($transaction->created_at)->format('H:i') }}

                                </td>


                                {{-- Amount --}}
                                <td class="p-6 text-left">

                        <span
                            @class([
                                'text-[13px] font-black tabular-nums',

                                'text-emerald-500'
                                    => $transaction->type === 'credit',

                                'text-red-500'
                                    => $transaction->type === 'debit',
                            ])
                        >

                            {{ $transaction->type === 'credit' ? '+' : '-' }}

                            {{ number_format($transaction->amount) }}

                        </span>

                                </td>

                            </tr>


                        @empty

                            {{-- Empty State --}}
                            <tr>

                                <td
                                    colspan="4"
                                    class="p-12"
                                >

                                    <div
                                        class="flex flex-col
                                   items-center
                                   justify-center
                                   text-center"
                                    >

                                        <div
                                            class="w-16 h-16
                                       rounded-2xl
                                       bg-gray-50
                                       dark:bg-white/5
                                       flex items-center
                                       justify-center
                                       text-gray-400"
                                        >

                                            <svg
                                                class="w-7 h-7"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a2 2 0 011.414.586l4.414 4.414A2 2 0 0119 9v10a2 2 0 01-2 2z"
                                                />
                                            </svg>

                                        </div>

                                        <span
                                            class="mt-4
                                       text-sm
                                       font-black
                                       text-gray-500
                                       dark:text-gray-400"
                                        >
                                هنوز تراکنشی ثبت نشده است
                            </span>

                                    </div>

                                </td>

                            </tr>

                        @endforelse

                        </tbody>

                    </table>

                </div>


                {{-- Pagination --}}
                @if($this->transactions->hasPages())

                    <div
                        class="p-6
                   bg-gray-50/50
                   dark:bg-white/[0.02]
                   border-t border-gray-100
                   dark:border-white/5"
                    >

                        {{ $this->transactions->links() }}

                    </div>

                @endif

            </div>
        </div>

    </main>
    {{-- دکمه تسویه حساب --}}


    {{-- Withdraw Modal --}}
    @if($showWithdrawModal)

        <div
            class="fixed inset-0 z-[999]
               flex items-center justify-center
               p-4 sm:p-6"
            wire:key="withdraw-modal"
        >

            {{-- Overlay --}}
            <div
                wire:click="closeWithdrawModal"
                class="absolute inset-0 bg-gray-950/60 backdrop-blur-md"
            ></div>


            {{-- Modal --}}
            <div
                class="relative w-full max-w-md
                   overflow-hidden
                   rounded-[2rem]
                   border border-white/20 dark:border-white/10
                   bg-white/95 dark:bg-gray-950/95
                   shadow-[0_25px_80px_rgba(0,0,0,0.25)]
                   backdrop-blur-xl"
            >

                {{-- Decorative --}}
                <div
                    class="absolute -top-24 -left-24
                       w-48 h-48 rounded-full
                       bg-primary-500/10 blur-3xl
                       pointer-events-none"
                ></div>


                {{-- Header --}}
                <div class="relative flex items-center justify-between
                        px-6 pt-6 pb-5">

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
                                    d="M12 8c-2.21 0-4 1.343-4 3s1.79 3 4 3 4 1.343 4 3-1.79 3-4 3m0-14V4m0 16v-2m0-14a8 8 0 100 16 8 8 0 000-16z"
                                />
                            </svg>
                        </div>

                        <div>

                            <h3 class="text-sm font-black
                                   text-gray-900 dark:text-white">
                                تسویه حساب
                            </h3>

                            <p class="mt-1 text-[10px] font-bold text-gray-400">
                                برداشت موجودی کیف پول
                            </p>

                        </div>

                    </div>


                    <button
                        wire:click="closeWithdrawModal"
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

                    {{-- Available Balance --}}
                    <div
                        class="p-4 rounded-2xl
                           bg-primary-500/[0.06]
                           border border-primary-500/10"
                    >

                        <div class="flex items-center justify-between">

                            <div>

                            <span
                                class="block text-[10px] font-bold
                                       text-gray-400"
                            >
                                موجودی قابل تسویه
                            </span>

                                <span
                                    class="block mt-1 text-lg font-black
                                       text-gray-900 dark:text-white"
                                >
                                {{ number_format($withdrawableBalance ?? 0) }}

                                <span class="text-[9px] text-gray-400">
                                    تومان
                                </span>
                            </span>

                            </div>

                            <div
                                class="w-10 h-10 rounded-xl
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
                                        d="M12 8v8m-4-4h8"
                                    />
                                </svg>
                            </div>

                        </div>

                    </div>


                    {{-- Amount --}}
                    <div class="mt-5">

                        <div class="flex items-center justify-between mb-2">

                            <label
                                for="withdrawAmount"
                                class="text-[11px] font-black
                                   text-gray-700 dark:text-gray-300"
                            >
                                مبلغ تسویه
                            </label>

                            <button
                                type="button"
                                wire:click="$set('withdrawAmount', {{ $withdrawableBalance ?? 0 }})"
                                class="text-[10px] font-black
                                   text-primary-500
                                   hover:text-primary-600"
                            >
                                کل موجودی
                            </button>

                        </div>


                        <div class="relative">

                            <input
                                id="withdrawAmount"
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                wire:model.live="withdrawAmount"
                                placeholder="مثلاً ۱,۰۰۰,۰۰۰"
                                class="w-full h-14 rounded-2xl
                                   border border-gray-200
                                   dark:border-white/10
                                   bg-gray-50 dark:bg-white/[0.04]
                                   px-4 pl-20
                                   text-sm font-black
                                   text-gray-900 dark:text-white
                                   placeholder:text-gray-300
                                   dark:placeholder:text-gray-600
                                   focus:border-primary-500
                                   focus:ring-4
                                   focus:ring-primary-500/10
                                   outline-none transition"
                            >

                            <span
                                class="absolute left-4 top-1/2
                                   -translate-y-1/2
                                   text-[10px] font-black
                                   text-gray-400"
                            >
                            تومان
                        </span>

                        </div>


                        @error('withdrawAmount')
                        <p class="mt-2 text-[10px] font-bold text-red-500">
                            {{ $message }}
                        </p>
                        @enderror

                    </div>


                    {{-- Destination --}}
                    <div class="mt-5">

                        <label
                            class="block mb-2 text-[11px] font-black
                               text-gray-700 dark:text-gray-300"
                        >
                            حساب مقصد
                        </label>

                        <div
                            class="p-4 rounded-2xl
                               border border-gray-200
                               dark:border-white/10
                               bg-gray-50 dark:bg-white/[0.04]"
                        >

                            @if($withdrawalAccount)

                                <div class="flex items-center justify-between">

                                    <div>

                                    <span
                                        class="block text-[10px] font-bold
                                               text-gray-400"
                                    >
                                        شماره شبا
                                    </span>

                                        <span
                                            class="block mt-1
                                               text-[11px] font-black
                                               text-gray-800
                                               dark:text-gray-200
                                               tracking-wide"
                                            dir="ltr"
                                        >
                                        {{ $withdrawalAccount->iban }}
                                    </span>

                                    </div>

                                    <span
                                        class="px-2.5 py-1 rounded-lg
                                           bg-emerald-500/10
                                           text-emerald-500
                                           text-[9px] font-black"
                                    >
                                    تأیید شده
                                </span>

                                </div>

                            @else

                                <div class="flex items-center gap-3">

                                    <div
                                        class="w-9 h-9 rounded-xl
                                           bg-amber-500/10
                                           flex items-center justify-center"
                                    >
                                        <span class="text-amber-500">!</span>
                                    </div>

                                    <div>
                                        <p class="text-[10px] font-black
                                              text-gray-700
                                              dark:text-gray-300">
                                            حساب مقصد ثبت نشده است
                                        </p>

                                        <p class="mt-1 text-[9px] font-bold
                                              text-gray-400">
                                            ابتدا یک حساب بانکی تأیید شده ثبت کنید.
                                        </p>
                                    </div>

                                </div>

                            @endif

                        </div>

                        @error('withdrawalAccount')
                        <p class="mt-2 text-[10px] font-bold text-red-500">
                            {{ $message }}
                        </p>
                        @enderror

                    </div>


                    {{-- Security --}}
                    <div
                        class="mt-5 flex items-start gap-3
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
                                d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"
                            />
                        </svg>

                        <p
                            class="text-[9px] leading-5 font-bold
                               text-gray-500 dark:text-gray-400"
                        >
                            درخواست تسویه پس از بررسی ثبت می‌شود و مبلغ از
                            موجودی قابل برداشت شما کسر خواهد شد.
                        </p>

                    </div>


                    {{-- Submit --}}
                    <button
                        wire:click="requestWithdrawal"
                        wire:loading.attr="disabled"
                        wire:target="requestWithdrawal"
                        type="button"
                        @disabled(!$withdrawalAccount)
                        class="mt-5 w-full h-14 rounded-2xl
                           bg-primary-500
                           text-white
                           text-[11px] font-black
                           shadow-lg shadow-primary-500/25
                           hover:bg-primary-600
                           disabled:opacity-40
                           disabled:cursor-not-allowed
                           transition-all
                           active:scale-[.98]"
                    >

                    <span
                        wire:loading.remove
                        wire:target="requestWithdrawal"
                    >
                        ثبت درخواست تسویه
                    </span>

                        <span
                            wire:loading
                            wire:target="requestWithdrawal"
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
                            />

                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                            />
                        </svg>

                        در حال ثبت درخواست...
                    </span>

                    </button>

                </div>

            </div>

        </div>

    @endif
    @if($showWalletModal)

        <div
            class="fixed inset-0 z-[999]
               flex items-center justify-center
               p-4 sm:p-6"
            wire:key="wallet-modal"
        >

            {{-- Overlay --}}
            <div
                wire:click="closeWalletModal"
                class="absolute inset-0 bg-gray-950/60 backdrop-blur-md"
            ></div>


            {{-- Modal --}}
            <div
                class="relative w-full max-w-md
                   overflow-hidden
                   rounded-[2rem]
                   border border-white/20 dark:border-white/10
                   bg-white/95 dark:bg-gray-950/95
                   shadow-[0_25px_80px_rgba(0,0,0,0.25)]
                   backdrop-blur-xl"
            >

                {{-- Decorative background --}}
                <div
                    class="absolute -top-24 -right-24
                       w-48 h-48 rounded-full
                       bg-primary-500/10 blur-3xl pointer-events-none"
                ></div>


                {{-- Header --}}
                <div class="relative flex items-center justify-between
                        px-6 pt-6 pb-5">

                    <div>

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
                                        d="M12 8c-2.21 0-4 1.343-4 3s1.79 3 4 3 4 1.343 4 3-1.79 3-4 3m0-14V4m0 16v-2m0-14a8 8 0 100 16 8 8 0 000-16z"
                                    />
                                </svg>
                            </div>

                            <div>
                                <h3 class="text-sm font-black
                                       text-gray-900 dark:text-white">
                                    افزایش موجودی کیف پول
                                </h3>

                                <p class="mt-1 text-[10px] font-bold
                                      text-gray-400">
                                    مبلغ موردنظر خود را وارد کنید
                                </p>
                            </div>

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

                    {{-- Current Balance --}}
                    <div
                        class="mb-5 p-4 rounded-2xl
                           bg-gray-50 dark:bg-white/[0.04]
                           border border-gray-100 dark:border-white/5"
                    >

                        <div class="flex items-center justify-between">

                        <span class="text-[10px] font-bold text-gray-400">
                            موجودی فعلی
                        </span>

                            <span class="text-sm font-black
                                     text-gray-900 dark:text-white">
                            {{ number_format(auth()->user()->wallet_balance ?? 0) }}
                            <span class="text-[9px] text-gray-400">
                                تومان
                            </span>
                        </span>

                        </div>

                    </div>


                    {{-- Amount --}}
                    <div>

                        <label
                            for="walletAmount"
                            class="block mb-2 text-[11px] font-black
                               text-gray-700 dark:text-gray-300"
                        >
                            مبلغ افزایش موجودی
                        </label>


                        <div class="relative">

                            <input
                                id="walletAmount"
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                wire:model.live="walletAmount"
                                placeholder="مثلاً ۵۰۰,۰۰۰"
                                class="w-full h-14
                                   rounded-2xl
                                   border border-gray-200 dark:border-white/10
                                   bg-gray-50 dark:bg-white/[0.04]
                                   px-4 pl-20
                                   text-sm font-black
                                   text-gray-900 dark:text-white
                                   placeholder:text-gray-300 dark:placeholder:text-gray-600
                                   focus:border-primary-500
                                   focus:ring-4 focus:ring-primary-500/10
                                   outline-none transition"
                            >

                            <span
                                class="absolute left-4 top-1/2 -translate-y-1/2
                                   text-[10px] font-black text-gray-400"
                            >
                            تومان
                        </span>

                        </div>


                        @error('walletAmount')
                        <p class="mt-2 text-[10px] font-bold text-red-500">
                            {{ $message }}
                        </p>
                        @enderror

                    </div>


                    {{-- Quick Amounts --}}
                    <div class="grid grid-cols-3 gap-2 mt-4">

                        @foreach([100000, 500000, 1000000] as $amount)

                            <button
                                type="button"
                                wire:click="$set('walletAmount', '{{ $amount }}')"
                                class="h-10 rounded-xl
                                   bg-gray-100 dark:bg-white/5
                                   text-[10px] font-black
                                   text-gray-600 dark:text-gray-300
                                   hover:bg-primary-500/10
                                   hover:text-primary-500
                                   transition"
                            >
                                {{ number_format($amount) }}
                            </button>

                        @endforeach

                    </div>


                    {{-- Security Notice --}}
                    <div
                        class="mt-5 flex items-start gap-3
                           p-3.5 rounded-2xl
                           bg-emerald-500/5
                           border border-emerald-500/10"
                    >

                        <svg
                            class="w-4 h-4 mt-0.5 shrink-0 text-emerald-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 15v2m0-6a2 2 0 100-4 2 2 0 000 4zm0 0v2m8-1a8 8 0 11-16 0 8 8 0 0116 0z"
                            />
                        </svg>

                        <p class="text-[9px] leading-5 font-bold
                              text-gray-500 dark:text-gray-400">
                            پرداخت شما از طریق درگاه امن انجام می‌شود.
                            موجودی کیف پول فقط پس از تأیید موفق تراکنش افزایش خواهد یافت.
                        </p>

                    </div>


                    {{-- Submit --}}
                    <button
                        wire:click="startWalletPayment"
                        wire:loading.attr="disabled"
                        wire:target="startWalletPayment"
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

                    <span wire:loading.remove wire:target="startWalletPayment">
                        ادامه و پرداخت
                    </span>

                        <span
                            wire:loading
                            wire:target="startWalletPayment"
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

                        در حال ایجاد تراکنش...
                    </span>

                    </button>

                </div>

            </div>

        </div>

    @endif
</div>
