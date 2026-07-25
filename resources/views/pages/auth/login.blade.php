<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

new #[Layout('layouts::main')] class extends Component
{
    public $step = 1;
    public $mobile;
    public $otp;
    public $password;

    public function checkMobile()
    {
        $this->validate([
            'mobile' => 'required|regex:/^09[0-9]{9}$/',
        ],[
            'mobile.required' => 'وارد کردن شماره موبایل الزامی است.',
            'mobile.regex' => 'شماره موبایل معتبر نیست. مثال صحیح: 09123456789',
        ]);
        $this->step = 2;

    }
    public function sendOtp(\App\Services\OtpService $otpService)
    {
        try {
            $otpService->send($this->mobile);
            $this->step = 3;
            $this->dispatch('otp-sent');
        } catch (\Exception $e) {
            $this->addError('mobile', $e->getMessage());
        }
    }
    public function verifyOtp(\App\Services\OtpService $otpService)
    {
        $this->validate([
            'otp' => 'required|digits:4',
        ], [
            'otp.required' => 'لطفاً کد تایید را وارد کنید.',
            'otp.digits' => 'کد تایید باید 4 رقم باشد.',
        ]);

        $ok = $otpService->verify($this->mobile, $this->otp);

        if (!$ok) {
            $this->addError('otp', 'کد اشتباه یا منقضی است');
            return;
        }
        $user = \App\Models\User::firstOrCreate(
            ['mobile' => $this->mobile],
            [
                'password' => bcrypt(Str::random(16)),
            ]
        );
        $user->assignRole('user');
        if ($user->status === false){
            abort(403, ' کاربر شما غیر فعال است با پشتیبانی تماس بگیرید');
        }
        Auth::login($user);

        return redirect()->intended('/');
    }


    public function loginPassword()
    {
        $this->validate([
            'mobile' => ['required', 'digits:11'],
            'password' => ['required', 'string'],
        ], [
            'mobile.required' => 'شماره موبایل را وارد کنید.',
            'mobile.digits' => 'شماره موبایل باید 11 رقم باشد.',

            'password.required' => 'رمز عبور را وارد کنید.',
        ]);

        if (! Auth::attempt([
            'mobile' => $this->mobile,
            'password' => $this->password,
        ])) {

            $this->addError('password', 'شماره موبایل یا رمز عبور اشتباه است.');
            return;
        }

        $user = Auth::user();

        if (! $user->status) {
            Auth::logout();

            abort(403, 'کاربر شما غیرفعال است. لطفاً با پشتیبانی تماس بگیرید.');
        }

        session()->regenerate();

        return redirect()->intended('/');
    }

    public function changeTab($tab)
    {
        if ($tab=='otp'){
            $this->step=2;
        }else{
            $this->step=4;
        }
        $this->dispatch('changeTab',currentTab:$tab);
    }
};
?>

<div>

    <div class="min-h-screen flex items-center justify-center p-4 bg-zinc-50 dark:bg-zinc-950 transition-colors duration-500" dir="rtl">

        <div class="w-full max-w-[420px] relative">
            <div class="absolute -top-20 -right-20 w-40 h-40 bg-blue-600/20 rounded-full blur-[80px]"></div>
            <div class="absolute -bottom-20 -left-20 w-40 h-40 bg-blue-400/20 rounded-full blur-[80px]"></div>

            <div class="relative overflow-hidden bg-white/70 dark:bg-zinc-900/60 backdrop-blur-md border border-white dark:border-white/10 rounded-[3.5rem] p-8 md:p-10 shadow-lg shadow-blue-500/10">

                <div class="flex justify-center mb-8">
                    <a href="/" class="flex items-center gap-2 group">
                        <img src="assets/images/logo.png" class="w-30" alt="">
                    </a>
                </div>

                <div class="text-center mb-8">
                    <h2 class="text-xl font-black text-zinc-900 dark:text-white" id="main-title">خوش آمدید</h2>
                    <p class="text-[11px] font-bold text-zinc-500 mt-2" id="main-desc">
                        @if($step==1)
                            برای ادامه، شماره موبایل خود را وارد کنید
                        @elseif($step == 2 || $step == 3)
                            لطفا کد ارسال شده را وارد کنید
                        @else
                           لطفا رمز عبور را وارد کنید
                        @endif
                    </p>
                </div>

                @if($step == 1)

                        <div id="step-1" class="space-y-6">
                        <div class="relative">
                            <input wire:model.defer="mobile" type="tel" id="phone" placeholder="0912*******"
                                   class="w-full px-6 py-5
                               bg-white dark:bg-zinc-800
                               border border-zinc-200 dark:border-white/10
                               rounded-2xl text-left font-black tracking-widest outline-none
                               focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10
                               text-zinc-900 dark:text-white
                               placeholder:text-zinc-400 dark:placeholder:text-zinc-500
                               transition-all">
                            @error('mobile')
                            <p class="mt-2 text-red-500 font-bold text-center">{{$message}}</p>
                            @enderror
                        </div>
                        <button  wire:click="checkMobile()" class="w-full py-5 bg-blue-600 text-white rounded-2xl font-black text-[14px] shadow-lg shadow-blue-600/30 hover:bg-blue-700 transition-all active:scale-95">
                            مرحله بعد
                        </button>
                    </div>
                    @else
                     <div id="step-2" class="space-y-6">
                         <div class="flex p-1 bg-zinc-100 dark:bg-white/5 rounded-2xl mb-6">
                             <button
                                 wire:click="changeTab('otp')"
                                 id="tab-otp"
                                 @class([
                                     'flex-1 py-2.5 rounded-xl text-[11px] font-black transition-all',
                                     'bg-white dark:bg-zinc-800 shadow-sm text-blue-600' => in_array($step, [2, 3]),
                                     'text-zinc-500' => !in_array($step, [2, 3]),
                                 ])
                             >کد تایید (SMS)</button>

                             <button
                                 wire:click="changeTab('pass')"
                                 id="tab-pass"
                                 @class([
                                     'flex-1 py-2.5 rounded-xl text-[11px] font-black transition-all',
                                     'bg-white dark:bg-zinc-800 shadow-sm text-blue-600' => $step == 4,
                                     'text-zinc-500' => $step != 4,
                                 ])
                             >رمز عبور</button>
                         </div>

                        <div id="otp-area" class="space-y-6">
                            @if($step == 2)
                                <div id="before-send" class="text-center py-4">
                                    <button wire:click="sendOtp" onclick="handleSendCode()" class="w-full py-4 bg-blue-50 dark:bg-blue-500/10 text-blue-600 border border-blue-200 dark:border-blue-500/20 rounded-2xl font-black text-[13px] hover:bg-blue-100 transition-all">
                                        ارسال کد تایید به شماره موبایل
                                    </button>
                                    @error('mobile')
                                    <p class="mt-2 text-red-500 font-bold text-center">{{$message}}</p>
                                    @enderror
                                </div>
                            @elseif($step == 3)
                                <div id="after-send" class=" animate-fadeIn space-y-6">
                                    <div class="flex flex-row-reverse justify-between gap-2" id="otp-inputs">
                                        <input type="text" id="otp-1" maxlength="1" class="otp-field w-14 h-14 bg-white dark:bg-zinc-800 border rounded-xl text-center text-xl font-black text-blue-600 focus:border-blue-500 {{ $errors->has('otp') ? 'border-red-500 text-red-600' : 'border-zinc-200 dark:border-white/10' }} outline-none transition-all">
                                        <input type="text" maxlength="1" class="otp-field w-14 h-14 bg-white dark:bg-zinc-800 border rounded-xl text-center text-xl font-black text-blue-600 focus:border-blue-500 {{ $errors->has('otp') ? 'border-red-500 text-red-600' : 'border-zinc-200 dark:border-white/10' }} outline-none transition-all">
                                        <input type="text" maxlength="1" class="otp-field w-14 h-14 bg-white dark:bg-zinc-800 border rounded-xl text-center text-xl font-black text-blue-600 focus:border-blue-500 {{ $errors->has('otp') ? 'border-red-500 text-red-600' : 'border-zinc-200 dark:border-white/10' }} outline-none transition-all">
                                        <input type="text" maxlength="1" class="otp-field w-14 h-14 bg-white dark:bg-zinc-800 border rounded-xl text-center text-xl font-black text-blue-600 focus:border-blue-500 {{ $errors->has('otp') ? 'border-red-500 text-red-600' : 'border-zinc-200 dark:border-white/10' }} outline-none transition-all">
                                    </div>
                                    <input type="hidden" wire:model="otp" id="otp-value">
                                    <div class="text-center">
                                        <div wire:loading wire:target="verifyOtp" class="flex items-center justify-center gap-2 mt-2">
                                            <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            <span class="text-sm text-gray-600">در حال بررسی کد...</span>
                                        </div>
                                    </div>

                                    @error('otp')
                                    <p class="mt-2 text-red-500 font-bold text-center">{{$message}}</p>
                                    @enderror

                                    <div class="flex items-center justify-center gap-2 text-[11px] font-black">
                                        <div id="timer-container" class="text-blue-600 flex items-center gap-1">
                                            <span id="timer-text">02:00</span>
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/></svg>
                                        </div>
                                        <button id="resend-btn" wire:click="sendOtp" onclick="handleSendCode()" class="hidden text-zinc-400 hover:text-blue-600 transition-all underline decoration-dotted">
                                            ارسال مجدد کد
                                        </button>
                                    </div>

                                </div>
                            @endif



                        </div>
                         @if($step == 4)

                        <div id="pass-area" class="{{$step != 4 ? 'hidden' : ''}}">
                            <input wire:model.lazy="password" type="password" id="password" placeholder="رمز عبور خود را وارد کنید"
                                   class="w-full px-6 py-5
                               bg-white dark:bg-zinc-800
                               border border-zinc-200 dark:border-white/10
                               rounded-2xl text-center font-black tracking-widest outline-none
                               focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10
                               text-zinc-900 dark:text-white
                               placeholder:text-zinc-400 dark:placeholder:text-zinc-500
                               transition-all">

                        </div>
                         <button id="pass-button" wire:click="loginPassword()" class="w-full py-5 bg-blue-600 text-white rounded-2xl font-black text-[14px] shadow-lg shadow-blue-600/20 hover:bg-blue-700 transition-all active:scale-95">
                             تایید و ورود به زاراچرم
                         </button>
                             @error('password')
                             <p class="mt-2 text-red-500 font-bold text-center">{{$message}}</p>
                             @enderror
                         @endif
                        <button onclick="showStep(1)" class="w-full text-[10px] font-black text-zinc-400 hover:text-blue-500 transition-all text-center">ویرایش شماره موبایل</button>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
@script
    <script>
        let currentMode = 'otp';
        let isCodeSent = false;
        let countdown;

        Livewire.on('changeTab', (data) => {
            const currentMode = data.currentTab;
            if (currentMode === 'otp') {

                const firstOtpBox = document.getElementById('otp-1');
                if (firstOtpBox) {
                    firstOtpBox.focus();
                }
            } else {

                const passwordInput = document.getElementById('password');
                if (passwordInput) {
                    passwordInput.focus();
                }
            }
        });
        window.handleSendCode = function () {
            Swal.fire({
                title: 'در حال ارسال...',
                didOpen: () => { Swal.showLoading(); },
                timer: 1500,
                showConfirmButton: false,
                customClass: { popup: 'rounded-[2rem]' }
            }).then(() => {
                isCodeSent = true;
            });
        }
        // مدیریت تایمر
        Livewire.on('otp-sent', () => {
            startTimer(120);
            setTimeout(() => document.querySelector('.otp-field').focus(), 100);
            setTimeout(() => {
                document.querySelector('.otp-field')?.focus();
                initOtpInputs();
            }, 100);
        });

        function initOtpInputs() {
            const otpBox = document.getElementById('otp-inputs');
            if (!otpBox) return;
            const inputs = otpBox.querySelectorAll('.otp-field');
            const otpValue = document.getElementById('otp-value');
            if (!inputs.length || !otpValue ) return;
            inputs.forEach((input, index) => {
                input.addEventListener('input', () => {
                    input.value = input.value.replace(/\D/g, '').slice(0, 1);
                    if (input.value && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                    const otp = [...inputs]
                        .map(input => input.value)
                        .join('');
                    otpValue.value = otp;
                    otpValue.dispatchEvent(new Event('input'));
                    toggleVerifyButton(otp);
                });
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !input.value && index > 0) {
                        inputs[index - 1].focus();
                    }
                });

            });
        }


        function toggleVerifyButton( otp) {
            if (otp.length === 4 && /^\d{4}$/.test(otp)) {
                $wire.verifyOtp();
            }
        }
        function startTimer(seconds) {
            const timerText = document.getElementById('timer-text');
            const timerContainer = document.getElementById('timer-container');
            const resendBtn = document.getElementById('resend-btn');
            resendBtn.classList.add('hidden');
            timerContainer.classList.remove('hidden');

            let timeLeft = seconds;
            if(countdown) clearInterval(countdown);

            countdown = setInterval(() => {
                const m = Math.floor(timeLeft / 60);
                const s = timeLeft % 60;
                timerText.innerText = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;

                if (timeLeft <= 0) {
                    clearInterval(countdown);
                    timerContainer.classList.add('hidden');
                    resendBtn.classList.remove('hidden');
                }
                timeLeft--;
            }, 1000);
        }

        // منطق فیلدهای OTP (تمرکز خودکار)
        const otpFields = document.querySelectorAll('.otp-field');
        otpFields.forEach((field, index) => {
            field.addEventListener('input', (e) => {
                if (e.target.value.length === 1 && index < otpFields.length - 1) {
                    otpFields[index + 1].focus();
                }
            });
            field.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    otpFields[index - 1].focus();
                }
            });
        });

    </script>
@endscript

